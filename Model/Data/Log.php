<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Data;

use Kiliba\Connector\Api\Data\LogInterface;

class Log extends \Magento\Framework\Api\AbstractExtensibleObject implements LogInterface
{

    /**
     * Get log_id
     * @return string|null
     */
    public function getLogId()
    {
        return $this->_get(self::LOG_ID);
    }

    /**
     * Set log_id
     * @param string $logId
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setLogId($logId)
    {
        return $this->setData(self::LOG_ID, $logId);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Kiliba\Connector\Api\Data\LogExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     * @param \Kiliba\Connector\Api\Data\LogExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Kiliba\Connector\Api\Data\LogExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Get type
     * @return string|null
     */
    public function getType()
    {
        return $this->_get(self::TYPE);
    }

    /**
     * Set type
     * @param string $type
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setType($type)
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * Get process
     * @return string|null
     */
    public function getProcess()
    {
        return $this->_get(self::PROCESS);
    }

    /**
     * Set process
     * @param string $process
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setProcess($process)
    {
        return $this->setData(self::PROCESS, $process);
    }

    /**
     * Get message
     * @return string|null
     */
    public function getMessage()
    {
        return $this->_get(self::MESSAGE);
    }

    /**
     * Set message
     * @param string $message
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setMessage($message)
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * Get date
     * @return string|null
     */
    public function getDate()
    {
        return $this->_get(self::DATE);
    }

    /**
     * Set date
     * @param string $date
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setDate($date)
    {
        return $this->setData(self::DATE, $date);
    }

    /**
     * Get store_id
     * @return string|null
     */
    public function getStoreId()
    {
        return $this->_get(self::STORE_ID);
    }

    /**
     * Set store_id
     * @param string $storeId
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }
}
