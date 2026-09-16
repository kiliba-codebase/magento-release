<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Api;

use Kiliba\Connector\Api\Module\EmailOpenTrackingInterface;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Model\Import\Visit;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer as CustomerEntity;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;

class EmailOpenTracking extends AbstractApiAction implements EmailOpenTrackingInterface
{
    const VALID_STATUSES = [
        "unknown",
        "legacy_not_opposed",
        "consented",
        "refused",
        "withdrawn",
    ];

    /** @var ResourceConnection */
    protected $_resourceConnection;

    /** @var CustomerRepositoryInterface */
    protected $_customerRepository;

    /** @var CustomerCollectionFactory */
    protected $_customerCollectionFactory;

    /** @var EavConfig */
    protected $_eavConfig;

    public function __construct(
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        ConfigHelper $configHelper,
        KilibaLogger $kilibaLogger,
        Visit $visitManager,
        DeletedItem $deletedItemManager,
        CustomerRepositoryInterface $customerRepository,
        CustomerCollectionFactory $customerCollectionFactory,
        EavConfig $eavConfig
    ) {
        parent::__construct(
            $request,
            $resourceConnection,
            $configHelper,
            $kilibaLogger,
            $visitManager,
            $deletedItemManager
        );
        $this->_resourceConnection = $resourceConnection;
        $this->_customerRepository = $customerRepository;
        $this->_customerCollectionFactory = $customerCollectionFactory;
        $this->_eavConfig = $eavConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerEmailOpenTracking()
    {
        $requestCheck = $this->_checkRequest();
        if (!$requestCheck["success"]) {
            return [$requestCheck];
        }

        $normalized = $this->normalizeUpdates($this->_request->getParams());
        if (isset($normalized["error"])) {
            return [[
                "success" => false,
                "code" => $normalized["error"],
                "message" => $normalized["error"] === "invalid_status"
                    ? "Invalid open tracking status."
                    : "Invalid updated_at value.",
            ]];
        }

        $updates = $normalized["updates"];
        if (empty($updates)) {
            return [[
                "success" => false,
                "code" => "missing_tracking_updates",
                "message" => "No open tracking field provided.",
            ]];
        }

        $websiteId = (int)$requestCheck["websiteId"];
        $providedCustomerId = (int)$this->_request->getParam("id_customer");
        $providedEmail = trim((string)$this->_request->getParam("email"));
        $customer = $this->resolveCustomer($providedCustomerId, $providedEmail, $websiteId);

        if ($providedCustomerId > 0 && $customer === null) {
            return [[
                "success" => false,
                "code" => "customer_not_found",
                "message" => "Customer not found for the active website.",
            ]];
        }

        $resolvedCustomerId = $customer === null ? null : (int)$customer->getId();
        $resolvedEmail = $providedEmail;
        if ($customer !== null) {
            $customerEmail = trim((string)$customer->getEmail());
            if ($providedEmail !== "" && strcasecmp($providedEmail, $customerEmail) !== 0) {
                return [[
                    "success" => false,
                    "code" => "customer_email_mismatch",
                    "message" => "Customer ID and email do not identify the same customer.",
                    "resolved_customer_id" => $resolvedCustomerId,
                ]];
            }
            $resolvedEmail = $customerEmail;
        }

        try {
            $customerResult = $this->updateCustomer($customer, $updates, $websiteId);
            $newsletterResult = $this->updateNewsletter(
                $resolvedEmail,
                $resolvedCustomerId,
                $updates,
                $websiteId
            );
        } catch (\Exception $exception) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Update customer email open tracking",
                $exception->getMessage(),
                $websiteId
            );

            return [[
                "success" => false,
                "code" => "email_open_tracking_update_failed",
                "message" => "Unable to update email open tracking fields.",
            ]];
        }

