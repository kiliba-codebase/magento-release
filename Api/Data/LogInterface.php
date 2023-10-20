<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Data;

interface LogInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{

    const LOG_ID = 'log_id';
    const MESSAGE = 'message';
    const STORE_ID = 'store_id';
    const PROCESS = 'process';
    const TYPE = 'type';
    const DATE = 'date';

    /**
     * Get log_id
     * @return string|null
     */
    public function getLogId();

    /**
     * Set log_id
     * @param string $logId
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setLogId($logId);

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Kiliba\Connector\Api\Data\LogExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     * @param \Kiliba\Connector\Api\Data\LogExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Kiliba\Connector\Api\Data\LogExtensionInterface $extensionAttributes
    );

    /**
     * Get type
     * @return string|null
     */
    public function getType();

    /**
     * Set type
     * @param string $type
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setType($type);

    /**
     * Get process
     * @return string|null
     */
    public function getProcess();

    /**
     * Set process
     * @param string $process
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setProcess($process);

    /**
     * Get message
     * @return string|null
     */
    public function getMessage();

    /**
     * Set message
     * @param string $message
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setMessage($message);

    /**
     * Get date
     * @return string|null
     */
    public function getDate();

    /**
     * Set date
     * @param string $date
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setDate($date);

    /**
     * Get store_id
     * @return string|null
     */
    public function getStoreId();

    /**
     * Set store_id
     * @param string $storeId
     * @return \Kiliba\Connector\Api\Data\LogInterface
     */
    public function setStoreId($storeId);
}
