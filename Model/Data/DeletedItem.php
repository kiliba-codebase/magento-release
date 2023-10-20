<?php


namespace Kiliba\Connector\Model\Data;

use Kiliba\Connector\Api\Data\DeletedItemInterface;

class DeletedItem extends \Magento\Framework\Api\AbstractExtensibleObject implements DeletedItemInterface
{

    /**
     * Get deleteditem_id
     * @return string|null
     */
    public function getDeleteditemId()
    {
        return $this->_get(self::DELETEDITEM_ID);
    }

    /**
     * Set deleteditem_id
     * @param string $deleteditemId
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setDeleteditemId($deleteditemId)
    {
        return $this->setData(self::DELETEDITEM_ID, $deleteditemId);
    }

    /**
     * Get entity_id
     * @return string|null
     */
    public function getEntityId()
    {
        return $this->_get(self::ENTITY_ID);
    }

    /**
     * Set entity_id
     * @param string $entityId
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Kiliba\Connector\Api\Data\DeletedItemExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     * @param \Kiliba\Connector\Api\Data\DeletedItemExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Kiliba\Connector\Api\Data\DeletedItemExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Get entity_type
     * @return string|null
     */
    public function getEntityType()
    {
        return $this->_get(self::ENTITY_TYPE);
    }

    /**
     * Set entity_type
     * @param string $entityType
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setEntityType($entityType)
    {
        return $this->setData(self::ENTITY_TYPE, $entityType);
    }

    /**
     * Get store_id
     * @return string|null
     */
    public function getStoreId()
    {
        return $this->_get(self::STORE_ID);
    }

    /**
     * Set store_id
     * @param string $storeId
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * Get created_at
     * @return string|null
     */
    public function getCreatedAt()
    {
        return $this->_get(self::CREATED_AT);
    }

    /**
     * Set created_at
     * @param string $createdAt
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
