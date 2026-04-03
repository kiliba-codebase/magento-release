<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Block\Adminhtml;

use Magento\Framework\Data\Form\Element\AbstractElement;

class ReadonlyField extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setDisabled('disabled');
        return $element->getElementHtml();
    }
}
