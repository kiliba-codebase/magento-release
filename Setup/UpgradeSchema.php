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
                    'identity' => false,
                    'comment' => 'Kiliba customer/guest key'
                ]
            );

            $installer->getConnection()->addColumn(
                $installer->getTable('kiliba_connector_visit'),
                'customer_key',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'nullable' => true,
                    'identity' => false,
                    'comment' => 'Kiliba customer/guest key'
                ]
            );
        }
        
        $setup->endSetup();
    }
}