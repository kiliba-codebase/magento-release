<?php


namespace Kiliba\Connector\Model\ResourceModel;

class DeletedItem extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('kiliba_connector_deleteditem', 'deleteditem_id');
    }
}
