<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Adminhtml_Export_Grid_Renderer_Action extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
    public function render(Varien_Object $row)
    {
        $this->getColumn()->setActions(array(array(
            'url'     => $this->getUrl('*/*/exportone', array('id' => $row->getId())),
            'caption' => Mage::helper('mybuys')->__('Export Baseline Feed For ' . $row->getWebsiteName()),
        )));
        return parent::render($row);
    }
}
