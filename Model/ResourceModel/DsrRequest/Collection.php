<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\ResourceModel\DsrRequest;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'request_id';

    protected function _construct(): void
    {
        $this->_init(\Magenx\Gdpr\Model\DsrRequest::class, \Magenx\Gdpr\Model\ResourceModel\DsrRequest::class);
    }
}
