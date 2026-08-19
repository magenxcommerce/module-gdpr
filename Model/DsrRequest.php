<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * A customer's data-subject request (export / anonymize / erase).
 *
 * Named DsrRequest rather than Request to avoid any confusion with
 * \Magento\Framework\App\RequestInterface in code that imports both.
 */
class DsrRequest extends AbstractModel
{
    public const TYPE_EXPORT_DATA = 'export_data';
    public const TYPE_ANONYMIZE_DATA = 'anonymize_data';
    public const TYPE_ERASE_DATA = 'erase_data';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_COMPLETED = 'completed';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\DsrRequest::class);
    }
}
