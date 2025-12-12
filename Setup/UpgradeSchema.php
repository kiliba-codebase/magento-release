<?php
 
namespace Kiliba\Connector\Setup;
 
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
 
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $_productMetadata;

    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    ) {
        $this->_productMetadata = $productMetadata;
    }

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $setup->startSetup();
        if (version_compare($context->getVersion(), '2.2.0', '<') && version_compare($this->_productMetadata->getVersion(), '2.3', '<')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('quote'),
                'kiliba_connector_customer_key',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Kiliba customer/guest key'
                ]
            );

            $installer->getConnection()->addColumn(
                $installer->getTable('kiliba_connector_visit'),
                'customer_key',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'comment' => 'Kiliba customer/guest key'
                ]
            );
        }

        if (version_compare($context->getVersion(), '2.8.0', '<') && version_compare($this->_productMetadata->getVersion(), '2.3', '<')) {
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
        
        $setup->endSetup();
    }
}