<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Generic Edit/Delete actions column, reused by the Cookie Group and Cookie
 * grids. The URL paths and index field come from the column's own <config>
 * data in the listing XML, so the same class serves both entities.
 */
class EntityActions extends Column
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

        $indexField = $this->getData('config/indexField') ?? 'entity_id';
        $editUrlPath = $this->getData('config/editUrlPath');
        $deleteUrlPath = $this->getData('config/deleteUrlPath');
        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $id = $item[$indexField] ?? null;
            if ($id === null) {
                continue;
            }
            if ($editUrlPath) {
                $item[$name]['edit'] = [
                    'href' => $this->urlBuilder->getUrl($editUrlPath, ['id' => $id]),
                    'label' => __('Edit'),
                ];
            }
            if ($deleteUrlPath) {
                $item[$name]['delete'] = [
                    'href' => $this->urlBuilder->getUrl($deleteUrlPath, ['id' => $id]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete #%1', $id),
                        'message' => __('Are you sure you want to delete this record?'),
                    ],
                ];
            }
        }

        return $dataSource;
    }
}
