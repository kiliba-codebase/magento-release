<?php


namespace Kiliba\Connector\Api\Data;

interface VisitInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{

    const VISIT_ID = 'visit_id';
    const CONTENT = 'content';
    const CUSTOMER_KEY = 'customer_key';
    const STORE_ID = 'store_id';
    const CREATED_AT = 'created_at';

    /**
     * Get visit_id
     * @return string|null
     */
    public function getVisitId();

    /**
     * Set visit_id
     * @param string $visitId
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     */
    public function setVisitId($visitId);

    /**
     * Get content
     * @return string|null
     */
    public function getContent();

    /**
     * Set content
     * @param string $content
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     */
    public function setContent($content);

    /**
     * Get Kiliba Customer Key
     * @return string|null
     */
    public function getCustomerKey();

    /**
     * Set Kiliba Customer Key
     * @param string $customerKey
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     */
    public function setCustomerKey($customerKey);

    /**
     * Get store_id
     * @return string|null
     */
    public function getStoreId();

    /**
     * Set store_id
     * @param string $storeId
     * @return \Kiliba\Connector\Api\Data\LogInterface
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
     * @return \Kiliba\Connector\Visit\Api\Data\VisitInterface
     */
    public function setCreatedAt($createdAt);

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Kiliba\Connector\Api\Data\VisitExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     * @param \Kiliba\Connector\Api\Data\VisitExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Kiliba\Connector\Api\Data\VisitExtensionInterface $extensionAttributes
    );
}
