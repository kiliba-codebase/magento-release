<?php

namespace Kiliba\Connector\Setup;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface {

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $_productMetadata;

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    ) {
        $this->_productMetadata = $productMetadata;
    }

    public function install(
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    ) {
        $installer = $setup;
        $installer->startSetup();

        if(version_compare($this->_productMetadata->getVersion(), '2.3', '<')) {
            // kiliba_connector_log
            if(!$installer->tableExists('kiliba_connector_log')) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('kiliba_connector_log')
                )
                    ->addColumn(
                        'log_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'nullable' => false,
                            'primary'  => true,
                            'unsigned' => true,
                        ],
                        'Log ID'
                    )
                    ->addColumn(
                        'type',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['nullable' => false],
                        'Log type'
                    )
                    ->addColumn(
                        'process',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['nullable' => false],
                        'Log process'
                    )
                    ->addColumn(
                        'message',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        '64k',
                        ['nullable' => false],
                        'Log message'
                    )
                    ->addColumn(
                        'date',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                        null,
                        ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                        'Log date'
                    )
                    ->addColumn(
                        'store_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                        null,
                        ['nullable' => false,'unsigned' => true],
                        'Log store ID'
                    )
                    ->addForeignKey(
                        $installer->getFkName('kiliba_connector_log', 'store_id', 'store', 'store_id'),
                        'store_id',
                        $installer->getTable('store'),
                        'store_id',
                        \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
                    );
                    
                $installer->getConnection()->createTable($table);
            }
            
            // kiliba_connector_visit
            if(!$installer->tableExists('kiliba_connector_visit')) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('kiliba_connector_visit')
                )
                    ->addColumn(
                        'visit_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'nullable' => false,
                            'primary'  => true,
                            'unsigned' => true,
                        ],
                        'Visit ID'
                    )
                    ->addColumn(
                        'content',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        '64k',
                        ['nullable' => false],
                        'Log type'
                    )
                    ->addColumn(
                        'customer_key',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        null,
                        ['nullable' => true],
                        'Kiliba customer/guest key'
                    )
                    ->addColumn(
                        'store_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                        null,
                        ['nullable' => false,'unsigned' => true],
                        'Visit store'
                    )
                    ->addColumn(
                        'created_at',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                        null,
                        ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                        'Visit creation date'
                    )
                    ->addForeignKey(
                        $installer->getFkName('kiliba_connector_visit', 'store_id', 'store', 'store_id'),
                        'store_id',
                        $installer->getTable('store'),
                        'store_id',
                        \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
                    );
                    
                $installer->getConnection()->createTable($table);
            }
            
            // kiliba_connector_deleteditem
            if(!$installer->tableExists('kiliba_connector_deleteditem')) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('kiliba_connector_deleteditem')
                )
                    ->addColumn(
                        'deleteditem_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'nullable' => false,
                            'primary'  => true,
                            'unsigned' => true,
                        ],
                        'Delete item row ID'
                    )
                    ->addColumn(
                        'entity_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['nullable' => false],
                        'Deleted item entity ID'
                    )
                    ->addColumn(
                        'entity_type',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        30,
                        ['nullable' => true],
                        'Deleted item entity type'
                    )
                    ->addColumn(
                        'store_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                        null,
                        ['nullable' => false,'unsigned' => true],
                        'Deleted item store'
                    )
                    ->addColumn(
                        'created_at',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                        null,
                        ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                        'Deleted item creation date'
                    )
                    ->addForeignKey(
                        $installer->getFkName('kiliba_connector_deleteditem', 'store_id', 'store', 'store_id'),
                        'store_id',
                        $installer->getTable('store'),
                        'store_id',
                        \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
                    );
                    
                $installer->getConnection()->createTable($table);
            }

            // Update quote table
            $installer->getConnection()->addColumn(
                $installer->getTable('quote'),
                'kiliba_connector_customer_key',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Kiliba customer/guest key'
                ]
            );

            // kiliba_connector_popup_customers
            if(!$installer->tableExists('kiliba_connector_popup_customers')) {
                $table = $installer->getConnection()->newTable(
                    $installer->getTable('kiliba_connector_popup_customers')
                )
                    ->addColumn(
                        'popup_customer_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        [
                            'identity' => true,
                            'nullable' => false,
                            'primary'  => true,
                            'unsigned' => true,
                        ],
                        'Popup Customer ID'
                    )
                    ->addColumn(
                        'popup_type',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['nullable' => false],
                        'Type of popup'
                    )
                    ->addColumn(
                        'email',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        255,
                        ['nullable' => false],
                        'Customer email'
                    )
                    ->addColumn(
                        'subscribe',
                        \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                        1,
                        ['nullable' => false, 'unsigned' => false, 'default' => 0],
                        'Newsletter subscription flag'
                    )
                    ->addColumn(
                        'subscribe_ip',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        45,
                        ['nullable' => true],
                        'IP address if subscribed'
                    )
                    ->addColumn(
                        'website_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                        null,
                        ['nullable' => false,'unsigned' => true],
                        'Website ID'
                    )
                    ->addColumn(
                        'created_at',
                        \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                        null,
                        ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                        'Creation date'
                    )
                    ->addForeignKey(
                        $installer->getFkName('kiliba_connector_popup_customers', 'website_id', 'store_website', 'website_id'),
                        'website_id',
                        $installer->getTable('store_website'),
                        'website_id',
                        \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
                    )
                    ->addIndex(
                        $installer->getIdxName(
                            'kiliba_connector_popup_customers',
                            ['email', 'popup_type'],
                            \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX
                        ),
                        ['email', 'popup_type'],
                        ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_INDEX]
                    );
                    
                $installer->getConnection()->createTable($table);
            }
        }

        $installer->endSetup();
    }
}