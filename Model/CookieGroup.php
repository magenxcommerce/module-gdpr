<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model;

use Magento\Framework\Model\AbstractModel;

class CookieGroup extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\CookieGroup::class);
    }
}
