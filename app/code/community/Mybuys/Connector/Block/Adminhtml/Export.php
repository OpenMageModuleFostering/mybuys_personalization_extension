<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Adminhtml_Export extends Mage_Adminhtml_Block_Widget_Grid_Container
{

	public function __construct()
	{

		$this->_controller = "adminhtml_export";
		$this->_blockGroup = "mybuys";
		$this->_headerText = Mage::helper("mybuys")->__("Generate MyBuys Baseline Data Feeds");
		parent::__construct();

	}

    protected function _prepareLayout()
    {
    	// Remove add button
    	$this->_removeButton('add');
    	
    	// Add export all button
        $this->_addButton('exportall', array(
            'label'     => 'Export Baseline Feeds For All Sites',
            'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/exportall') . '\')',
            'class'     => 'exportall',
        ));
    	
    	// Call parent
        return parent::_prepareLayout();
    }


}
