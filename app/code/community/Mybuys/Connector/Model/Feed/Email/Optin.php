<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Email_Optin extends Mybuys_Connector_Model_Feed_Base
{

	// Field mapping - Magento attributes to MyBuys Feed Fields
	public function getFieldMap()
	{
		return array(
			'subscriber_email' => 'Email Address',
			'change_status_at' => 'Opt-In Date',
			);
	}

	// File name key
	public function getFileNameKey()
	{
		return 'email_optin';
	}

	/**
	 * Build collection to do query
	 *
	 * @param int|string $websiteId Which website to query for collection
	 */
	public function getCollection($websiteId)
	{
		// Create collection
		$collection = Mage::getResourceModel('newsletter/subscriber_collection');

		// Add entity_id field
		$collection->getSelect()
			->columns('main_table.subscriber_id as entity_id');

		// Filter feed to only include products for given website
		// Join to core_store table and grab website_id field
		$collection->getSelect()
			->joinLeft('core_store', 'main_table.store_id = core_store.store_id', 'core_store.website_id')
			->where('core_store.website_id = ' . $websiteId)
			;
		
		// Filter feed by status
		$collection
			->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

        // make sure we order by entity_id
        $collection->getSelect()
        	->order('main_table.subscriber_id');

		// Return collection
		return $collection;
	}

	/**
	 * Add filter to collection to make it only include records necessary for automatic daily feed (instead of one-time baseline feed).
	 *
	 * $collection Collection of data which will be spit out as feed
	 */
	protected function addIncrementalFilter($collection)
	{
		//
		// Don't filter the incremental feed, because sometimes the change date field in Magento is not updated
		//
		// Filter transactions by date and only send yesterday's transactions
		//$collection->getSelect()
		//	->where("date(main_table.change_status_at) = '" . $incrementalDate . "'");
		
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
				->where("main_table.subscriber_id >= {$minEntityId}");
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
