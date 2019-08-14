<?php
/**
 * MyBuys Magento Connector
 *
 * @category    Mybuys
 * @package        Mybuys_Connector
 * @website    http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright    Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Job extends Mage_Core_Model_Abstract
{
    /**
     * Job Types
     */
    const TYPE_GENERATE_BASELINE = 1;
    const TYPE_GENERATE_DAILY = 2;
    const TYPE_TRANSFER = 3;

    /**
     * Feed Types
     */
    const FEED_TYPE_CATEGORY = 'category';
    const FEED_TYPE_PRODUCT = 'product';
    const FEED_TYPE_SKU = 'sku';
    const FEED_TYPE_TRANSACTION = 'transaction';
    const FEED_TYPE_CROSSSELL = 'crosssell';
    const FEED_TYPE_RATING = 'rating';
    const FEED_TYPE_EMAIL_OPTIN = 'email_optin';
    const FEED_TYPE_EMAIL_OPTOUT = 'email_optout';

    /**
     * Statuses
     */
    const STATUS_SCHEDULED = 1;
    const STATUS_RUNNING = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_ERROR = 4;


    /**
     * Constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('mybuys/job');
    }

    /**
     * Pull the next job to run from the queue and set status to running
     *
     * @returns Mybuys_Connector_Model_Job The next job object
     */
    public static function getNextJobFromQueue()
    {
        // Log
        Mage::log('Getting next job from the queue.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

        // Create collection
        $collection = Mage::getResourceModel('mybuys/job_collection');

        // Retrieve our table name
        $table = $collection->getTable('mybuys/job');

        // DB Query to grab next job
        $collection->getSelect()
            ->where('status = ' . Mybuys_Connector_Model_Job::STATUS_SCHEDULED . ' or status = ' . Mybuys_Connector_Model_Job::STATUS_RUNNING)
            ->where(Mybuys_Connector_Model_Job::STATUS_SCHEDULED . " not in (select status from {$table} mbj2 where mbj2.job_id = main_table.dependent_on_job_id) ")
            ->order('job_id')
            ->limit(1);

        // Get next job and mark it as running
        foreach ($collection as $job) {
            // Log
            Mage::log('Found job id: ' . $job->getJobId(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            // Set status and return job
            $job->setStatus(Mybuys_Connector_Model_Job::STATUS_RUNNING);
            $job->setStartedAt(Mage::getSingleton('core/date')->gmtDate());
            $job->save();
            return $job;
        }

        // Log
        Mage::log('No jobs found.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        // Otherwise return false
        return false;

    }

    /**
     * Create a new job object
     *
     */
    public static function createJob($dependentOnJobId, $websiteId, $type, $feedType)
    {
        // Log
        Mage::log('Scheduling new job.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        // Create new job object and init fields
        $newJob = Mage::getModel('mybuys/job');
        $newJob->setDependentOnJobId($dependentOnJobId);
        $newJob->setMinEntityId(0);
        $newJob->setWebsiteId($websiteId);
        $newJob->setType($type);
        $newJob->setFeedType($feedType);
        $newJob->setScheduledAt(Mage::getSingleton('core/date')->gmtDate());
        $newJob->setStatus(Mybuys_Connector_Model_Job::STATUS_SCHEDULED);
        $newJob->save();
        return $newJob;
    }

    /**
     * Cleanup the job queue, removing all jobs that were created more than max age days ago
     *
     */
    public static function cleanupJobQueue()
    {
        // Log
        Mage::log('Cleaning up the job queue.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

        // Iterate websites and check configuration
        $websites = Mage::app()->getWebsites(false, true);
        foreach ($websites as $website) {
            // Save website id
            $websiteId = $website->getId();

            // Lookup age days config settings
            $maxAgeDays = Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/advanced/max_job_age');

            // log
            Mage::log('Max job age (for website id: ' . $websiteId . ') in days: ' . $maxAgeDays, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

            // Retrieve the write connection
            $resource = Mage::getSingleton('core/resource');
            $writeConnection = $resource->getConnection('core_write');

            // Retrieve our table name
            $table = $resource->getTableName('mybuys/job');

            // Build query
            $query = "DELETE FROM {$table} WHERE date(scheduled_at) < date_sub(curdate(), interval {$maxAgeDays} DAY) AND website_id = {$websiteId}";

            // Execute the query
            $writeConnection->query($query);
        }
    }

    /**
     * Purge all scheduled jobs
     *
     */
    public static function purgeAllJobs()
    {
        // Iterate websites and check configuration
        $websites = Mage::app()->getWebsites(false, true);
        foreach ($websites as $website) {
            // Save website id
            $websiteId = $website->getId();

            // Retrieve the write connection
            $resource = Mage::getSingleton('core/resource');
            $writeConnection = $resource->getConnection('core_write');

            // Retrieve our table name
            $table = $resource->getTableName('mybuys/job');

            // Build query
            $query = "DELETE FROM {$table} WHERE website_id = {$websiteId}";

            // Execute the query
            $writeConnection->query($query);
        }
    }

    /**
     * Schedule all the necessary daily jobs for today, only do once per day based on configure schedule
     *
     */
    public static function scheduleAllDailyJobs()
    {
        // Iterate websites and check configuration
        $websites = Mage::app()->getWebsites(false, true);
        foreach ($websites as $website) {
            if (self::_checkTime($website->getId())) {
                // Schedule daily jobs for this website
                Mybuys_Connector_Model_Job::scheduleJobs($website->getId(), false);
            }
        }
    }

    private static function _checkTime($websiteId)
    {
        // Lookup configured time
        $configTime = trim(Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/schedule/dailyfeedtime'));
        // Log
        Mage::log('Daily feed time configuration (for website id ' . $websiteId . '): "' . $configTime . '"', Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);

        // Lookup configured timezone
        $timezone = Mage::app()->getWebsite($websiteId)->getConfig('general/locale/timezone');
        // Get current time
        $curDateTime = new DateTime("now", new DateTimeZone($timezone));
        // Log
        Mage::log('Current time: "' . $curDateTime->format('H:i:s') . '" timezone: "' . $curDateTime->getTimezone()->getName() . '"', Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);

        // Create time object from configured time
        $configDateTime = new DateTime("now", new DateTimeZone($timezone));
        $configDateTime->setTime(
            intval(substr($configTime, 0, 2)),
            intval(substr($configTime, 3, 2)),
            intval(substr($configTime, 6, 2)));
        // Log
        Mage::log('Configured time: "' . $configDateTime->format('H:i:s') . '" timezone: "' . $configDateTime->getTimezone()->getName() . '"', Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);

        // Do time diff
        $minutes = floor(($curDateTime->format('U') - $configDateTime->format('U')) / 60);

        // Interpret result
        if ($minutes >= -4 && $minutes <= 4) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Schedule all daily or baseline jobs for all websites to run immediately
     */

    public static function scheduleJobsAllWebsites($bBaselineFile)
    {
        // Iterate websites and check configuration
        $websites = Mage::app()->getWebsites(false, true);
        foreach ($websites as $website) {
            // Save website id
            $websiteId = $website->getId();

            // Schedule jobs for this website
            Mybuys_Connector_Model_Job::scheduleJobs($websiteId, $bBaselineFile);
        }

    }

    /**
     * Schedule baseline or incremental daily jobs to run immediately
     *
     *
     */
    public static function scheduleJobs($websiteId, $bBaselineFile)
    {
        // Log
        Mage::log('Scheduling jobs for website: ' . $websiteId, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        Mage::log('All feeds for website set to: ' . Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/general/allfeedsenabled'), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

        // Local to hold last job id
        $lastJobId = null;

        // Check if data feeds enabled
        if (Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/general/allfeedsenabled') != 'enabled') {
            return;
        }

        // Create generate jobs for all enabled feeds
        foreach (Mybuys_Connector_Model_Generatefeeds::getFeedTypes() as $curType) {
            // Create feed job of this type if config is enabled
            if (Mage::app()->getWebsite($websiteId)->getConfig('mybuys_datafeeds/feedsenabled/' . $curType) == 'enabled') {
                // Check baseline or daily
                $jobType = 0;
                if ($bBaselineFile) {
                    $jobType = Mybuys_Connector_Model_Job::TYPE_GENERATE_BASELINE;
                } else {
                    $jobType = Mybuys_Connector_Model_Job::TYPE_GENERATE_DAILY;
                }

                // Create feed job
                $job = Mybuys_Connector_Model_Job::createJob($lastJobId, $websiteId, $jobType, $curType);
                $job->save();
                $lastJobId = $job->getJobId();
            }
        }

        // Create transfer feeds job for this website
        $job = Mybuys_Connector_Model_Job::createJob($lastJobId, $websiteId, Mybuys_Connector_Model_Job::TYPE_TRANSFER, NULL);
        $job->save();
    }

    /**
     * Run job
     *
     */
    public function run()
    {
        try {
            // Log
            Mage::log('Running job: ' . $this->getJobId(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Website Id: ' . $this->getWebsiteId(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Dependent On Job Id: ' . $this->getDependentOnJobId(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Min Entity Id: ' . $this->getMinEntityId(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Type: ' . $this->getType(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Feed Type: ' . $this->getFeedType(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);

            // Execute the job
            $this->executeJob();

            // Log
            Mage::log('Job completed successfully.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
        } catch (Exception $e) {
            // Fail this job
            $this->setStatus(Mybuys_Connector_Model_Job::STATUS_ERROR);
            $this->setEndedAt(Mage::getSingleton('core/date')->gmtDate());
            $this->setErrorMessage($e->getMessage());
            $this->save();
            // Log exception
            Mage::logException($e);
            Mage::log('Job failed with error:', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log('Memory usage: ' . memory_get_usage(), Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
            // Send error email
            Mage::helper('mybuys')->sendErrorEmail($this->getWebsiteId(), $e->getMessage());
        }

        return $this;
    }

    /**
     * Execute this job
     *
     */
    protected function executeJob()
    {
        // Check data feeds enabled
        if (Mage::app()->getWebsite($this->getWebsiteId())->getConfig('mybuys_datafeeds/general/allfeedsenabled') != 'enabled') {
            Mage::throwException('Data feeds not enabled for website: ' . $this->getWebsiteId());
        }

        // Done flag
        $bDone = false;

        // Switch on job type
        switch ($this->getType()) {
            case Mybuys_Connector_Model_Job::TYPE_GENERATE_BASELINE:
                // Call out to Mybuys_Connector_Model_Generatefeeds
                // Send false param to generate incremental (automatic daily) data feeds
                $genModel = Mage::getModel('mybuys/generatefeeds');
                $minEntityId = $this->getMinEntityId();
                $genModel->generateForWebsite($this->getWebsiteId(), true, $this->getFeedType(), $minEntityId, $bDone);
                $this->setMinEntityId($minEntityId);
                break;
            case Mybuys_Connector_Model_Job::TYPE_GENERATE_DAILY:
                // Call out to Mybuys_Connector_Model_Generatefeeds
                // Send false param to generate incremental (automatic daily) data feeds
                $genModel = Mage::getModel('mybuys/generatefeeds');
                $minEntityId = $this->getMinEntityId();
                $genModel->generateForWebsite($this->getWebsiteId(), false, $this->getFeedType(), $minEntityId, $bDone);
                $this->setMinEntityId($minEntityId);
                break;
            case Mybuys_Connector_Model_Job::TYPE_TRANSFER:
                // Call out to Mybuys_Connector_Model_Transferfeeds
                $tranModel = Mage::getModel('mybuys/transferfeeds');
                $tranModel->transfer($this->getWebsiteId());
                $bDone = true;
                break;
        }

        // Mark job as succeeded
        if ($bDone) {
            $this->setStatus(Mybuys_Connector_Model_Job::STATUS_COMPLETED);
            $this->setEndedAt(Mage::getSingleton('core/date')->gmtDate());
        }
        // Save done status & or new min entity_id
        $this->save();

    }
}