        if (!$customerResult["updated"] && !$newsletterResult["updated"]) {
            return [[
                "success" => false,
                "code" => "missing_tracking_target",
                "message" => "No compatible customer attribute or newsletter field could be updated.",
                "resolved_customer_id" => $resolvedCustomerId,
                "resolved_email" => $resolvedEmail === "" ? null : $resolvedEmail,
            ]];
        }

        return [[
            "success" => true,
            "resolved_customer_id" => $resolvedCustomerId,
            "resolved_email" => $resolvedEmail === "" ? null : $resolvedEmail,
            "updated_customer_fields" => $customerResult["field_names"],
            "updated_newsletter_fields" => $newsletterResult["field_names"],
            "updated_newsletter_rows" => $newsletterResult["rows"],
        ]];
    }

    /**
     * Validate and normalize values before resolving any storage target.
     *
     * @param mixed[] $params
     * @return mixed[]
     */
    protected function normalizeUpdates(array $params)
    {
        $updates = [];

        if (array_key_exists("status", $params)) {
            $status = strtolower(trim((string)$params["status"]));
            if ($status === "") {
                $updates["pixel_tracking_status"] = null;
            } elseif (in_array($status, self::VALID_STATUSES, true)) {
                $updates["pixel_tracking_status"] = $status;
            } else {
                return ["error" => "invalid_status"];
            }
        }

        if (array_key_exists("updated_at", $params)) {
            $updatedAt = trim((string)$params["updated_at"]);
            if ($updatedAt === "") {
                $updates["pixel_tracking_updated_at"] = null;
            } else {
                try {
                    $date = new \DateTime($updatedAt);
                } catch (\Exception $exception) {
                    return ["error" => "invalid_updated_at"];
                }
                $date->setTimezone(new \DateTimeZone("UTC"));
                $updates["pixel_tracking_updated_at"] = $date->format("Y-m-d\\TH:i:s\\Z");
            }
        }

        if (array_key_exists("source", $params)) {
            $updates["pixel_tracking_source"] = $this->normalizeNullableString($params["source"]);
        }

        if (array_key_exists("policy_version", $params)) {
            $updates["pixel_tracking_policy_version"] = $this->normalizeNullableString($params["policy_version"]);
        }

        return ["updates" => $updates];
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    protected function normalizeNullableString($value)
    {
        $normalized = trim((string)$value);
        return $normalized === "" ? null : $normalized;
    }

    /**
     * An explicit ID never falls back to an email match.
     *
     * @param int $customerId
     * @param string $email
     * @param int $websiteId
     * @return \Magento\Customer\Api\Data\CustomerInterface|null
     */
    protected function resolveCustomer($customerId, $email, $websiteId)
    {
        if ($customerId > 0) {
            try {
                $customer = $this->_customerRepository->getById($customerId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
                return null;
            }

            return (int)$customer->getWebsiteId() === $websiteId ? $customer : null;
        }

        if ($email === "") {
            return null;
        }

        $collection = $this->_customerCollectionFactory->create();
        $collection->addAttributeToFilter("website_id", $websiteId);
        $collection->addAttributeToFilter("email", $email);
        $collection->addAttributeToSort("entity_id", "DESC");
        $collection->setPageSize(1);
        $matchedCustomer = $collection->getFirstItem();
        if (!$matchedCustomer->getId()) {
            return null;
        }

        return $this->_customerRepository->getById($matchedCustomer->getId());
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface|null $customer
     * @param mixed[] $updates
     * @param int $websiteId
     * @return mixed[]
     */
    protected function updateCustomer($customer, array $updates, $websiteId)
    {
        if ($customer === null) {
            return ["updated" => false, "field_names" => []];
        }

        $configuredFields = $this->_configHelper->getCustomerPixelTrackingFields($websiteId);
        $fieldNames = [];
        foreach ($updates as $payloadField => $value) {
            $attributeCode = isset($configuredFields[$payloadField]) ? $configuredFields[$payloadField] : null;
            if ($attributeCode === null) {
                continue;
            }

            $attribute = $this->_eavConfig->getAttribute(CustomerEntity::ENTITY, $attributeCode);
            if (!$attribute->getId()) {
                continue;
            }

            $customer->setCustomAttribute(
                $attributeCode,
                $this->normalizeAttributeValue($payloadField, $value, $attribute->getBackendType())
            );
            $fieldNames[] = $attributeCode;
        }

        if (empty($fieldNames)) {
            return ["updated" => false, "field_names" => []];
        }

        // Repository save updates customer_entity.updated_at, which is the incremental pull marker.
        $this->_customerRepository->save($customer);
        return ["updated" => true, "field_names" => array_values(array_unique($fieldNames))];
    }

    /**
     * @param string $payloadField
     * @param string|null $value
     * @param string $backendType
     * @return string|null
     */
    protected function normalizeAttributeValue($payloadField, $value, $backendType)
    {
        if ($payloadField !== "pixel_tracking_updated_at" || $value === null) {
            return $value;
        }

        if ($backendType === "datetime") {
            return gmdate("Y-m-d H:i:s", strtotime($value));
        }

        return $value;
    }

    /**
     * Update standard optional columns when a merchant extended newsletter_subscriber.
     *
     * @param string $email
     * @param int|null $customerId
     * @param mixed[] $updates
     * @param int $websiteId
     * @return mixed[]
     */
    protected function updateNewsletter($email, $customerId, array $updates, $websiteId)
    {
        if ($email === "") {
            return ["updated" => false, "rows" => 0, "field_names" => []];
        }

        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName("newsletter_subscriber");
        $columns = $connection->describeTable($table);
        $newsletterUpdates = [];
        $fieldNames = [];

        foreach ($updates as $field => $value) {
            if (!isset($columns[$field])) {
                continue;
            }
            $newsletterUpdates[$field] = $this->normalizeColumnValue($field, $value, $columns[$field]);
            $fieldNames[] = $field;
        }

        if (empty($newsletterUpdates)) {
            return ["updated" => false, "rows" => 0, "field_names" => []];
        }

        // Guest pulls use change_status_at for incremental synchronization.
        if (isset($columns["change_status_at"])) {
            $newsletterUpdates["change_status_at"] = gmdate("Y-m-d H:i:s");
        }

        $storeIds = array_values($this->_configHelper->getWebsiteById($websiteId)->getStoreIds());
        $where = [$connection->quoteInto("subscriber_email = ?", $email)];
        if (!empty($storeIds) && isset($columns["store_id"])) {
            $where[] = $connection->quoteInto("store_id IN (?)", $storeIds);
        }
        if ($customerId !== null && isset($columns["customer_id"])) {
            $where[] = $connection->quoteInto("(customer_id = ? OR customer_id = 0)", $customerId);
        }

        $rows = (int)$connection->update($table, $newsletterUpdates, $where);
        $matched = $rows > 0;
        if (!$matched) {
            $select = $connection->select()->from($table, ["subscriber_id"])->where(implode(" AND ", $where))->limit(1);
            $matched = (bool)$connection->fetchOne($select);
        }

        return [
            "updated" => $matched,
            "rows" => $rows,
            "field_names" => $matched ? array_values(array_unique($fieldNames)) : [],
        ];
    }

    /**
     * @param string $field
     * @param string|null $value
     * @param mixed[] $column
     * @return string|null
     */
    protected function normalizeColumnValue($field, $value, array $column)
    {
        if ($field !== "pixel_tracking_updated_at" || $value === null) {
            return $value;
        }

        $dataType = isset($column["DATA_TYPE"]) ? strtolower($column["DATA_TYPE"]) : "";
        if ($dataType === "datetime" || $dataType === "timestamp") {
            return gmdate("Y-m-d H:i:s", strtotime($value));
        }
        if ($dataType === "date") {
            return gmdate("Y-m-d", strtotime($value));
        }

        return $value;
    }
}
