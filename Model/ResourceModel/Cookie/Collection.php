<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\ResourceModel\Cookie;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'cookie_id';

    protected function _construct(): void
    {
        $this->_init(\Magenx\Gdpr\Model\Cookie::class, \Magenx\Gdpr\Model\ResourceModel\Cookie::class);
    }
}
