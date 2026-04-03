<?php


namespace Kiliba\Connector\Api\Module;


interface DiscountInterface
{
    /**
     * @return string[]
     */
    public function createMagentoDiscountCode();

    /**
     * @return string[]
     */
    public function deleteMagentoDiscountCode();

    /**
     * @return string[]
     */
    public function purgeMagentoDiscountCode();
}