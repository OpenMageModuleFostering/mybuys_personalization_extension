<?php
/*
 */
class Mybuys_Connector_Model_System_Config_LogOptions
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'partial', 'label'=>Mage::helper('adminhtml')->__('Send Last 5000 Lines (zipped)')),
            array('value'=>'link', 'label'=>Mage::helper('adminhtml')->__('Send Link to Log File')),
            //array('value'=>'', 'label'=>''),
            //array('value'=>'none', 'label'=>Mage::helper('adminhtml')->__('Do Not Send Log Data')),
            //array('value'=>'zipped', 'label'=>Mage::helper('adminhtml')->__('Send Entire Log File (zipped)')),
        );
    }
}
