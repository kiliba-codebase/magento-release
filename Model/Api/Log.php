<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Api;

use Kiliba\Connector\Api\Module\LogInterface;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;

class Log extends AbstractApiAction implements LogInterface
{

    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $websiteId = $requestCheck["websiteId"];

        $type = $this->_request->getParam("type") ?? null;
        $page = $this->_request->getParam("page") ?? null;

        return $this->_kilibaLogger->getStoreLog($websiteId, self::MAX_BATCH_DATA, $page, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function clearLogs()
    {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $date = $this->_request->getParam("date") ?? null;

        return array(
            'cleanOldVisit' => $this->_visitManager->cleanOldVisit(),
            'cleanDeletedItem' => $this->_deletedItemManager->cleanDeletedItem(),
            'removeOldLog' => $this->_kilibaLogger->removeOldLog(0, $date)
        );
    }
}
