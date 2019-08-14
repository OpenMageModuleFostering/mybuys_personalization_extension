<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_System_Config_EnableToggle
{
    public function toOptionArray()
    {
        return array(
            //array('value'=>'', 'label'=>''),
            array('value'=>'enabled', 'label'=>Mage::helper('adminhtml')->__('Enabled')),
            array('value'=>'disabled', 'label'=>Mage::helper('adminhtml')->__('Disabled')),
        );
    }
}
