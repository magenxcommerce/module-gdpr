<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\Cookie;

use Magento\Backend\App\Action;

class NewAction extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::cookies';

    public function execute()
    {
        return $this->_forward('edit');
    }
}
