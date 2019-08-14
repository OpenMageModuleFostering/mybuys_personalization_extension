<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Constants
	 */
	const LOG_FILE    =   'mybuys.log';
	/*
	 * Example of how logging should be done in this extension:
	 *     Mage::log($message, Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
	 */

    public function sendErrorEmail($websiteId, $error)
    {
    	try {
	    	// Get recipients
    	    $recipients = explode(',', Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/notification/emails'));
        	if (!count($recipients)) {
            	Mage::log('No recipients for error email', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
	            return;
    	    }
        	    
        	// Create Zend_Mail
        	$mail = new Zend_Mail();

        	// Set recipients
	        foreach ($recipients as $email) {
            	$mail->addTo(trim($email));
    	    }

        	// Subject
        	$mail->setSubject('MyBuys Magento Extension Error Nofication - ' . Mage::getSingleton('core/date')->gmtDate());

	        // Body
    	    $sBody = 'Timestamp: ' . Mage::getSingleton('core/date')->gmtDate() . "\n\n";
        	$sBody .= "Error:\n$error\n\n";
        	$mail->setBodyText($sBody);

        	// From
        	$mail->setFrom(Mage::getStoreConfig('general/store_information/address'));

        	// Send
        	$mail->send();
        }
        catch(Exception $e) {
            // Log exception
            Mage::logException($e);
            Mage::log('Failed to send error email, error: ', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}    
    }

	/**
	 * Validate feed configuration settings for one website or all websites
	 *
	 */
	public function validateFeedConfiguration($websiteId)
	{
		// Check input params
		$websites = array();
		if($websiteId) {
			$websites[] = $websiteId;
		}
		else {
			$websiteModels = Mage::app()->getWebsites(false, true);
			foreach($websiteModels as $curWebsite) {
				$websites[] = $curWebsite->getId();
			}
		}

		// Track if feeds enabled for any website
		$bFeedsEnabled = false;
		// Iterate all websites
		foreach($websites as $curWebsiteId) {		
			// Check data feeds enabled
			// Check feed of this type if config is enabled
			if	(Mage::app()->getWebsite($curWebsiteId)->getConfig('mybuys_datafeeds/general/allfeedsenabled') == 'enabled') {	
				// Track if feeds enabled for any website
				$bFeedsEnabled = true;

				// Lookup up throttle param
				$throttle = Mage::app()->getWebsite($curWebsiteId)->getConfig('mybuys_datafeeds/advanced/throttle');
				if($throttle < 0) {
           			Mage::throwException('Invalid throttle parameter (' . $throttle . ') for website id: ' . $curWebsiteId);
				}
						
				// Assemble SFTP credentials
				try {
					// Get hostname & port
					$sftpHost = Mage::app()->getWebsite($curWebsiteId)->getConfig('mybuys_datafeeds/connect/hostname');
					$sftpPort = Mage::app()->getWebsite($curWebsiteId)->getConfig('mybuys_datafeeds/connect/port');
					// Get user credentials from config
					$sftpUser = Mage::app()->getWebsite($curWebsiteId)->getConfig('mybuys_datafeeds/connect/username');
					$sftpPassword = Mage::app()->getWebsite($curWebsiteId)->getConfig('mybuys_datafeeds/connect/password');
					$sftpPassword = Mage::helper('core')->decrypt(trim($sftpPassword));
				}
				catch (Exception $e)
				{
					// Log
					Mage::logException($e);
					Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
					// Throw proper error message
           			Mage::throwException('Error looking up feed transfer connectivity parameters for website id: ' . $curWebsiteId);				
				}
				// Check SFTP credentials
				if(strlen($sftpHost) <= 0) {
           			Mage::throwException('SFTP host (' . $sftpHost . ') setting is invalid for website id: ' . $curWebsiteId);				
				}
				if(strlen($sftpPort) <= 0 || $sftpPort < 1 || $sftpPort > 65535) {
           			Mage::throwException('SFTP port (' . $sftpPort . ') setting is invalid for website id: ' . $curWebsiteId);				
				}
				if(strlen($sftpUser) <= 0) {
           			Mage::throwException('SFTP user (' . $sftpUser . ') setting is invalid for website id: ' . $curWebsiteId);				
				}
				if(strlen($sftpPassword) <= 0) {
           			Mage::throwException('SFTP password setting is invalid for website id: ' . $curWebsiteId);				
				}
			}
		}

		// Now send error message if feeds not enable for any website
		if(!$bFeedsEnabled) {
        	Mage::throwException('Data feeds or not enabled for any website.');
        }
	}

}
	 