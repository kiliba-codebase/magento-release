<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Newsletter\Model\SubscriberFactory;

class Customer extends AbstractModel
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $_customerRepository;

    /**
     * @var SubscriberFactory
     */
    protected $_subscriberFactory;

    /**
     * @var CollectionFactory
     */
    protected $_customerCollectionFactory;

    protected $_coreTable = "customer_entity";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CustomerRepositoryInterface $customerRepository,
        SubscriberFactory $subscriberFactory,
        CollectionFactory $customerCollectionFactory
    ) {
        parent::__construct(
            $configHelper,
            $formatterHelper,
            $kilibaCaller,
            $kilibaLogger,
            $serializer,
            $searchCriteriaBuilder,
            $resourceConnection
        );
        $this->_customerRepository = $customerRepository;
        $this->_subscriberFactory = $subscriberFactory;
        $this->_customerCollectionFactory = $customerCollectionFactory;
    }

    /**
     * @param int $entityId
     * @param int $websiteId
     * @return \Magento\Customer\Api\Data\CustomerInterface
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId,$websiteId)
    {
        return $this->_customerRepository->getById($entityId,false,$websiteId);
    }
    
    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $searchCriteria
            ->addFilter("website_id", $websiteId);

        return $this->_customerRepository->getList($searchCriteria->create())->getItems();
    }

    public function getTotalCount($websiteId) {
        return $this->_customerCollectionFactory->create()
            ->addAttributeToFilter('website_id', $websiteId)
            ->getSize();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $customersData = [];
        try {
            foreach ($collection as $customer) {
                if ($customer->getId()) {
                    $data = $this->formatData($customer, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $customersData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data customer";
            if (isset($customer)) {
                $message .= " customer id " . $customer->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $customersData;
    }


    /**
     * @param \Magento\Customer\Model\Customer|\Magento\Customer\Api\Data\CustomerInterface $customer
     * @param int $websiteId
     * @return array
     */
    public function formatData($customer, $websiteId)
    {
        try {
            $checkSubscriber = $this->_subscriberFactory->create()->load($customer->getId(), "customer_id");
            $customerAddresses = $customer->getAddresses();

            $addressData = [];
            if (!empty($customerAddresses)) {
                foreach ($customerAddresses as $address) {
                    $addressData[] = $this->_formatCustomerAddress($address);
                }
            }

            $data = [
                "email" => (string)$customer->getEmail(),
                "id_magento" => (string)$customer->getId(),
                "id_shop_group" => (string)$customer->getWebsiteId(),
                "id_shop" => (string)$customer->getStoreId(),
                "firstname" => (string)$customer->getFirstName(),
                "lastname" => (string)$customer->getLastName(),
                "birthday" => (string)$customer->getDob(),
                "newsletter" => $this->_formatBoolean($checkSubscriber->isSubscribed()),
                "id_gender" => (string)$customer->getGender(),
                "id_lang" =>(string)$this->_configHelper->getStoreLocale($customer->getStoreId()),
                "optin" => "", // TODO : field to add ?
                "date_add" => (string)$customer->getCreatedAt(),
                "date_update" => (string)$customer->getUpdatedAt(),
                "deleted" => $this->_formatBoolean(false), // always false when use this function
                "id_default_group" => (string)$customer->getGroupId(),
                "id_groups" => (string)$customer->getGroupId(), // no several group in magento
                "addresses" => $addressData,
            ];
            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format customer data, id = " . $customer->getId(),
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * @param int $customerId
     * @param int $websiteId
     * @return array
     */
    public function formatDeletedCustomer($customerId, $websiteId)
    {
        $data = [
            "email" => "",
            "id_magento" => (string) $customerId,
            "id_shop_group" => "",
            "id_shop" => "",
            "firstname" => "",
            "lastname" => "",
            "birthday" => "",
            "newsletter" => "",
            "id_gender" => "",
            "id_lang" => "",
            "optin" => "",
            "date_add" => "",
            "date_update" => "",
            "deleted" => $this->_formatBoolean(true), // always true when use this function
            "id_default_group" => "",
            "id_groups" => "",
            "addresses" => [],
        ];

        return $data;
    }

    /**
     * @param \Magento\Customer\Api\Data\AddressInterface $address
     * @return array
     */
    protected function _formatCustomerAddress($address)
    {
        $street = $address->getStreet();
        if (!is_array($street)) {
            $street1 = $street;
            $street2 = "";
        } else {
            if (isset($street[0])) {
                $street1 = $street[0];
                unset($street[0]);
            }
            $street2 = implode(" ", $street);
        }

        return [
            "id_address" => (string) $address->getId(),
            "id_country" => (string) $address->getCountryId(),
            "id_state" => (string) $address->getRegionId(),
            "address1" => (string) $street1,
            "address2" => (string) $street2,
            "postcode" => (string) $address->getPostcode(),
            "city" => (string) $address->getCity(),
            "phone" => (string) $address->getTelephone(),
            "active" => "",
            "deleted" => $this->_formatBoolean(false), // always false when use this function
            //"date_add" => $address->getCreatedAt(),
            //"date_updated" => $address->getUpdatedAt(),
        ];
    }

    /**
     * @return false|string
     */
    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "Customer",
            "fields" => [
                [
                    "name" => "email",
                    "type" => "string"
                ],
                [
                    "name" => "id_magento",
                    "type" => "string"
                ],
                [
                    "name" => "id_shop_group",
                    "type" => "string"
                ],
                [
                    "name" => "id_shop",
                    "type" => "string"
                ],
                [
                    "name" => "firstname",
                    "type" => "string"
                ],
                [
                    "name" => "lastname",
                    "type" => "string"
                ],
                [
                    "name" => "birthday",
                    "type" => "string"
                ],
                [
                    "name" => "newsletter",
                    "type" => "string"
                ],
                [
                    "name" => "id_gender",
                    "type" => "string"
                ],
                [
                    "name" => "id_lang",
                    "type" => "string"
                ],
                [
                    "name" => "optin",
                    "type" => "string"
                ],
                [
                    "name" => "date_add",
                    "type" => "string"
                ],
                [
                    "name" => "date_update",
                    "type" => "string"
                ],
                [
                    "name" => "deleted",
                    "type" => "string"
                ],
                [
                    "name" => "id_default_group",
                    "type" => "string"
                ],
                [
                    "name" => "id_groups",
                    "type" => "string"
                ],
                [
                    "name" => "addresses",
                    "type" => [
                        "type" => "array",
                        "items" => [
                            "name" => "Address",
                            "type" => "record",
                            "fields" => [
                                [
                                    "name" => "id_address",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "id_country",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "id_state",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "address1",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "address2",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "postcode",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "city",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "phone",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "active",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "deleted",
                                    "type" => "string"
                                ],/*
                                [
                                    "name" => "date_add",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "date_updated",
                                    "type" => "string"
                                ],*/
                            ]
                        ]
                    ]
                ],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
