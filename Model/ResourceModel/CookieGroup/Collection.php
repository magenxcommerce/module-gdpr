<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\ResourceModel\CookieGroup;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'group_id';

    protected function _construct(): void
    {
        $this->_init(\Magenx\Gdpr\Model\CookieGroup::class, \Magenx\Gdpr\Model\ResourceModel\CookieGroup::class);
    }
}
