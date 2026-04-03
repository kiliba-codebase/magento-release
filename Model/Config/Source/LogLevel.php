<?php


namespace Kiliba\Connector\Model\Config\Source;

class LogLevel implements \Magento\Framework\Data\OptionSourceInterface
{

    const ALL_LOG = 0;
    const ONLY_ERROR = 1;
    const NO_LOG = 2;

    public function toOptionArray()
    {
        return [
            ['value' => self::ALL_LOG, 'label' =>  __("All logs")],
            ['value' => self::ONLY_ERROR, 'label' => __("Error only")],
            ['value' => self::NO_LOG, 'label' => __("No log at all")]
        ];
    }
}