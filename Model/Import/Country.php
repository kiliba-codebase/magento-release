<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;

class Country extends AbstractModel
{

    /**
     * @var CollectionFactory
     */
    protected $_countryCollection;

    /**
     * @var CountryFactory
     */
    protected $_countryFactory;

    protected $_coreTable = "directory_country";
    protected $_filterScope = false;

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CollectionFactory $countryCollection,
        CountryFactory $countryFactory
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
        $this->_countryCollection = $countryCollection;
        $this->_countryFactory = $countryFactory;
    }

    /**
     * @param int $entityId
     * @param int $websiteId
     * @return \Magento\Directory\Model\Country
     */
    public function getEntity($entityId)
    {
        return $this->_countryFactory->create()->loadByCode($entityId);
    }


    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $searchCriteria
            ->addFilter("website_id", $websiteId);

        return $this->_countryFactory->getList($searchCriteria->create())->getItems();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $countriesData = [];
        foreach ($collection as $country) {
            if ($country->getId()) {
                $countriesData[] = $this->formatData($country);
            }
        }

        return $countriesData;
    }

    public function getSyncCollection($websiteId, $limit, $offset, $createdAt = null, $updatedAt = null, $withData = true) {
        /* ON country no limit offet or filter needed*/

        $collection = $this->_countryCollection->create();

        if ($withData) {
            return $this->prepareDataForApi($collection, $websiteId);
        }

        $ids = [];
        foreach ($collection as $item) {
            $ids[] = $item->getId();
        }

        return $ids;
    }

    /**
     * @param \Magento\Directory\Model\Country $country
     * @return array
     */
    public function formatData($country)
    {
        $data = [
            "id_country" => (string) $country->getCountryId(),
            "iso_code" => (string) $country->getData("iso2_code"),
        ];
        return $data;
    }

    /**
     * @return false|string
     */
    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "Pays",
            "fields" => [
                [
                    "name" => "id_country",
                    "type" => "string"
                ],
                [
                    "name" => "iso_code",
                    "type" => "string"
                ]
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
