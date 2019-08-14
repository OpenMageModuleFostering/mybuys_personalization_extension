<?php
/**
 * MyBuys Magento Connector
 *
 * @category	Mybuys
 * @package    	Mybuys_Connector
 * @website 	http://www.mybuys.com <http://www.mybuys.com/>
 * @copyright 	Copyright (C) 2009-2012 MyBuys, Inc. All Rights Reserved.
 */

// Includes
require_once('phpseclib/Net/SFTP.php');

class Mybuys_Connector_Helper_SftpConnection extends Mage_Core_Helper_Abstract
{
	/**
	 * Constants
	 *
	 * Hardcode MyBuys SFTP port and timeout value
	 */
	const SFTP_TIMEOUT      =       20;

	/**
	 * Connection handle
	 */
	private $_oConnection = null;

	/**
	 * Connect
	 * @return boolean
	 */
	public function connect($host, $port, $user, $pw)
	{

		try
		{
			// Close
			if (isset($this->_oConnection)) {
				$this->close();
			}

			// Get config values
			// -- Server
			$sServer = $host;
			$sServer = ($sServer ? trim($sServer) : '');
			// -- Server
			$sPort = $port;
			$sPort = ($sPort ? trim($sPort) : '');
			// -- Username
			$sUsername = $user;
			$sUsername = ($sUsername ? trim($sUsername) : '');
			// -- Password
			$sPassword = $pw;
			$sPassword = ($sPassword ? trim($sPassword) : '');

			// Check credentials
			if (!strlen($sServer)) {
                Mage::throwException('Invalid SFTP host: ' . $sServer);
			}
			if (!strlen($sPort) || !ctype_digit($sPort)) {
                Mage::throwException('Invalid SFTP port: ' . $sPort);
			}
			if (!strlen($sUsername)) {
                Mage::throwException('Invalid SFTP user: ' . $sUsername);
			}
			if (!strlen($sPassword)) {
                Mage::throwException('Invalid SFTP password: ' . $sPassword);
			}

			// -- Open connection
			$this->_oConnection = new Net_SFTP($sServer, $sPort, self::SFTP_TIMEOUT);
			if (!$this->_oConnection->login($sUsername, $sPassword)) {
                Mage::throwException(sprintf(__("Unable to open SFTP connection as %s@%s", $sUsername, $sServer)));
			}

			return true;
		}

		catch (Exception $e)
		{
			// Log
			Mage::logException($e);
			Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
            if ($this->_oConnection->getLastSFTPError())
                Mage::log("SFTP reported error is ". $this->_oConnection->getLastSFTPError(), Zend_Log::INFO, Mybuys_Connector_Helper_Data::LOG_FILE);
		}
		return false;
	}

	/**
	 * Close
	 * @return boolean
	 */
	public function close()
	{
		try
		{
			// Close connection
			if (isset($this->_oConnection)) {
				$bRes = $this->_oConnection->disconnect();
				unset($this->_oConnection);
				return $bRes;
			}
			else {
                Mage::throwException('Connection not open!');				
			}
		}
		catch (Exception $e)
		{
			// Log
			Mage::logException($e);
			Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}

		return false;
	}

	/**
	 * Is connected
	 * @return boolean
	 */
	public function isConnected()
	{
		return (isset($this->_oConnection));
	}

    /**
     * Change directory
     * @param string directory
     * @return boolean
     */
    public function changeDir($sDir)
    {
        try
        {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }

            // Get filename
            return $this->_oConnection->chdir($sDir);
        }
        catch (Exception $e)
        {
            // Log
            Mage::logException($e);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }

    /**
     * Make directory
     * @param string directory
     * @return boolean
     */
    public function makeDir($sDir)
    {
        try
        {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }

            // Get filename
            return $this->_oConnection->mkdir($sDir);
        }
        catch (Exception $e)
        {
            // Log
            Mage::logException($e);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }

    /**
     * List files
     * @param string directory
     * @return array
     */
    public function listFiles($sDir = '.')
    {
        try
        {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }

            // Get filename
            return $this->_oConnection->nlist($sDir);
        }
        catch (Exception $e)
        {
            // Log
            Mage::logException($e);
            Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }

	/**
	 * Transfer file
	 * @param string Local file path
	 * @return boolean
	 */
	public function putFile($sLocalFilePath)
	{
		try
		{
			// Close connection
			if (!$this->isConnected()) {
				return false;
			}

			// Get filename
			$sFilename = basename($sLocalFilePath);

			// Transfer
			$bSuccess = $this->_oConnection->put($sFilename, $sLocalFilePath, NET_SFTP_LOCAL_FILE);
			
			// Check success and log errors
			if(!$bSuccess) {
				Mage::log('SFTP Error: ' . $this->_oConnection->getLastSFTPError(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
			}
			
			// Return success
			return $bSuccess;
		}
		catch (Exception $e)
		{
			// Log
			Mage::logException($e);
			Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}

		return false;
	}

	/**
	 * Transfer file and delete when successful as one atomic operation
	 * @param string Local file path
	 * @return boolean
	 */
	public function putAndDeleteFile($sLocalFilePath)
	{
		try
		{
			$bSuccess = $this->putFile($sLocalFilePath);
			if($bSuccess) {
				$oIo = new Varien_Io_File();
				$oIo->rm($sLocalFilePath);
			}
			return $bSuccess;
		}
		catch (Exception $e)
		{
			// Log
			Mage::logException($e);
			Mage::log($e->getMessage(), Zend_Log::ERR, Mybuys_Connector_Helper_Data::LOG_FILE);
		}

		return false;
	}
}
