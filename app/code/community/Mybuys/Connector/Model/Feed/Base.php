<?php
/**
 * MyBuys Magento Connector
 *
 * @category    Mybuys
 * @package        Mybuys_Connector
 * @website    http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright    Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Feed_Base extends Mage_Core_Model_Abstract
{

    protected $_optionValueMap=array();

    protected $_attrSetIdToName=array();

    protected function _initAttributeSets($storeId=0)
    {
        $optionValueTable = Mage::getSingleton('core/resource')->getTableName('eav/attribute_option_value');
        $sql="select option_id, value from $optionValueTable where store_id=$storeId";
        $attributeValues = Mage::getSingleton('core/resource')
            ->getConnection('default_read')
            ->fetchAll($sql);

        //create an array to map attribute option id's into option values
        foreach ($attributeValues as $values) {
            $this->_attrSetIdToName[$values['option_id']] = $values['value'];
        }

        return $this;
    }

    // Field mapping - Magento attributes to MyBuys Feed Fields
    // This field must be populated by derived classes
    public function getFieldMap()
    {
        return array();
    }

    // Feed file name key
    // This must be overridde by derived classes
    public function getFileNameKey()
    {
        return '';
    }

    /**
     * Build collection object used to generate feed
     *
     * @param int|string $websiteId Id of the website for which to generate feed
     * @return Varien_Data_Collection_Db Collection of data to spit out into feed file
     */
    public function getFeedCollection($websiteId)
    {
        return null;
    }

    /**
     * Add filter to collection to make it only include records necessary for automatic daily feed (instead of one-time baseline feed).
     *
     * @param Varien_Data_Collection_Db $collection Collection of data which will be spit out as feed
     */
    protected function addIncrementalFilter($collection, $incrementalDate=null)
    {
        return null;
    }

    /**
     * Add throttle parameter to collection to limit output to X number of rows
     *
     * @param Varien_Data_Collection_Db $collection Collection of data which will be spit out as feed
     * @param int|string $throttle Number representing maximum record count which should be included in this feed generation run
     * @param int|string $minEntityId Number representing minimum value for entity Id to export - This acts as a placeholder for where the feed export left off
     */
    protected function addThrottleFilter($collection, $throttle, $minEntityId)
    {
        return null;
    }

    /**
     * Add custom attributes selected by magento admin to query
     *
     * @param Varien_Data_Collection_Db $collection Collection of data which will be spit out as feed
     * @param string $customAttribs Comma separated list of attribute codes
     * @param array $fieldMap Reference to fieldmap where attribute codes should also be added
     */
    protected function addCustomAttributes($collection, $customAttribs, &$fieldMap)
    {
        // Log
        Mage::log("Adding custom attributes include in query: {$customAttribs}", Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        // Check if we have any custom attribs
        if (strlen(trim($customAttribs)) > 0) {
            // Iterate custom attribs
            foreach (explode(',', $customAttribs) as $curAttrib) {
                 // Trim attribute code
                 $curAttrib = trim($curAttrib);
                 // Check if attribute exists

                 $_attribute=$collection->getAttribute($curAttrib);
                 if ($_attribute === false) {
                     // Attribute not found
                     Mage::throwException("Attribte not found: {$curAttrib}");
                 }
                 // Log
                 Mage::log("Adding attribute to query: {$curAttrib}", Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);

                 if ($_attribute->getFrontendInput()=="select" || $_attribute->getFrontendInput()=="multiselect") {
                         // attribute is a select of multi-select input and attribute id to value translation is needed
                         // Log
                     Mage::log("Note - Attribute needs translation", Zend_Log::DEBUG, Mybuys_Connector_Helper_Data::LOG_FILE);
                     $this->_optionValueMap['custom_' . $curAttrib]=true;
                 }
                 // Add attribute to select
                 $collection
                     ->addExpressionAttributeToSelect('custom_' . $curAttrib, "{{" . $curAttrib . "}}", $curAttrib)
                     ->addAttributeToSelect($curAttrib);
                 // Add attribute to map
                 $fieldMap['custom_' . $curAttrib] = 'custom_' . $curAttrib;
             }
        }

        // Return the original collection object
        return $collection;
    }

    /**
     * Generate one feed for this website and store feed file at the specified path
     *
     * @param int|string $websiteId Id of the website for which to generate data feed file
     * @param string $exportPath Path to the folder where data feed files should be stored
     * @param bool $bBaselineFile Should this file be a baseline file or an automated daily file
     * @param int|string $throttle Number representing maximum record count which should be included in this feed generation run, 0 = unlimited throttle
     * @param int|string $minEntityId Number representing minimum value for entity Id to export - This acts as a placeholder for where the feed export left off
     * @param bool $bDone Indicates when the feed generation is done
     */
    public function generate($websiteId, $exportPath, $bBaselineFile, $throttle, &$minEntityId, &$bDone)
    {
        // Log
        Mage::log('Generating ' . $this->getFileNameKey() . ' data feed for website with Id: ' . $websiteId, Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        Mage::log("Export path: {$exportPath}", Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        Mage::log("Baseline feed: {$bBaselineFile}", Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        Mage::log("Throttle: {$throttle}", Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        Mage::log("Min entity_id: {$minEntityId}", Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

        // initialize done flag
        $bDone = false;

        //
        // Build output file name
        //

        // Lookup website name
        $websitecode = Mage::app()->getWebsite($websiteId)->getCode();

        // Build date for incremental query
        $incrementalDate = date("Y-m-d", time() - 60 * 60 * 24);

        // Check if baseline or incremental feed
        if (!$bBaselineFile) {
            // Generating automated feed file, use full file name
            $filename = $exportPath . DS . $this->getFileNameKey() . '-websiteid-' . $websiteId . '-' . $websitecode . '-' . $incrementalDate . '.tsv';
        } else {
            // Generating baseline file, use simple file name
            $filename = $exportPath . DS . $this->getFileNameKey() . '-websiteid-' . $websiteId . '-' . $websitecode . '-' . 'baseline.tsv';
        }
        Mage::log("Output Filenane: {$filename}", Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);

        // build collection
        $collection = $this->getFeedCollection($websiteId);

        // Add throttle filter when configured
        if ($throttle > 0) {
            $collection = $this->addThrottleFilter($collection, $throttle, $minEntityId);
        }

        // Add incremental / automatic daily feed filter
        if (!$bBaselineFile) {
            $collection = $this->addIncrementalFilter($collection, $incrementalDate);
        }

        //Mage::log(ucfirst($this->getFileNameKey()) . " feed query", Zend_Log::INFO, "mybuys_query.log");
        //Mage::log($collection->getSelect()->__toString(), Zend_Log::INFO, "mybuys_query.log");

        // Get column headers from field map provided by derived class
        $headerColumns = array_values($this->getFieldMap());

        // Create / open output file
        $file = Mage::helper('mybuys/tsvfile');
        /* @var $file Mybuys_Connector_Helper_Tsvfile */
        // Open file, either create new one or open existing depending on $throttle and $minEntityId
        if ($throttle > 0 && $minEntityId > 0) {
            // open existing file
            $bSuccess = $file->reopen($filename, $headerColumns);
        } else {
            // otherwise, start new file with headers
            $bSuccess = $file->open($filename, $headerColumns);
        }

        // Check success opening file
        if (!$bSuccess) {
            Mage::log('Failed to open data feed file:' . $filename, Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            return false;
        }


        //get all the attribute values for this website
        Mage::log('Initializing attribute values', Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
        $this->_initAttributeSets();

        // Iterate data rows and output to file
        foreach ($collection as $curRow) {
            // Build row output array
            // Put data into assoc array by column headers
            $curRowData = $curRow->getData();
            $rowValues = array();
            
            foreach ($this->getFieldMap() as $mapKey => $mapValue) {
                //if the attribute is a select or multiselect then we need to translate the 
                // option id value into the display value
                if ($this->_optionValueMap[$mapKey]) {
                    $items = explode(",",$curRowData[$mapKey]);
                    $attrList=array();
                    foreach ($items as $item) {
                        $attrList[]= $this->_attrSetIdToName[$item];
                    }
                    $rowValues[$mapValue] = implode(",", $attrList);
                } else {
                    $rowValues[$mapValue] = $curRowData[$mapKey];
                }
            }

            // Output this row
            $bSuccess = $file->writeRow($rowValues);
            if (!$bSuccess) {
                Mage::log('Failed to write to data feed file: ' . $filename, Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
                $file->close();
                return false;
            }

            // Collect last entity Id and generate new minEntityId param
            $minEntityId = $curRow->getEntityId() + 1;
        }

        // Check if we're done this feed export
        // Check a few different conditions to determine if we're done
        if ($throttle == 0 || count($collection) == 0 || count($collection) < $throttle) {
            $bDone = true;
        }

        // Close file
        $bSuccess = $file->close();
        if (!$bSuccess) {
            Mage::log('Failed to close data feed file: ' . $filename, Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            return false;
        }
    }

}
	 