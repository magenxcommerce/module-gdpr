<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Block\Adminhtml\CookieGroup;

use Magento\Backend\Block\Widget\Form\Container;

class Edit extends Container
{
    protected $_objectId = 'id';
    protected $_blockGroup = 'Magenx_Gdpr';
    protected $_controller = 'adminhtml_cookiegroup';

    protected function _construct(): void
    {
        parent::_construct();
        $this->buttonList->remove('reset');

        if (!$this->getRequest()->getParam('id')) {
            $this->buttonList->remove('delete');
        }
    }

    public function getHeaderText(): \Magento\Framework\Phrase
    {
        return $this->getRequest()->getParam('id') ? __('Edit Cookie Group') : __('New Cookie Group');
    }
}
