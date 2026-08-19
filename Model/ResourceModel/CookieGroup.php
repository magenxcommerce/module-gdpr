<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CookieGroup extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('magenx_gdpr_cookie_group', 'group_id');
    }
}
