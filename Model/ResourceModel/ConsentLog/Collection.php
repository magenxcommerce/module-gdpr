<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model\ResourceModel\ConsentLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(\Magenx\Gdpr\Model\ConsentLog::class, \Magenx\Gdpr\Model\ResourceModel\ConsentLog::class);
    }
}
