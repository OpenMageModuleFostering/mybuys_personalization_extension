<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Category extends Mybuys_Connector_Model_Feed_Base
{

	// Field mapping - Magento attributes to MyBuys Feed Fields
	private $fieldMap = array(
		'entity_id' => 'Category ID',
		'name' => 'Name',
		'parent_id' => 'Parent ID',
		'is_root_category' => 'Is Root Category',
		);

	public function getFieldMap()
	{
		return $this->fieldMap;
	}

	// File name key
	public function getFileNameKey()
	{
		return 'category';
	}

	/**
	 * Build collection to do query
	 *
	 * @param int|string $websiteId Which website to query for collection
	 */
	public function getFeedCollection($websiteId)
	{
		// Lookup category path for root category
		// Assume only 1 store per website
		$rootCategoryId = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getRootCategoryId();
		$rootCategory = Mage::getModel('catalog/category')->load($rootCategoryId);
		$rootCategoryPath = $rootCategory->getPath();
		
		// Create collection
		$collection = Mage::getModel('catalog/category')->getCollection();
		
		// Get name and id
		$collection
			->addAttributeToSelect('name');
			
		// Filter collection to only include active categories
		$collection
			->addIsActiveFilter();

        // determine if we are at the top level of the catalog
        $collection->getSelect()
            ->from(null, array("is_root_category" => "if(level = 1, 'Yes', 'No')"));

		// Filter feed to only include products for given website
		// Do this by filtering on 'path' attribute, based on root category path found above
		// Include the root category itself in the feed
		$collection
			->addAttributeToFilter('path', array('like' => $rootCategoryPath . '%') );

        // make sure we order by entity_id
        $collection->getSelect()
        	->order('entity_id');

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
				->where("e.entity_id >= {$minEntityId}");
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
