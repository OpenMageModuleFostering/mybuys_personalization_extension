<?php
/**
 * MyBuys Magento Connector
 *
 * @category    Mybuys
 * @package        Mybuys_Connector
 * @website    http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright    Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Transaction extends Mybuys_Connector_Model_Feed_Base
{
    // Field mapping - Magento attributes to MyBuys Feed Fields

    private $fieldMap = array(
        'customer_email' => 'Email Address',
        'created_at' => 'Transaction Date',
        'order_id' => 'Transaction ID',
        'base_subtotal' => 'Transaction Total',
        'calc_product_id' => 'Item ID',
        'qty_ordered' => 'Item Quantity',
        'base_original_price' => 'Item Price',
        'base_row_total' => 'Item Subtotal',
        'base_original_price' => 'Item Original Price',
        'increment_id' => 'Order Id',
    );

    public function getFieldMap()
    {
        return $this->fieldMap;
    }

    // Feed file name key
    public function getFileNameKey()
    {
        return 'transaction';
    }

    /**
     * Build collection to do query
     *
     * @param int|string $websiteId Which website to query for collection
     */
    public function getFeedCollection($websiteId)
    {
        // Create collection (of sales order items
        $collection = Mage::getResourceModel('sales/order_item_collection');

        $collection // tried to use an array here, but it kept giving an error
            ->addAttributeToSelect('order_id')
            ->addAttributeToSelect('created_at')
            ->addAttributeToSelect('product_id')
            ->addAttributeToSelect('qty_ordered')
            ->addAttributeToSelect('base_price')
            ->addAttributeToSelect('base_row_total')
            ->addAttributeToSelect('base_original_price')
            ->addAttributeToSelect('parent_item_id');

        //filter out child products
        $collection->addAttributeToFilter('parent_item_id', array('null' => true));

        // Filter collection for current website
        // need to join to core_store table and grab website_id field
        $collection->getSelect()
            ->joinLeft('core_store', 'main_table.store_id = core_store.store_id', 'core_store.website_id')
            ->where('core_store.website_id = ' . $websiteId);

        // Join order item up with main order record for subtotals and emails
        $collection->getSelect()
            ->joinLeft('sales_flat_order', 'main_table.order_id = sales_flat_order.entity_id', array('base_subtotal', 'customer_email', 'increment_id'));

        //get the visibility data from the product's attribute data
        //thanks Vanai
        $attributeCode = 'visibility';
        $alias = $attributeCode . '_table';
        $attribute = Mage::getSingleton('eav/config')
            ->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeCode);

        $collection->getSelect()
            ->join(
                array($alias => $attribute->getBackendTable()),
                "main_table.product_id = $alias.entity_id AND $alias.attribute_id={$attribute->getId()}",
                array($attributeCode => 'value')
            );


        //addExpressionAttributeToSelect does not work for the order_item_collection model
        //use the send columns function to add the if directly
        $collection
            ->getSelect()->columns(array('calc_product_id' =>
            'if((product_type="grouped" and `visibility_table`.`value` = ' . Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE . '),
   	            if(LOCATE(\'super_product_config\', product_options)>0,
   		            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(product_options,\'super_product_config\',-1), \'product_id\',-1), \'";\',2),\':"\',-1) AS UNSIGNED),
   		            0),
   	            `main_table`.`product_id`)'));

		// Add alias for entity_id column to use with throttle function
		$collection->getSelect()
			->columns(array('entity_id' => 'main_table.item_id'));

        // order by order item id
        $collection->getSelect()
            ->order('main_table.item_id');

        // Return collection
        return $collection;
    }

    /**
     * Add filter to collection to make it only include records necessary for automatic daily feed (instead of one-time baseline feed).
     *
     * $collection Collection of data which will be spit out as feed
     */
    protected function addIncrementalFilter($collection, $incrementalDate)
    {

        // convert given date into GMT (Magento) time
        $dateStart = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(strtotime($incrementalDate . "00:00:00")));
        $dateEnd = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(strtotime($incrementalDate . "23:59:59")));

        $collection->getSelect()
            ->where("date(main_table.created_at) between '" . $dateStart . "' AND '" . $dateEnd . "'");

        return $collection;
    }

    /**
     * Add throttle parameter to collection to limit output to X number of rows
     *
     * @param Varien_Data_Collection_Db $collection Collection of data which will be spit out as feed
     * @param int|string $throttle Number representing maximum record count which should be included in this feed generation run
     * @param int|string $minEntityId Number representing minimum value for entity Id to export - This acts as a placeholder for where the feed export left off
     */
    protected function addThrottleFilter($collection, $throttle, $minEntityId)
    {
        // Add mim entity id filter
        if ($minEntityId > 0) {
            $collection->getSelect()
                ->where("main_table.item_id >= {$minEntityId}");
        }

        // Add throttle param
        if ($throttle > 0) {
            $collection->getSelect()
                ->limit($throttle);
        }

        // Return the modified collection
        return $collection;
    }

}
