<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

$installer = $this;
$installer->startSetup();

// Exports
$installer->run("
	DROP TABLE IF EXISTS `mybuys_job`;

	CREATE TABLE `mybuys_job` (
	  `job_id` int unsigned NOT NULL AUTO_INCREMENT,
	  `website_id` smallint(5) unsigned NOT NULL,
	  `dependent_on_job_id` int unsigned,	  
	  `min_entity_id` int unsigned,	  
	  `type` varchar(40) NOT NULL,
	  `feed_type` varchar(40) NOT NULL,
	  `status` int(11) NOT NULL,
	  `scheduled_at` datetime DEFAULT NULL,
	  `started_at` datetime DEFAULT NULL,
	  `ended_at` datetime DEFAULT NULL,
	  `error_message` varchar(64) NOT NULL,	  
	  PRIMARY KEY (`job_id`),
	  INDEX `indx_export_type` (`type`),
	  INDEX `indx_export_entity` (`feed_type`),
	  INDEX `indx_export_status` (`status`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();
	 