<?php

namespace Kiliba\Connector\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Locale\Resolver;

class PopupConfiguration extends Template
{
    /**
     * @var Resolver
     */
    protected $localeResolver;

    public function __construct(
        Template\Context $context,
        Resolver $localeResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->localeResolver = $localeResolver;
    }

    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }
}