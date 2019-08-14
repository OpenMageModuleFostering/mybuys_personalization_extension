<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Adminhtml_ExportController extends Mage_Adminhtml_Controller_Action
{

	public function indexAction()
	{
		$this->loadLayout()->_setActiveMenu("mybuys/export");
		$this->renderLayout();
	}

	/**
	* Export baseline data feed
	*/
	public function exportoneAction()
	{
    	// Validate configuration
    	try {
			// Get ID from request
			$id = $this->getRequest()->getParam('id');
    		Mage::helper('mybuys')->validateFeedConfiguration($id);
    	}
    	catch(Exception $e) {
			// Display message 
        	$this->_getSession()->addError($this->__($e->getMessage()));

			// Redirect back to index
			$this->_redirect('*/*/index');

    		return;
    	}
    	
		try {
			// Get ID from request
			$id = $this->getRequest()->getParam('id');
			// Log
			Mage::log('Scheduling immediate baseline data feeds for website Id: ' . $id, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
			
			// Schedule all feeds for this site
			Mybuys_Connector_Model_Job::scheduleJobs($id, true);

			// Log
			Mage::log('Successfully scheduled feeds.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
		}
		catch(Exception $e) {
			// Log exception
			Mage::logException($e);
			Mage::log('Failed to schedule feeds.', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
			Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}

		// Display message once job scheduled
        $this->_getSession()->addSuccess($this->__('Baseline feed generation and transfer has been scheduled for website ID ' . $id . '.'));

		// Redirect back to index
		$this->_redirect('*/*/index');
	}

    /**
     * Export all baseline data feeds
     */
    public function exportallAction()
    {
    	// Validate configuration
    	try {
    		Mage::helper('mybuys')->validateFeedConfiguration();
    	}
    	catch(Exception $e) {
			// Display message 
        	$this->_getSession()->addError($this->__($e->getMessage()));

			// Redirect back to index
			$this->_redirect('*/*/index');

    		return;
    	}
    	
		try {
			// Get ID from request
			$id = $this->getRequest()->getParam('id');
			// Log
			Mage::log('Scheduling immediate baseline data feeds for all websites.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
			
			// Schedule all feeds for this site
			Mybuys_Connector_Model_Job::scheduleJobsAllWebsites(true);

			// Log
			Mage::log('Successfully scheduled feeds.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
		}
		catch(Exception $e) {
			// Log exception
			Mage::logException($e);
			Mage::log('Failed to schedule feeds.', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
			Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}

		// Display message once job scheduled
        $this->_getSession()->addSuccess($this->__('Baseline feed generation and transfer has been scheduled all websites.'));

		// Redirect back to index
		$this->_redirect('*/*/index');
	}	

}
