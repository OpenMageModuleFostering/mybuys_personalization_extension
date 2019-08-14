<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Rating extends Mybuys_Connector_Model_Feed_Base
{

	// Field mapping - Magento attributes to MyBuys Feed Fields
	public function getFieldMap()
	{
		return array(
		'entity_id' => 'Product ID',
		'review_count' => 'Review Count',
		'avg_rating' => 'Average Overall Rating',
		'last_review_date' => 'Last Review Date',
		);
	}

	// File name key
	public function getFileNameKey()
	{
		return 'rating';
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

		// Don't hard code table names
		$coreStoreTable = $collection->getTable('core/store');
		$reviewTable = $collection->getTable('review/review');
		$reviewDetailTable = $collection->getTable('review/review_detail');
		$reviewEntityTable = $collection->getTable('review/review_entity');
		$ratingTable = $collection->getTable('rating/rating');
		$rovaTable = $collection->getTable('rating/rating_vote_aggregated');
		$ratingEntityTable = $collection->getTable('rating/rating_entity');

		// Add # of reviews total, avg rating & last reivew date to the query
		$collection->getSelect()
			->columns(array(
				'review_count' =>
					'  (select ' .
					'    count(*) ' .
					'  from ' . $reviewTable . ' r ' .
					'  where ' .
					'    (select store_id from ' . $reviewDetailTable . ' rd where rd.review_id=r.review_id) in (select store_id from ' . $coreStoreTable . ' where website_id = ' . $websiteId . ') and ' .
					'    r.entity_pk_value = e.entity_id and ' .
					'    r.entity_id in ' . 
					"         (select entity_id from " . $reviewEntityTable . " where entity_code = '" . Mage_Review_Model_Review::ENTITY_PRODUCT_CODE . "'))",
				'avg_rating' =>
					'  (select ' . 
					'    (sum(vote_value_sum) / sum(vote_count))' . 
					'  from ' . 
					'    ' . $rovaTable . ' rova, ' . $ratingTable . ' ra ' . 
					'  where ' . 
					'    rova.store_id in (select store_id from ' . $coreStoreTable . ' cs2 where cs2.website_id = ' . $websiteId . ') and ' .
					'    ra.entity_id in ' . 
					"         (select entity_id from " . $ratingEntityTable . " where entity_code = '" . Mage_Rating_Model_Rating::ENTITY_PRODUCT_CODE . "') and " . 
					'    rova.entity_pk_value = e.entity_id and ' .
					'    rova.rating_id = ra.rating_id ' . 
					'  group by rova.entity_pk_value) ',
				'last_review_date' =>
					'  (select ' .
					'   date(max(r2.created_at))' .
					'  from ' . $reviewTable . ' r2 ' .
					'  where ' .
					'    (select store_id from ' . $reviewDetailTable . ' rd where rd.review_id=r2.review_id) in (select store_id from ' . $coreStoreTable . ' where website_id = ' . $websiteId . ') and ' .
					'    r2.entity_pk_value = e.entity_id and ' .
					'    r2.entity_id in ' . 
					"         (select entity_id from " . $reviewEntityTable . " where entity_code = '" . Mage_Review_Model_Review::ENTITY_PRODUCT_CODE . "'))",
				));				

        // make sure we order by entity_id
        $collection->getSelect()
        	->order('e.entity_id');

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
		// Don't do any date filtering on this data, its not easy to detect which parts have changed
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
