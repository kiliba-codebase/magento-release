<?php


namespace Kiliba\Connector\Api\Data;

interface DeletedItemInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{

    const ENTITY_TYPE = 'entity_type';
    const STORE_ID = 'store_id';
    const DELETEDITEM_ID = 'deleteditem_id';
    const CREATED_AT = 'created_at';
    const ENTITY_ID = 'entity_id';

    /**
     * Get deleteditem_id
     * @return string|null
     */
    public function getDeleteditemId();

    /**
     * Set deleteditem_id
     * @param string $deleteditemId
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setDeleteditemId($deleteditemId);

    /**
     * Get entity_id
     * @return string|null
     */
    public function getEntityId();

    /**
     * Set entity_id
     * @param string $entityId
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setEntityId($entityId);

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Kiliba\Connector\Api\Data\DeletedItemExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     * @param \Kiliba\Connector\Api\Data\DeletedItemExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Kiliba\Connector\Api\Data\DeletedItemExtensionInterface $extensionAttributes
    );

    /**
     * Get entity_type
     * @return string|null
     */
    public function getEntityType();

    /**
     * Set entity_type
     * @param string $entityType
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setEntityType($entityType);

    /**
     * Get store_id
     * @return string|null
     */
    public function getStoreId();

    /**
     * Set store_id
     * @param string $storeId
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setStoreId($storeId);

    /**
     * Get created_at
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set created_at
     * @param string $createdAt
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     */
    public function setCreatedAt($createdAt);
}
