<?php

namespace Kiliba\Connector\Model\ResourceModel\DeletedItem;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * @var string
     */
    protected $_idFieldName = 'deleteditem_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Kiliba\Connector\Model\DeletedItem::class,
            \Kiliba\Connector\Model\ResourceModel\DeletedItem::class
        );
    }
}