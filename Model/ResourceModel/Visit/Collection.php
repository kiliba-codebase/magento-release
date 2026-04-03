<?php

namespace Kiliba\Connector\Model\ResourceModel\Visit;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'visit_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \Kiliba\Connector\Model\Visit::class,
            \Kiliba\Connector\Model\ResourceModel\Visit::class
        );
    }
}