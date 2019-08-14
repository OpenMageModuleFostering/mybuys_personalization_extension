<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Adminhtml_Export_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

	public function __construct()
	{
		parent::__construct();
		$this->setId("exportGrid");
		$this->setDefaultDir("ASC");
		$this->setSaveParametersInSession(true);
	}

	protected function _prepareCollection()
	{
		// Build a collection representing each data feed for each website
		$collection = new Varien_Data_Collection();

		// Iterate websites and check configuration
		$websites = Mage::app()->getWebsites(false, true);
		foreach($websites as $website) {
			// Grab id from website
			$websiteId = $website->getId();
			
			// Create Website Row in Grid
			if(Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/general/allfeedsenabled') == 'enabled') {
				// Lookup configuration for this site
				$feedTypes = '';
				foreach(Mybuys_Connector_Model_Generatefeeds::getFeedTypes() as $curFeedType) {
                	if(Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/feedsenabled/' . $curFeedType) == 'enabled') {
                		if(strlen($feedTypes) > 0) {
                			$feedTypes .= ', ';
                		}
                		$feedTypes .= $curFeedType;
                	}
                }
                $sftpUser = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/connect/username');
                $sftpDestination = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/connect/hostname');				
				
				// Create and save grid item
				$newItem = $collection->getNewEmptyItem();
				$newItem->setData(array(
					'id' => $website->getId(),
					'website_name' => $website->getName(),
					'website_code' => $website->getCode(),
					'feeds' => $feedTypes,
					'sftp_destination' => $sftpDestination,
					'sftp_user' => $sftpUser,
					));
				$collection->addItem($newItem);
			}

		}

		$this->setCollection($collection);
		return $this;
	}

	protected function _prepareColumns()
	{
		$this->addColumn('id', array(
			'header'    => Mage::helper('mybuys')->__('Website ID'),
			'width'     => '50px',
			'index'     => 'id'
		));

		$this->addColumn('website_name', array(
			'header'    => Mage::helper('mybuys')->__('Website Name'),
			'width'     => '110px',
			'index'     => 'website_name'
		));

		$this->addColumn('website_code', array(
			'header'    => Mage::helper('mybuys')->__('Website Code'),
			'width'     => '100px',
			'index'     => 'website_code'
		));

		$this->addColumn('feeds', array(
			'header'    => Mage::helper('mybuys')->__('Feeds to Send'),
			'width'     => '320px',
			'index'     => 'feeds'
		));

		$this->addColumn('sftp_destination', array(
			'header'    => Mage::helper('mybuys')->__('SFTP Destination'),
			'width'     => '140px',
			'index'     => 'sftp_destination'
		));

		$this->addColumn('sftp_user', array(
			'header'    => Mage::helper('mybuys')->__('SFTP User'),
			'width'     => '100px',
			'index'     => 'sftp_user'
		));

        $this->addColumn('action', array(
            'header'   => Mage::helper('mybuys')->__('Action'),
            'filter'   => false,
            'sortable' => false,
            'renderer' => 'mybuys/adminhtml_export_grid_renderer_action'
        ));

		return parent::_prepareColumns();
	}

	public function getRowUrl($row)
	{
		return $this->getUrl("*/*/exportone", array("id" => $row->getId()));
	}

}
