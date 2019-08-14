<?php   
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Zone extends Mage_Core_Block_Template
{   

    public function zonesEnabled() 
    {
        $webConfig = Mage::getStoreConfig('mybuys_websitecode/general/website_code');
        if($webConfig == 'enabled')
        {
            return true;
        }
        else { return false; }
    }

    public function getZoneStatus($page) 
    {
        $zoneConfig = Mage::getStoreConfig('mybuys_websitecode/recommendation');
        if ($zoneConfig[$page] == 'enabled')
        {
            return true;
        } 
        else { return false; } 
    }

}