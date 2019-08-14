<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Generatefeeds
{
	const MBUYS_FEED_PATH = 'mybuys/feeds';
	
	protected static $feedTypes = array(
		'category',
		'product',
		'sku',
		'transaction',
		'crosssell',
		'rating',
		'email_optin',
		'email_optout',
		);
	
    protected function _construct(){

    }

	/**
	 * Return a list of possible feed types
	 *
	 * @returns array Array of all known feeds types
	 */
	public static function getFeedTypes()
	{
		return self::$feedTypes;
	}

	/**
	 * Generate data feeds for this specific website
	 *
	 * @param in $websiteId Id of the website for which to generate data feeds
	 * @param bool $bBaselineFile Should this file be a baseline file or an automated daily file
	 * @param string $feedType Type of feed to generate, null = generate all feeds
	 * @param int|string $minEntityId Number representing minimum value for entity Id to export - This acts as a placeholder for where the feed export left off
	 */

    public function generateForWebsite($websiteId, $bBaselineFile, $feedType, &$minEntityId, &$bDone)
	{
		// Log mem usage
        Mage::helper('mybuys')->log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
        
		// Check data feeds enabled
		// Check feed of this type if config is enabled
		if	(Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/general/allfeedsenabled') != 'enabled' ||
			Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/feedsenabled/' . $feedType) != 'enabled') 
		{
           	Mage::throwException('Data feeds or feedtype ' . $feedType . ' not enabled for website: ' . $websiteId);
		}

		// Lookup up throttle param
		$throttle = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/advanced/throttle');
		
		// Build path of where to store feeds
		$feedExportPath = Mage::getConfig()->getVarDir() . DS . Mybuys_Connector_Model_Generatefeeds::MBUYS_FEED_PATH;
		// Check and create folder
		$oIo = new Varien_Io_File();
		$oIo->checkAndCreateFolder($feedExportPath);

		// Create feed
		// Generate just the one specific type of feed
		$modelFeed = Mage::getModel('mybuys/feed_' . $feedType);
		$modelFeed->generate($websiteId, $feedExportPath, $bBaselineFile, $throttle, $minEntityId, $bDone);
		
	}
}
	 
