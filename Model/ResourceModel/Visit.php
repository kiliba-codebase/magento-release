<?php


namespace Kiliba\Connector\Model\ResourceModel;

class Visit extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('kiliba_connector_visit', 'visit_id');
    }
}