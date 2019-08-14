<?php   
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Block_Category extends Mage_Core_Block_Template
{   

    public function getProductsInCategory(int $catId)
    {
        $_category = Mage::getModel('catalog/category')->load($catId);
        $subs = $_category->getAllChildren(true);
        $result = array();
        foreach($subs as $cat_id) 
        {
            $category = new Mage_Catalog_Model_Category();
            $category->load($cat_id);
            $collection = $category->getProductCollection();
            foreach ($collection as $product) 
            {
                $result[] = $product->getId();
            }
        }
        return $result;
    }

    public function zoneEnabled() 
    {
        $zoneConfig = Mage::getStoreConfig('mybuys_websitecode/general/website_code');
        if($zoneConfig == 'enabled')
        {
            return true;
        }
        else { return false; }
    }

}