<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Observer
{
    /**
     * Generate and send new datafeed files
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @return  Mybuys_Connector_Model_Observer
     */
    public function processDailyFeeds($schedule)
    {
		try {
            // Log
            Mage::log('**********************************************************', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Data feeds cron process started...', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Is Single Store Mode: ' . Mage::app()->isSingleStoreMode(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('**********************************************************', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
                        
            // Cleanup queue
            $this->cleanupJobQueue();

			// Log mem usage
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);

            // Schedule daily feed jobs for all websites
            $this->scheduleJobs();

			// Log mem usage
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);

            // Run 1 job
            $this->runJob();

            // Log
            Mage::log('**********************************************************', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Daily feeds cron process completed successfully.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('**********************************************************', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
		}
		catch(Exception $e) {
            // Log exception
            Mage::logException($e);
            Mage::log('**********************************************************', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Data feeds cron process failed with error:', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('**********************************************************', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}        
        
        return $this;
    }
    
    /**
     * Remove old entries from the job queue
     *
     *
     */
    protected function cleanupJobQueue()
    {
    	try {
    		Mybuys_Connector_Model_Job::cleanupJobQueue();
    	}
    	catch(Exception $e) {
            // Log exception
            Mage::logException($e);
            Mage::log('Failed to cleaup job queue, error:', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            throw $e;
    	}
    }
    
    /**
     * Schedule any daily feed jobs which are necessary when we hit the daily trigger time
     *
     *
     */
    protected function scheduleJobs()
    {
    	try {
    		Mybuys_Connector_Model_Job::scheduleAllDailyJobs();
    	}
    	catch(Exception $e) {
            // Log exception
            Mage::logException($e);
            Mage::log('Failed to schedule daily jobs, error:', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            throw $e;
    	}
    }
    
    /**
     * Grab the next job and run it, if it exists
     *
     *
     */
    protected function runJob()
    {
    	try {
    		$job = Mybuys_Connector_Model_Job::getNextJobFromQueue();
    		if($job !== false) {
    			$job->run();
    		}
    	}
    	catch(Exception $e) {
            // Log exception
            Mage::logException($e);
            Mage::log('Failed to run job, error:', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            throw $e;
    	}
    }
    
}
	 