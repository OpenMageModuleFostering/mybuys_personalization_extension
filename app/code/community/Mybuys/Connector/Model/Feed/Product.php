<?php
/**
 * MyBuys Magento Connector
 *
 * @category    Mybuys
 * @package        Mybuys_Connector
 * @website    http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright    Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Product extends Mybuys_Connector_Model_Feed_Base
{

    // Field mapping - Magento product fields to MyBuys Product Feed

    private $fieldMap = array(

        'entity_id' => 'Product ID',
        'name' => 'Product Name',
        'category_ids' => 'Category IDs',
        'product_url' => 'URL',
        'image_url' => 'Large Image URL',
        'calc_base_price' => 'Base Price',
        'minimal_price' => 'Current Price',
        'is_on_sale' => 'On Sale',
        'adj_qty' => 'Inventory Quantity',
        'is_in_stock' => 'Inventory Status',
        'cur_visibility' => 'Visibility',
        'type_id' => 'Product Type',
/*
        'price' => 'price',
        'final_price' => 'final_price',
        'minimal_price' => 'minimal_price',
        'min_price' => 'min_price',
        'max_price' => 'max_price',
        'tier_price' => 'tier_price',
        'avg_price'=>'avg_price',
*/
    );

    public function getFieldMap()
    {
        return $this->fieldMap;
    }

    // File name key
    public function getFileNameKey()
    {
        return 'product';
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

        // Add product name to feed
        $collection
            ->addAttributeToSelect('name');

        // Filter feed to only include products for given website
        $collection
            ->addWebsiteFilter($websiteId);

        // Filter on enabled
        $collection->
            addFieldToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        // Filter on visible
        $collection
            ->addAttributeToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));

        //add price data
        $collection
//            ->addMinimalPrice();
            ->addPriceData(null, $websiteId);   // have to use this version so you can set the website id

        // Add stock level fields
        $collection->joinTable(
            array ('at_qty'=>'cataloginventory/stock_item'),
            'product_id=entity_id',
            array('qty' => 'qty', 'is_in_stock' => 'is_in_stock'),
            '{{table}}.stock_id=1',
            'left');

        // Add categories to query
        $prodCatTable = $collection->getTable('catalog/category_product');
        $collection->getSelect()
            ->columns(array(
                'category_ids' =>
                '  (select ' .
                    "      group_concat(distinct pc.category_id separator ' | ') " .
                    '  from ' . $prodCatTable . ' pc ' .
                    '  where ' .
                    '      pc.product_id = e.entity_id) '
            ,));


        // Add full product page URL
        // Lookup un-secure store base URL
        $baseUrl = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getBaseUrl();
        $coreRewriteTable = $collection->getTable('core/url_rewrite');
        $collection->getSelect()
            ->columns(array(
                'product_url' =>
                "  (select " .
                    "    concat('{$baseUrl}', url.request_path) " .
                    "  from " .
                    "    {$coreRewriteTable} url " .
                    "  where  " .
                    "    id_path = concat('product/', e.entity_id) " .
                    "  limit 1) "
            ,));

        // Add product image URL
        // TODO - check for other images (small and thumb) just in case the default one is not defined
        $imageBaseURL = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . "catalog/product";
        $collection
            ->addExpressionAttributeToSelect(
                'image_url',
                "if({{image}} <> 'no_selection', " .
                    "  concat('{$imageBaseURL}', {{image}}), " .
                    "  '')",
                'image'
            );


        $collection->getSelect()->columns('if(price_index.final_price < price_index.price, 1, 0) as is_on_sale ');

        // Add product attributes which indicate if product is visible or not
        // Status, visibility and is_in_stock must all be set for product to show up
        $collection
            ->addExpressionAttributeToSelect('cur_status', "{{status}}", 'status');

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

        // Add any custom attributes to feed
        $customAttribs = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/feedsenabled/product_attributes');
        $collection = $this->addCustomAttributes($collection, $customAttribs, $this->fieldMap);

        // Make sure we order by entity_id
//        $collection->getSelect()
//            ->order('e.entity_id');

        // Return collection
        return $collection;
    }

    /**
     * Add filter to collection to make it only include records necessary for automatic daily feed (instead of one-time baseline feed).
     *
     * @param Varien_Data_Collection_Db $collection Collection of data which will be spit out as feed
     */
    protected function addIncrementalFilter($collection, $incrementalDate = NULL)
    {
        Mage::helper('mybuys')->log('Adding incremental filters to product feed', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        // daily feeds do not include out of stock items Filter on in_stock
        $collection->
            addAttributeToFilter('is_in_stock', 1);

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
        // Add mim entity id filter
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
