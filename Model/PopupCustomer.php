<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model;

use Magento\Framework\Model\AbstractModel;

class PopupCustomer extends AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Kiliba\Connector\Model\ResourceModel\PopupCustomer::class);
    }

    /**
     * Get popup customer ID
     *
     * @return int
     */
    public function getPopupCustomerId()
    {
        return $this->getData('popup_customer_id');
    }

    /**
     * Get popup type
     *
     * @return string
     */
    public function getPopupType()
    {
        return $this->getData('popup_type');
    }

    /**
     * Set popup type
     *
     * @param string $popupType
     * @return $this
     */
    public function setPopupType($popupType)
    {
        return $this->setData('popup_type', $popupType);
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->getData('email');
    }

    /**
     * Set email
     *
     * @param string $email
     * @return $this
     */
    public function setEmail($email)
    {
        return $this->setData('email', $email);
    }

    /**
     * Get subscribe flag
     *
     * @return bool
     */
    public function getSubscribe()
    {
        return (bool)$this->getData('subscribe');
    }

    /**
     * Set subscribe flag
     *
     * @param bool $subscribe
     * @return $this
     */
    public function setSubscribe($subscribe)
    {
        return $this->setData('subscribe', $subscribe ? 1 : 0);
    }

    /**
     * Get subscribe IP
     *
     * @return string|null
     */
    public function getSubscribeIp()
    {
        return $this->getData('subscribe_ip');
    }

    /**
     * Set subscribe IP
     *
     * @param string|null $ip
     * @return $this
     */
    public function setSubscribeIp($ip)
    {
        return $this->setData('subscribe_ip', $ip);
    }

    /**
     * Get website ID
     *
     * @return int
     */
    public function getWebsiteId()
    {
        return $this->getData('website_id');
    }

    /**
     * Set website ID
     *
     * @param int $websiteId
     * @return $this
     */
    public function setWebsiteId($websiteId)
    {
        return $this->setData('website_id', $websiteId);
    }

    /**
     * Get created at
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->getData('created_at');
    }
}
