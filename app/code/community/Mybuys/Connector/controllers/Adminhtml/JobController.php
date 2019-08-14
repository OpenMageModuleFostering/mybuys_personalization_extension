<?php
/**
 * MyBuys Magento Connector
 *
 * @category    Mybuys
 * @package        Mybuys_Connector
 * @website    http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright    Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Adminhtml_JobController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {
        $this->loadLayout()->_setActiveMenu("mybuys/job");
        $this->renderLayout();
    }

    /**
     * Export all baseline data feeds
     */
    public function exportallAction()
    {
        // Validate configuration
        try {
            Mage::helper('mybuys')->validateFeedConfiguration();
        } catch (Exception $e) {
            // Display message
            $this->_getSession()->addError($this->__($e->getMessage()));

            // Redirect back to index
            $this->_redirect('*/*/index');

            return;
        }

        try {
            // Log
            Mage::log('Scheduling immediate baseline data feeds for all websites.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

            // Schedule all feeds for this site
            Mybuys_Connector_Model_Job::scheduleJobsAllWebsites(true);

            // Log
            Mage::log('Successfully scheduled feeds.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        } catch (Exception $e) {
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

    /**
     * Purge all outstanding jobs
     */

    public function purgeallAction()
    {

        try {
            // Get ID from request
            $id = $this->getRequest()->getParam('id');
            // Log
            Mage::log('Purging all jobs.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

            // Purge all job records
            Mage::getModel('mybuys/job')->purgeAllJobs();

            // Log
            Mage::log('Successfully purged all jobs.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        } catch (Exception $e) {
            // Log exception
            // Display message once job scheduled
            $this->_getSession()->addSuccess($this->__('Failed to clear job queue.'));
            Mage::logException($e);
            Mage::log('Failed to purge jobs.', Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
        }

        // Display message once job scheduled
        $this->_getSession()->addSuccess($this->__('Job queue has been cleared.'));

        // Redirect back to index
        $this->_redirect('*/*/index');
    }

    /**
     * Purge all outstanding jobs
     */

    public function massDeleteAction()
    {
        {
            $jobIds = $this->getRequest()->getParam('job_id');
            if (!is_array($jobIds)) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('mybuys')->__('Please select jobs(s) to delete.'));
            } else {
                try {
                    $jobModel = Mage::getModel('mybuys/job');
                    foreach ($jobIds as $jobId) {
                        Mage::log('Mass delete - Deleting job id ' . $jobId, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
                        // delete the job
                        $jobModel->load($jobId)->delete();
                    }
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                }
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('mybuys')->__(
                    'Total of %d record(s) were deleted.', count($jobIds)
                )
            );

            Mage::log('Mass delete - ' . count($jobIds) . ' jobs deleted.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

            $this->_redirect('*/*/index');
        }
    }

    public function massRunAction()
    {
        {
            $jobIds = $this->getRequest()->getParam('job_id');
            if (!is_array($jobIds)) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('mybuys')->__('Please select jobs(s) to execute.'));
            } else {
                try {
                    $jobModel = Mage::getModel('mybuys/job');
                    foreach ($jobIds as $jobId) {
                        Mage::log('Mass execute - Execute job id ' . $jobId, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

                        // run the job
                        $jobModel->load($jobId)
                            ->setStartedAt(Mage::getSingleton('core/date')->gmtDate())
                            ->save()
                            ->run();
                    }
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                }
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('mybuys')->__(
                    'Total of %d jobs(s) were executed.', count($jobIds)
                )
            );

            Mage::log('Mass execute - ' . count($jobIds) . ' jobs executed.', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

            $this->_redirect('*/*/index');
        }
    }

}
