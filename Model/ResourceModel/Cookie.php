<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Cookie extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('magenx_gdpr_cookie', 'cookie_id');
    }
}
