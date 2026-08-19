<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\CookieGroup;

use Magento\Backend\App\Action;

class NewAction extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::cookie_groups';

    public function execute()
    {
        return $this->_forward('edit');
    }
}
