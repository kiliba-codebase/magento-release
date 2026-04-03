<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Block\Adminhtml;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Info extends \Kiliba\Connector\Block\Adminhtml\AbstractConfigMessage
{

    protected function _getElementHtml(AbstractElement $element)
    {
        return '
            <div class="notices-wrapper">
                <div class="messages">
                    <div class="message">
                        <strong>' . __('To configure Kiliba synchronisation, please select a website') . '</strong>
                    </div>
                </div>
            </div>';
    }
}
