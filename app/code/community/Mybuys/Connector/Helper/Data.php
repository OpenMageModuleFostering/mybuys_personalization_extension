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
	const LOG_FILE      =   'mybuys.log';
    const TAIL_SIZE     =   5000;
	/*
	 * Example of how logging should be done in this extension:
	 *     Mage::helper('mybuys')->log($message, Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
	 */

    public function log ($message, $level=null,$file=null,$force=false ) {
        $force = $force ||
                (Mage::getStoreConfig('mybuys_datafeeds/advanced/force_logging')!=="disabled");
        Mage::log($message,$level,$file,$force);
    }


    public function sendErrorEmail($websiteId, $error)
    {
    	try {
	    	// Get recipients
    	    $recipients = explode(',', Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/notification/emails'));
        	if (!count($recipients)) {
                Mage::helper('mybuys')->log('No recipients for error email', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
	            return;
    	    }

        	// Create Zend_Mail
        	$mail = new Zend_Mail();

        	// Set recipients
	        foreach ($recipients as $email) {
            	$mail->addTo(trim($email));
    	    }

        	// Subject
        	$clientId= Mage::app()->getWebsite($websiteId)->getConfig('mybuys_websitecode/general/identifier');
        	$mail->setSubject('MyBuys Magento Extension Error Notification for Client ID '. $clientId .
                              ' - ' . Mage::getSingleton('core/date')->gmtDate());

	        // Body
    	    $sBody = 'Timestamp: ' . Mage::getSingleton('core/date')->gmtDate() . "\n\n";
        	$sBody .= "Error:\n$error\n\n";
        	$mail->setBodyText($sBody);

        	// From
            $from = Mage::app()->getWebsite($websiteId)->getConfig('trans_email/ident_general/email')!="" ?
                        Mage::app()->getWebsite($websiteId)->getConfig('trans_email/ident_general/email'):
                        Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/notification/sendlog');
        	$mail->setFrom($from);

            $sendLogOption = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/notification/sendlog');

            $logFilePath = Mage::getBaseDir('log') . DS . Mybuys_Connector_Helper_Data::LOG_FILE;

            switch ($sendLogOption) {
                case 'none': {
                    break;
                }

                case 'link': {
                    //get secure link to log
                    $logFileUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB,true) ."var/log/". Mybuys_Connector_Helper_Data::LOG_FILE;
                    $sBody .= "Link to log file = ".$logFileUrl . "\n";
                    $sBody .= "*Note: You must allow access to the mybuys.log file located in /var/log/ through the .htaccess file for this directory. \n";
                    $mail->setBodyText($sBody);
                    break;
                }

                case 'partial': {
                    $buffer=$this->tail($logFilePath,Mybuys_Connector_Helper_Data::TAIL_SIZE,4096);
                    $tmpFileName = Mage::getBaseDir('var') . DS . Mybuys_Connector_Model_Generatefeeds::MBUYS_FEED_PATH . DS . "mybuys_tail.log";
                    $tmpZipFile = Mage::getBaseDir('var') . DS . Mybuys_Connector_Model_Generatefeeds::MBUYS_FEED_PATH . DS . "mybuyslog.zip";
                    $tmpFile = fopen($tmpFileName,"w");
                    fwrite($tmpFile,$buffer);
                    fclose($tmpFile);
                    $this->createZip(array($tmpFileName),$tmpZipFile);

                    $at = new Zend_Mime_Part(file_get_contents($tmpZipFile));
                    $at->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
                    $at->encoding = Zend_Mime::ENCODING_BASE64;
                    $at->filename   = 'MyBuysLog.zip';
                    $at->type       = 'application/zip';
                    $mail->addAttachment($at);

                    break;
                }

                case 'zipped' : {
                    $tmpZipFile = Mage::getBaseDir('var') . DS . Mybuys_Connector_Model_Generatefeeds::MBUYS_FEED_PATH . DS . "mybuyslog-".time().".zip";
                    $this->createZip(array($logFilePath),$tmpZipFile);

                    $at = new Zend_Mime_Part(file_get_contents($tmpZipFile));
                    $at->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
                    $at->encoding = Zend_Mime::ENCODING_BASE64;
                    $at->filename   = 'MyBuysLog.zip';
                    $at->type       = 'application/zip';
                    $mail->addAttachment($at);
                    break;
                }
            }

        	// Send
        	$mail->send();

            //clean up any tmp files
            if (isset($tmpFileName)) unlink($tmpZipFile);
            if (isset($tmpFileName)) unlink($tmpFileName);
        }
        catch(Exception $e) {
            // Log exception
            Mage::logException($e);
            Mage::helper('mybuys')->log('Failed to send error email, error: ', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::helper('mybuys')->log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}    
    }

	/**
	 * Validate feed configuration settings for one website or all websites
	 *
	 */
	public function validateFeedConfiguration($websiteId=null)
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
                    Mage::helper('mybuys')->log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
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

    //from http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
    private function tail($filename, $lines = 10, $buffer = 4096)
    {
        // Open the file
        $f = fopen($filename, "rb");

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if(fread($f, 1) != "\n") $lines -= 1;

        // Start reading
        $output = '';
        $chunk = '';

        // While we would like more
        while(ftell($f) > 0 && $lines >= 0)
        {
            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);

            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);

            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)).$output;

            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while($lines++ < 0)
        {
            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }

        // Close file and return
        fclose($f);
        return $output;
    }

    //http://davidwalsh.name/create-zip-php
    /* creates a compressed zip file */
    function createZip($files = array(), $destination = '', $overwrite = false)
    {
        //if the zip file already exists and overwrite is false, return false
        if (file_exists($destination) && !$overwrite) {
            return false;
        }
        //vars
        $valid_files = array();
        //if files were passed in...
        if (is_array($files)) {
            //cycle through each file
            foreach ($files as $file) {
                //make sure the file exists
                if (file_exists($file)) {
                    $valid_files[] = $file;
                }
            }
        }
        //if we have good files...
        if (count($valid_files)) {
            //create the archive
            $zip = new ZipArchive();
            if ($zip->open($destination, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
                return false;
            }
            //add the files
            foreach ($valid_files as $file) {
                $zip->addFile($file, basename($file));
            }
            //debug
            //echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
            //close the zip -- done!

            $zip->close();

            //check to make sure the file exists
            return file_exists($destination);
        } else {
            return false;
        }
    }
    
    /**
 	* True if the version of Magento currently being run is Enterprise Edition
	*/
	public function isMageEnterprise() {
    	return Mage::getConfig ()->getModuleConfig ( 'Enterprise_Enterprise' ) && Mage::getConfig ()->getModuleConfig ( 'Enterprise_AdminGws' ) && Mage::getConfig ()->getModuleConfig ( 'Enterprise_Checkout' ) && Mage::getConfig ()->getModuleConfig ( 'Enterprise_Customer' );
	}

}
	 