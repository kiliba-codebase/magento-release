<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Block\Adminhtml;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Warning extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        return '
		<div class="notices-wrapper">
		    <div class="messages">
		        <div class="message">
		            <strong>' . __('You may not see the change in this area without clearing config cache') . '</strong>
		        </div>
		    </div>
		</div>';
    }
}
