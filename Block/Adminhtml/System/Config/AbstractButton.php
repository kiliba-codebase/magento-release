<?php


namespace Kiliba\Connector\Block\Adminhtml\System\Config;

use Magento\Store\Model\ScopeInterface;

class AbstractButton extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var string
     */
    protected $_template = 'Kiliba_Connector::system/config/button.phtml';

    /**
     * @var string
     */
    protected $_url;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    protected $_label;

    protected $_model = "abstract";

    /**
     * Remove scope label
     *
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for collect button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        $websiteId = null;
        if ($this->getForm()->getScope() == ScopeInterface::SCOPE_STORES) {
            $websiteId = $this->getForm()->getScopeId();
        }

        return $this->getUrl($this->_url, ["model" => $this->_model, "website_id" => $websiteId]);
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => $this->id,
                'label' => __($this->_label)
            ]
        );
        return $button->toHtml();
    }
}
