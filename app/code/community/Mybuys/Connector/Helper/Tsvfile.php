<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Helper_Tsvfile extends Mage_Core_Helper_Abstract
{

	/**
	 * Variables
	 */
	private $_filename;
	private $_handle;
	private $_path;
	private $_errorMessage;
	private $_columnHeaders;


	public function __construct()
	{
		$this->_filename = null;
		$this->_handle = null;
		$this->_path = null;
		$this->_errorMessage = null;
	}

	/**
	 * Open file
	 * @param array  $columnHeaders An array of column header names, one for each column
	 * @param string $filename fully qualified filename + path. (directory must be writable)
	 * @return boolean
	 */
	public function open($filename, array $columnHeaders)
	{
		$this->_columnHeaders = $columnHeaders;
		$this->_filename = $filename;

		try
		{
			// Open file, truncate for writing
			$this->_handle = fopen($this->_filename, 'w');
			// Build header row string with delimiters & termination
			$rowString = implode("\t", $this->encodeFields($columnHeaders)) . "\r\n";
			// Write row to file, including delimiters and termination
	   		$result = fwrite($this->_handle, $rowString);
		}
		catch (Exception $e) {
			Mage::logException($e);
			return false;
		}

		return true;
	}

	/**
	 * Re Open existing file
	 * @param string $filename fully qualified filename + path. (directory must be writable)
	 * @return boolean
	 */
	public function reopen($filename, array $columnHeaders)
	{
		$this->_columnHeaders = $columnHeaders;
		$this->_filename = $filename;

		try
		{
			// Reopen file, append for writing
			$this->_handle = fopen($this->_filename, 'a');
		}
		catch (Exception $e) {
			Mage::logException($e);
			return false;
		}

		return true;
	}

	/**
	 * Close file
	 */
	public function close()
	{
		try
		{
			fclose($this->_handle);
		}
		catch (Exception $e) {
			Mage::logException($e);
			return false;
		}

		return true;
	}

	/**
	 * Write row to file
	 *
	 * @param array $rowValues An associative array of columns => values, cells for columns not included in this row are left empty
	 * @return boolean
	 */
	public function writeRow(array $rowValues)
	{
		try
		{
			// Filter and order rowValues based on column headers
			$selectedRowValues = array();
			foreach($this->_columnHeaders as $columnHeader) {
				$selectedRowValues[] = $rowValues[$columnHeader];
			}
		
			// Convert values to utf8
			$convertedRowValues = $this->encodeFields($selectedRowValues);
			// Build row string with delimiters & termination
			$rowString = implode("\t", $convertedRowValues) . "\r\n";
			// Write row to file, including delimiters and termination
	   		$result = fwrite($this->_handle, $rowString);
	   		// Check result
	   		if($result != strlen($rowString)) {
	   			return false;
	   		}
		}
		catch (Exception $e) {
			Mage::logException($e);
			return false;
		}

		return true;
	}

	/**
	 * Convert strings in array to Utf8 and encode for CSV file usage
	 * @param array $values
	 * @return array $converted
	 */
	private function encodeFields(array $values)
	{
		$converted = array();
		foreach($values as $value)
		{
			// Encode in utf8
			$newVal = utf8_encode($value);
			// Encode delimters inside field
			$newVal = str_replace('"', '""', $newVal);
			// Add delimiter
			$newVal = '"' . $newVal . '"';
			// Add to converted array
			array_push($converted,$newVal);
		}

		return $converted;
	}
}
