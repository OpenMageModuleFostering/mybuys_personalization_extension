<?php
/**
 * StoreFront Consulting MyBuys Magento Module
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @author     	StoreFront Consulting, Inc.
 * @website	 	http://www.storefrontconsulting.com
 * @copyright   Copyright © 2009-2012 StoreFront Consulting, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Adminhtml_Job extends Mage_Adminhtml_Block_Widget_Grid_Container
{

	public function __construct()
	{

		$this->_controller = "adminhtml_job";
		$this->_blockGroup = "mybuys";
		$this->_headerText = Mage::helper("mybuys")->__("MyBuys Data Feeds Job Queue");
		parent::__construct();

	}

    protected function _prepareLayout()
    {
    	// Remove add button
    	$this->_removeButton('add');
    	
    	// Add refesh button
        $this->_addButton('refresh', array(
            'label'     => Mage::helper("mybuys")->__("Refresh"),
            'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/index') . '\')',
            'class'     => 'refresh',
        ));
    	
    	// Add export all button
        $this->_addButton('exportall', array(
            'label'     => Mage::helper("mybuys")->__("Export Baseline Feeds For All Sites"),
            'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/exportall') . '\')',
            'class'     => 'exportall',
        ));
    	
    	// Add purge all jobs button
        $this->_addButton('deleteall', array(
            'label'     => Mage::helper("mybuys")->__("Purge Job Queue"),
            'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/purgeall') . '\')',
            'class'     => 'purgeall',
        ));

    	// Call parent
        return parent::_prepareLayout();
    }


}
