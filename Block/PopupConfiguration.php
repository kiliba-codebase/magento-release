<?php

namespace Kiliba\Connector\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Registry;

class PopupConfiguration extends Template
{
    /**
     * @var Resolver
     */
    protected $localeResolver;
    /**
     * @var Registry
     */
    protected $registry;

    public function __construct(
        Template\Context $context,
        Resolver $localeResolver,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->localeResolver = $localeResolver;
        $this->registry = $registry;
    }

    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * Expose current page category ids to the popup runtime so category targeting works on Magento too.
     *
     * - category page: current category id
     * - product page: product category ids
     */
    public function getCategoryIds()
    {
        $currentCategory = $this->registry->registry("current_category");
        if ($currentCategory && $currentCategory->getId()) {
            return [(string) $currentCategory->getId()];
        }

        $currentProduct = $this->registry->registry("current_product");
        if ($currentProduct && method_exists($currentProduct, "getCategoryIds")) {
            return array_values(array_filter(array_map("strval", (array) $currentProduct->getCategoryIds())));
        }

        return [];
    }
}
