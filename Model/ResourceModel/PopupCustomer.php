<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PopupCustomer extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('kiliba_connector_popup_customers', 'popup_customer_id');
    }

    /**
     * Check if email already registered for a popup type
     *
     * @param string $email
     * @param string $popupType
     * @return bool
     */
    public function isEmailRegistered($email, $popupType)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['popup_customer_id'])
            ->where('email = ?', $email)
            ->where('popup_type = ?', $popupType)
            ->limit(1);

        $result = $connection->fetchOne($select);
        return $result !== false;
    }

    /**
     * Get all registrations for an email and popup type
     *
     * @param string $email
     * @param string $popupType
     * @return array
     */
    public function getRegistrationsByEmailAndType($email, $popupType)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('email = ?', $email)
            ->where('popup_type = ?', $popupType);

        return $connection->fetchAll($select);
    }

}
