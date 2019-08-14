<?php
/**
 * MyBuys Magento Connector
 *
 * @category    Mybuys
 * @package        Mybuys_Connector
 * @website    http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright    Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Adminhtml_Job_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId("jobGrid");
        $this->setDefaultSort('job_id');
        $this->setDefaultDir("DESC");
        $this->setSaveParametersInSession(true);
        if (Mage::registry('preparedFilter')) {
            $this->setDefaultFilter(Mage::registry('preparedFilter'));
        }
    }

    protected function _addColumnFilterToCollection($column)
    {
        $filterArr = Mage::registry('preparedFilter');
        if (($column->getId() === 'store_id' || $column->getId() === 'status') && $column->getFilter()->getValue() && strpos($column->getFilter()->getValue(), ',')) {
            $_inNin = explode(',', $column->getFilter()->getValue());
            $inNin = array();
            foreach ($_inNin as $k => $v) {
                if (is_string($v) && strlen(trim($v))) {
                    $inNin[] = trim($v);
                }
            }
            if (count($inNin) > 1 && in_array($inNin[0], array('in', 'nin'))) {
                $in = $inNin[0];
                $values = array_slice($inNin, 1);
                $this->getCollection()->addFieldToFilter($column->getId(), array($in => $values));
            } else {
                parent::_addColumnFilterToCollection($column);
            }
        } elseif (is_array($filterArr) && array_key_exists($column->getId(), $filterArr) && isset($filterArr[$column->getId()])) {
            $this->getCollection()->addFieldToFilter($column->getId(), $filterArr[$column->getId()]);
        } else {
            parent::_addColumnFilterToCollection($column);
        }
        return $this;
    }

    protected function _prepareCollection()
    {
        // Create collection
        $collection = Mage::getResourceModel('mybuys/job_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('job_id', array(
            'header' => Mage::helper('mybuys')->__('Job ID'),
            'width' => '50px',
            'index' => 'job_id'
        ));

        $this->addColumn('dependent_on_job_id', array(
            'header' => Mage::helper('mybuys')->__('Parent Job'),
            'width' => '50px',
            'index' => 'dependent_on_job_id'
        ));

        $this->addColumn('website_id', array(
            'header' => Mage::helper('mybuys')->__('Website ID'),
            'width' => '50px',
            'index' => 'website_id'
        ));

        $this->addColumn('type', array(
            'header' => Mage::helper('mybuys')->__('Job Type'),
            'width' => '100px',
            'index' => 'type',
            'type' => 'options',
            'options' => array(
                Mybuys_Connector_Model_Job::TYPE_GENERATE_BASELINE => Mage::helper('mybuys')->__('Baseline Feed'),
                Mybuys_Connector_Model_Job::TYPE_GENERATE_DAILY => Mage::helper('mybuys')->__('Daily Feed'),
                Mybuys_Connector_Model_Job::TYPE_TRANSFER => Mage::helper('mybuys')->__('SFTP Transfer'),
            ),
        ));

        $this->addColumn('feed_type', array(
            'header' => Mage::helper('mybuys')->__('Feed Type'),
            'width' => '100px',
            'index' => 'feed_type'
        ));

        $this->addColumn('scheduled_at', array(
            'header' => Mage::helper('mybuys')->__('Scheduled'),
            'type' => 'datetime',
            'width' => '160px',
            'index' => 'scheduled_at'
        ));

        $this->addColumn('started_at', array(
            'header' => Mage::helper('mybuys')->__('Started'),
            'type' => 'datetime',
            'width' => '160px',
            'index' => 'started_at'
        ));

        $this->addColumn('ended_at', array(
            'header' => Mage::helper('mybuys')->__('Completed'),
            'type' => 'datetime',
            'width' => '160px',
            'index' => 'ended_at'
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('mybuys')->__('Status'),
            'width' => '100px',
            'index' => 'status',
            'type' => 'options',
            'options' => array(
                Mybuys_Connector_Model_Job::STATUS_SCHEDULED => Mage::helper('mybuys')->__('Scheduled'),
                Mybuys_Connector_Model_Job::STATUS_RUNNING => Mage::helper('mybuys')->__('Running'),
                Mybuys_Connector_Model_Job::STATUS_COMPLETED => Mage::helper('mybuys')->__('Completed'),
                Mybuys_Connector_Model_Job::STATUS_ERROR => Mage::helper('mybuys')->__('Error'),
            ),
        ));

        $this->addColumn('min_entity_id', array(
            'header' => Mage::helper('mybuys')->__('Next Batch Start ID'),
            'width' => '100px',
            'index' => 'min_entity_id'
        ));

        $this->addColumn('error_message', array(
            'header' => Mage::helper('mybuys')->__('Error Message'),
            'index' => 'error_message'
        ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('job_id');
        $this->getMassactionBlock()->setFormFieldName('job_id');

        $this->getMassactionBlock()->addItem('delete', array(
            'label' => Mage::helper('mybuys')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('mybuys')->__('Delete selected job(s)?')
        ));

        $this->getMassactionBlock()->addItem('execute', array(
            'label' => Mage::helper('mybuys')->__('Run Job'),
            'url' => $this->getUrl('*/*/massRun'),
            'confirm' => Mage::helper('mybuys')->__('Run selected job(s)?  Note that running multiple and/or jobs may impact site performance.')
        ));

        return $this;
    }
}
