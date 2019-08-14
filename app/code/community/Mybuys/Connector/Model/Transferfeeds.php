<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Transferfeeds
{
	// Folder on MyBuys FTP is hardcoded in extension
	const SFTP_FOLDER = '.';

	/**
	 * Transfer data feeds to MyBuys, triggered by cron
	 *
	 * @param int|string $websiteId Id of the website for which to generate data feeds
	 */
	public function transfer($websiteId)
	{
		try {
			// Log
           	Mage::log('Transferring data feeds for website with Id: ' . $websiteId, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
            
			// Check data feeds enabled
			if(Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/general/allfeedsenabled') != 'enabled') {
    	       	Mage::throwException('Data feeds not enabled for website: ' . $websiteId);
			}
        	
           	// Build list and transfer feeds for site
           	$fileList = $this->buildFileList($websiteId);
           	$bSuccess = $this->transferFileList($websiteId, $fileList);
           	if(!$bSuccess) {
               	Mage::throwException('Transfer file list failed!');
           	}
            	
       	    Mage::log('Sucessfully transferred data feeds for website.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
		}
		catch(Exception $e) {
       	    Mage::log('Failed to transfer data feeds for website.', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
           	Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
           	// Rethrow
           	throw $e;
		}        
	}
	
	/**
	 * Build list of files to transfer, based on enabled website
	 *
	 * @param int|string $websiteId Id of the website for which to generate data feeds
	 * @return array List of files to transfer for this website (full path & filename specified for each)
	 */
	protected function buildFileList($websiteId)
	{
		// Lookup feed path
		$feedPath = Mage::getConfig()->getVarDir() . DS . Mybuys_Connector_Model_Generatefeeds::MBUYS_FEED_PATH;
		
		// Log
   	    Mage::log('Searching for feed files for website id: ' . $websiteId . ' here: ' . $feedPath, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);		
		
		// Create websiteid match string
		$websiteIdMatchString = '-websiteid-' . $websiteId;

		// List to hold files we find
		$fileList = array();

		// Open feed directory
		$dh = opendir($feedPath);
        if ($dh === FALSE) {
           	Mage::throwException('Failed to open feed directory: ' . $feedPath);
        }
        
        // Iterate files in directory
        while (($entry = readdir($dh)) !== FALSE) {
        	// Get full path
            $fullpath = $feedPath . DS . $entry;
            
            // Check if have a file (as opposed to directory or link)
            if( is_file($fullpath) ) {
            	// Check if our file is for the correct websiteId
            	if(strpos($fullpath, $websiteIdMatchString) !== FALSE) {
            		// Add file to our list
            		$fileList[] = $fullpath;
            	}
            }
        }
        
        // Close dir handle
        closedir($dh);

		// Log
   	    Mage::log('Found ' . count($fileList) . ' feed files for website id: ' . $websiteId, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);		
		
		return $fileList;
	}
	
	/**
	 * Transfer this list of files to the SFTP site for MyBuys
	 *
	 * @param int|string $websiteId Id of the website for which to generate data feeds
	 * @param array $fileList List of file names (full path) to transfer
	 * @return bool Indicates if files successfully transfered or not
	 */
	protected function transferFileList($websiteId, array $fileList)
	{
		// Log
   	    Mage::log('Transferring ' . count($fileList) . ' files for website id: ' . $websiteId, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);		
   	    
		// Assemble SFTP crednetials
		try {
			// Get hostname & port
			$sftpHost = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/connect/hostname');
			$sftpPort = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/connect/port');
			// Get user credentials from config
			$sftpUser = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/connect/username');
			$sftpPassword = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/connect/password');
			$sftpPassword = Mage::helper('core')->decrypt(trim($sftpPassword));
		}
		catch (Exception $e)
		{
			// Log
			Mage::logException($e);
			Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
			return false;
		}

		//TODO - Add debug infromation to the email that gets sent out

		// Connect to server
		$connection = Mage::helper('mybuys/sftpConnection');
		/* @var $file Mybuys_Connector_Helper_SftpConnection */
		$bSuccess = $connection->connect($sftpHost, $sftpPort, $sftpUser, $sftpPassword);
		if(!$bSuccess) {
       	    Mage::log('Failed to connect to MyBuys!', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
			return false;
		}
		
		// Change to upload folder
		$sftpFolder = self::SFTP_FOLDER;
		$bSuccess = $connection->changeDir($sftpFolder);
		if(!$bSuccess) {
       	    Mage::log('Failed to change folders to: ' . $sftpFolder, Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
			return false;
		}
		
		// Iterate file list and put each file
		$bTransferSucceeded = true;
		foreach($fileList as $curFile) {
			// Log
            Mage::log('Transferring file: ' . $curFile, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
            // Transfer file
			$bSuccess = $connection->putAndDeleteFile($curFile);
			if(!$bSuccess) {
	       	    Mage::log('Failed to transfer and delete file: ' . $curFile, Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
				$bTransferSucceeded = false;
			}
		}

		// Close connection, dont check result
		$connection->close();
		
		// Check results
		if(!$bTransferSucceeded) {
       	    Mage::log('Some file transfers failed!', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
       	    return false;
		}
		else {
   		    Mage::log('Successfully transferred all files.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);		
			return true;
		}
		
	}

}
	 
