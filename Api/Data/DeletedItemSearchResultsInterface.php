<?php


namespace Kiliba\Connector\Api\Data;

interface DeletedItemSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get DeletedItem list.
     * @return \Kiliba\Connector\Api\Data\DeletedItemInterface[]
     */
    public function getItems();

    /**
     * Set entity_id list.
     * @param \Kiliba\Connector\Api\Data\DeletedItemInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
