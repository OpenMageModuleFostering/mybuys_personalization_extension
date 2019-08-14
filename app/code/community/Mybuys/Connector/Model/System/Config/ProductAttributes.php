<?php
/*
 */
class Mybuys_Connector_Model_System_Config_ProductAttributes
{
    public function toOptionArray()
    {

        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            //->addAttributeToSelect('*')
            //->addAttributeToFilter('is_user_defined', 1)
            ->addVisibleFilter()
            ->addStoreLabel(Mage::app()->getStore()->getId())
            ->setOrder('main_table.attribute_id', 'asc')
            // not in
            ->load();
        $result = array();
        foreach ($attributes as $_attribute) {
            if ($_attribute->getIsUserDefined()) {
                $result[] = array(
                    'value' => $_attribute["attribute_code"],
                    'label' => $_attribute["frontend_label"]
                );
            }
        }
        return $result;
    }
}