<?php


namespace Kiliba\Connector\Block\Adminhtml;

use Kiliba\Connector\Helper\ConfigHelper;
use Magento\Backend\Block\Template\Context;

if (class_exists(\Magento\Framework\View\Helper\SecureHtmlRenderer::class)) { // case CE
    class AbstractConfigMessage extends \Magento\Config\Block\System\Config\Form\Field
    {
        /**
         * @var ConfigHelper
         */
        protected $_configHelper;

        public function __construct(
            Context $context,
            ConfigHelper $configHelper,
            array $data = [],
            ?\Magento\Framework\View\Helper\SecureHtmlRenderer $secureRenderer = null
        ) {
            parent::__construct($context, $data, $secureRenderer);
            $this->_configHelper = $configHelper;
        }
    }
} else { // case EE
    class AbstractConfigMessage extends \Magento\Config\Block\System\Config\Form\Field
    {
        /**
         * @var ConfigHelper
         */
        protected $_configHelper;

        public function __construct(
            Context $context,
            ConfigHelper $configHelper,
            array $data = []
        ) {
            parent::__construct($context, $data);
            $this->_configHelper = $configHelper;
        }
    }
}