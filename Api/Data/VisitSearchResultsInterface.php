<?php


namespace Kiliba\Connector\Api\Data;

interface VisitSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get Visit list.
     * @return \Kiliba\Connector\Api\Data\VisitInterface[]
     */
    public function getItems();

    /**
     * Set content list.
     * @param \Kiliba\Connector\Api\Data\VisitInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}