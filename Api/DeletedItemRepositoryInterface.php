<?php


namespace Kiliba\Connector\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface DeletedItemRepositoryInterface
{

    /**
     * Save DeletedItem
     * @param \Kiliba\Connector\Api\Data\DeletedItemInterface $deletedItem
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Kiliba\Connector\Api\Data\DeletedItemInterface $deletedItem
    );

    /**
     * Retrieve DeletedItem
     * @param string $deleteditemId
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($deleteditemId);

    /**
     * Retrieve DeletedItem matching the specified criteria.
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Kiliba\Connector\Api\Data\DeletedItemSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete DeletedItem
     * @param \Kiliba\Connector\Api\Data\DeletedItemInterface $deletedItem
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Kiliba\Connector\Api\Data\DeletedItemInterface $deletedItem
    );

    /**
     * Delete DeletedItem by ID
     * @param string $deleteditemId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($deleteditemId);
}