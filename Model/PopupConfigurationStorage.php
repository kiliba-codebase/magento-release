<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model;

use Kiliba\Connector\Helper\ConfigHelper;
use Magento\Framework\App\ResourceConnection;

/**
 * Store complete popup documents outside core_config_data's 64 KiB TEXT value.
 */
class PopupConfigurationStorage
{
    const TABLE_NAME = 'kiliba_connector_popup_configuration';

    private $resourceConnection;
    private $configHelper;

    public function __construct(ResourceConnection $resourceConnection, ConfigHelper $configHelper)
    {
        $this->resourceConnection = $resourceConnection;
        $this->configHelper = $configHelper;
    }

    public function get($path, $websiteId)
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE_NAME);
        if ($connection->isTableExists($table)) {
            $value = $connection->fetchOne(
                $connection->select()->from($table, ['value'])
                    ->where('website_id = ?', (int)$websiteId)
                    ->where('config_path = ?', $path)
            );
            if ($value !== false) {
                return $value;
            }
        }

        // Existing installations retain their previous value until a verified sync.
        return $this->configHelper->getConfigWithoutCache($path, $websiteId);
    }

    public function set($path, $value, $websiteId)
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE_NAME);
        if (!$connection->isTableExists($table)) {
            return false;
        }

        $connection->beginTransaction();
        try {
            $connection->insertOnDuplicate($table, [
                'website_id' => (int)$websiteId,
                'config_path' => $path,
                'value' => $value,
            ], ['value']);

            $stored = $connection->fetchOne(
                $connection->select()->from($table, ['value'])
                    ->where('website_id = ?', (int)$websiteId)
                    ->where('config_path = ?', $path)
            );
            if ($stored !== $value) {
                $connection->rollBack();
                return false;
            }

            $connection->commit();
            return true;
        } catch (\Exception $e) {
            $connection->rollBack();
            return false;
        }
    }
}
