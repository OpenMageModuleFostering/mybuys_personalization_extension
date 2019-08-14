<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Adminhtml_System_Config_CommentText extends Mage_Adminhtml_Block_System_Config_Form_Fieldset

{
   public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return $element->getComment();
    }
}
