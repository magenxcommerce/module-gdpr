<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Ui\Component\Listing\Column;

use Magenx\Gdpr\Model\DsrRequest;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Approve/Deny actions on the Data Requests grid - shown only for pending
 * rows (export/anonymize requests arrive already completed and never show
 * these).
 */
class RequestActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            if (($item['status'] ?? null) !== DsrRequest::STATUS_PENDING) {
                continue;
            }
            $id = $item['request_id'];
            $item[$name]['approve'] = [
                'href' => $this->urlBuilder->getUrl('magenx_gdpr/request/approve', ['id' => $id]),
                'label' => __('Approve'),
                'confirm' => [
                    'title' => __('Approve request #%1', $id),
                    'message' => __('This anonymizes the customer\'s personal data immediately. Continue?'),
                ],
            ];
            $item[$name]['deny'] = [
                'href' => $this->urlBuilder->getUrl('magenx_gdpr/request/deny', ['id' => $id]),
                'label' => __('Deny'),
            ];
        }

        return $dataSource;
    }
}
