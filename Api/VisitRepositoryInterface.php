<?php


namespace Kiliba\Connector\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface VisitRepositoryInterface
{

    /**
     * Save Visit
     * @param \Kiliba\Connector\Api\Data\VisitInterface $visit
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Kiliba\Connector\Api\Data\VisitInterface $visit
    );

    /**
     * Retrieve Visit
     * @param string $visitId
     * @return \Kiliba\Connector\Api\Data\VisitInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($visitId);

    /**
     * Retrieve Visit matching the specified criteria.
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Kiliba\Connector\Api\Data\VisitSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete Visit
     * @param \Kiliba\Connector\Api\Data\VisitInterface $visit
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Kiliba\Connector\Api\Data\VisitInterface $visit
    );

    /**
     * Delete Visit by ID
     * @param string $visitId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($visitId);
}
