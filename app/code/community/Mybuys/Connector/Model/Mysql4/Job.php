<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

class Mybuys_Connector_Model_Mysql4_Job extends Mage_Core_Model_Mysql4_Abstract
{
	/**
	 * Constructor
	 */
	public function _construct()
	{
		$this->_init('mybuys/job', 'job_id');
	}
}
 
