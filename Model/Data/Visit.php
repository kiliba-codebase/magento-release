<?php


namespace Kiliba\Connector\Model\Data;

use Kiliba\Connector\Api\Data\VisitInterface;

class Visit extends \Magento\Framework\Api\AbstractExtensibleObject implements VisitInterface
{

    /**
     * Get visit_id
     * @return string|null
     */
    public function getVisitId()
    {
        return $this->_get(self::VISIT_ID);
    }

    /**
     * Set visit_id
     * @param string $visitId
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     */
    public function setVisitId($visitId)
    {
        return $this->setData(self::VISIT_ID, $visitId);
    }

    /**
     * Get content
     * @return string|null
     */
    public function getContent()
    {
        return $this->_get(self::CONTENT);
    }

    /**
     * Set content
     * @param string $content
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     */
    public function setContent($content)
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * Get Kiliba Customer Key
     * @return string|null
     */
    public function getCustomerKey()
    {
        return $this->_get(self::CUSTOMER_KEY);
    }

    /**
     * Set Kiliba Customer Key
     * @param string $customerKey
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     */
    public function setCustomerKey($customerKey)
    {
        return $this->setData(self::CUSTOMER_KEY, $customerKey);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Kiliba\Connector\Api\Data\VisitExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     * @param \Kiliba\Connector\Api\Data\VisitExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Kiliba\Connector\Api\Data\VisitExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
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
     * @return \Kiliba\Connector\Api\Data\VisitInterface
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
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}