<?php
/*
 */
class Mybuys_Connector_Model_System_Validate_ProductAttributes extends Mage_Core_Model_Config_Data
{
    const ATTRIBUTE_LIMIT = 20;

    public function save()
    {
        $limit = Mage::app()->getWebsite()->getConfig('mybuys_datafeeds/advanced/attribute_limit');
        if (!$limit) {
            $limit = self::ATTRIBUTE_LIMIT ;
        }

        $selections = $this->getValue(); //get the value from our config

        if(sizeof($selections) > $limit)  // more than 15 items selected
        {
            Mage::getSingleton('core/session')->addWarning(
                Mage::helper('mybuys')->__(
                "WARNING - Too many Product Custom Attributes selected for the product data feed.<br/>Only the first %s selected attributes were saved and applied to the feed.", $limit));
        }

        $this->setValue(array_slice($selections,0,$limit,true));  //only keep the first 15 items the user selected
        return parent::save();
    }
}
