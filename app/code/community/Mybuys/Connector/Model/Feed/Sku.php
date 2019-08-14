<?php
/**
 * MyBuys Magento Connector
 *
 * @category    Mybuys
 * @package        Mybuys_Connector
 * @website    http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright    Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Sku extends Mybuys_Connector_Model_Feed_Base
{
    private $fieldMap = array(
        'sku' => 'SKU ID',
        'product_id' => 'Product ID',
        'stock_status' => 'Inventory Status',
        'calc_base_price' => 'Base Price',
        'minimal_price' => 'Current Price',
        'adj_qty' => 'Inventory Quantity',
        //'qty' => 'Real Stock Qty',
        'type_id' => 'Magento Product Type',
        //'base_price'=>'base_price',
        //'calc_base_price'=>'calc_base_price'
        //'attributes' => 'Generic Attributes',
    );

    // Field mapping - Magento attributes to MyBuys Feed Fields
    public function getFieldMap()
    {
        return $this->fieldMap;
    }

    // File name key
    public function getFileNameKey()
    {
        return 'sku';
    }

    /**
     * Build collection to do query
     *
     * @param int|string $websiteId Which website to query for collection
     */
    public function getFeedCollection($websiteId)
    {
        // Create collection
        $collection = Mage::getResourceModel('catalog/product_collection');

        // Filter feed to only include products for given website
        $collection
            ->addWebsiteFilter($websiteId);

        // Filter on enabled
        $collection->
            addFieldToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);


        // Add product name to feed
        $collection
            ->addAttributeToSelect('name');
            
        // Add product sku to feed
        $collection
            ->addAttributeToSelect('sku');

        //add price data
        $collection
            ->addPriceData(null, $websiteId);   // have to use this version so you can set the website id

        // Add stock level fields
        $collection->joinTable(
            array('at_qty'=>'cataloginventory/stock_item'),
            'product_id=entity_id',
            array('qty' => 'qty', 'is_in_stock' => 'is_in_stock'),
            '{{table}}.stock_id=1',
            'left');

/*
        //get base price based on product type
        $collection
            ->addExpressionAttributeToSelect(
                'base_price',
                "if({{type_id}} = 'bundle' OR {{type_id}} = 'giftcard' OR {{type_id}} = 'grouped', " .
                    "price_index.min_price, " .
                    "price_index.price)",
                'type_id'
            );
*/
        //base price
        // for grouped and bundled products - return the average price
        // for giftcards - return the minimum price
        // for other products - return the price
        $collection
            ->addExpressionAttributeToSelect(
                'calc_base_price',
                "IF({{type_id}} NOT IN ('giftcard','grouped','bundle'),
                    price_index.price,
                    ROUND(
                        IF({{type_id}} IN ('bundle','grouped'),
                            -- get average price
                            (IF(price_index.tier_price IS NOT NULL,
                                LEAST(price_index.min_price, price_index.tier_price),
                                price_index.min_price)
                            + price_index.max_price / 2),
                            -- else get minimum price
                            (IF(price_index.tier_price IS NOT NULL,
                                LEAST(price_index.min_price, price_index.tier_price),
                                price_index.min_price))),
                        2))",
                'type_id'
            );


        // Add product attributes which indicate if product is visible or not
        // Status, visibility and is_in_stock must all be set for product to show up
        $collection
            ->addExpressionAttributeToSelect('cur_status', "{{status}}", 'status')
            ->addExpressionAttributeToSelect('cur_visibility', "{{visibility}}", 'visibility');


        $joinCond = 'super_link.product_id = `e`.`entity_id`';
        $colls = 'super_link.parent_id';
        $collection->getSelect()
            ->joinLeft(
                array('super_link' => $collection->getTable('catalog/product_super_link')),
                $joinCond,
                $colls
            );

        $collection
            ->addExpressionAttributeToSelect(
                'product_id',
                "if(super_link.parent_id IS NULL, " .
                    "{{entity_id}}, " .
                    "super_link.parent_id)",
                "entity_id"
            );


        // Add stock info for parent
        $collection->getSelect()->joinLeft(
            array("pstock"=>$collection->getTable('cataloginventory/stock_item')),
            join(' AND ',
                array('super_link.parent_id=pstock.product_id',
                      'pstock.stock_id=1')),
            array('qty','is_in_stock'));


        $collection
             ->addExpressionAttributeToSelect(
                 'adj_qty',
                 "if({{type_id}} = 'simple', " .
                     "at_qty.qty, " .
                     "if (at_qty.is_in_stock=1, ".
                         "if (at_qty.qty>0, ".
                             "at_qty.qty, at_qty.is_in_stock),".
                         "at_qty.is_in_stock))",
                 'type_id'
             );

        $collection
            ->addExpressionAttributeToSelect(
                'stock_status',
                "if(super_link.parent_id IS NULL, " .
                    "at_qty.is_in_stock, " .
                    "pstock.is_in_stock)",
                "entity_id"
            );

        //TODO - get status for parent somehow
        //LEFT JOIN `catalog_product_entity_int` AS `at_status_parent_default` ON (`at_status_parent_default`.`entity_id` = super_link.parent_id) AND (`at_status_parent_default`.`attribute_id` = '273') AND `at_status_parent_default`.`store_id` = 0

        // Add price
        $collection
            ->addExpressionAttributeToSelect('cur_price', "{{price}}", 'price');
/*
        // Add any custom attributes to feed
        $customAttribs = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/feedsenabled/product_attributes');
        $collection = $this->addCustomAttributes($collection, $customAttribs, $this->fieldMap);
*/
        // Make sure we order by entity_id
        $collection->getSelect()
            ->order('e.entity_id');

        $collection->groupByAttribute('entity_id');
        // Return collection
        return $collection;
    }

    /**
     * Add filter to collection to make it only include records necessary for automatic daily feed (instead of one-time baseline feed).
     *
     * $collection Collection of data which will be spit out as feed
     */
    protected function addIncrementalFilter($collection, $incrementalDate=null)
    {
        // No incremental date filtering of this feed
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
        if ($minEntityId > 0) {
            $collection->getSelect()
                ->where("e.entity_id >= {$minEntityId}");
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
