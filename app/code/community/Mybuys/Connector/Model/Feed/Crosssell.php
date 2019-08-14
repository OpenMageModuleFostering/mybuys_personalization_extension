<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Crosssell extends Mybuys_Connector_Model_Feed_Base
{

	// Field mapping - Magento attributes to MyBuys Feed Fields
	public function getFieldMap()
	{
		return array(
		'product_id' => 'Product ID',
		'linked_products' => 'Crosssell Product IDs',
		);
	}

	// File name key
	public function getFileNameKey()
	{
		return 'crosssell';
	}

	/**
	 * Build collection to do query
	 *
	 * @param int|string $websiteId Which website to query for collection
	 */
	public function getFeedCollection($websiteId)
	{
		// Create collection
		$collection = Mage::getResourceModel('catalog/product_link_collection');

		// Add entity_id field
		$collection->getSelect()
			->columns('product_id as entity_id');

		// Filter feed to only include products for given website
		$collection->getSelect()
			->where($websiteId . ' in (select website_id from catalog_product_website cpw where cpw.product_id = main_table.product_id)')
			;

        // Filter based on types of links
        // Cross Sells, Upsells & Related for now
        $collection->getSelect()
        	->where('main_table.link_type_id IN (?)', 
        		array(
        			Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED,
        			Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL,
        			Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL,
        		));
        		
        // Do group by and concat to build list of related product ids
        $collection->getSelect()
        	->group('main_table.product_id')
        	->columns(array(
        		'linked_products' =>
        		"group_concat(main_table.linked_product_id separator ', ')"))
        	;
        	
        // make sure we order by entity_id
        $collection->getSelect()
        	->order('product_id');

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
		// Add mim entity id filter
		if($minEntityId > 0) {
			$collection->getSelect()
				->where("product_id >= {$minEntityId}");
		}
			
		// Add throttle param
		if($throttle > 0) {
			$collection->getSelect()
				->limit($throttle);
		}
		
		// Return the modified collection
		return $collection;
	}

}
