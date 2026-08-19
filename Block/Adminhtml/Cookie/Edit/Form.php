<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Block\Adminhtml\Cookie\Edit;

use Magenx\Gdpr\Model\ResourceModel\CookieGroup\CollectionFactory as CookieGroupCollectionFactory;
use Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        private readonly CookieGroupCollectionFactory $groupCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _construct(): void
    {
        parent::_construct();
        $this->setId('magenx_gdpr_cookie_form');
    }

    protected function _prepareForm(): self
    {
        /** @var \Magenx\Gdpr\Model\Cookie $model */
        $model = $this->_coreRegistry->registry('magenx_gdpr_cookie');

        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getUrl('magenx_gdpr/cookie/save'),
                'method' => 'post',
            ],
        ]);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Cookie')]);

        if ($model->getId()) {
            $fieldset->addField('cookie_id', 'hidden', ['name' => 'cookie_id']);
        }

        $groupOptions = ['' => __('-- Please Select --')];
        foreach ($this->groupCollectionFactory->create() as $group) {
            $groupOptions[$group->getId()] = $group->getData('label');
        }

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => __('Cookie Name'),
            'title' => __('Cookie Name'),
            'note' => __('Exact name, or a trailing * to match a prefix (e.g. _ga_*).'),
            'required' => true,
        ]);
        $fieldset->addField('group_id', 'select', [
            'name' => 'group_id',
            'label' => __('Group'),
            'title' => __('Group'),
            'values' => $groupOptions,
            'required' => true,
        ]);
        $fieldset->addField('source', 'select', [
            'name' => 'source',
            'label' => __('Set By'),
            'title' => __('Set By'),
            'values' => [
                'storefront' => __('Storefront'),
                'auth' => __('Authentication'),
                'google' => __('Google'),
            ],
        ]);
        $fieldset->addField('purpose', 'textarea', [
            'name' => 'purpose',
            'label' => __('Purpose'),
            'title' => __('Purpose'),
            'note' => __('Shown to visitors on the cookie policy page.'),
        ]);
        $fieldset->addField('duration_label', 'text', [
            'name' => 'duration_label',
            'label' => __('Duration'),
            'title' => __('Duration'),
            'note' => __('Display text, e.g. "1 hour", "Session", "2 years".'),
        ]);
        $fieldset->addField('is_active', 'select', [
            'name' => 'is_active',
            'label' => __('Is Active'),
            'title' => __('Is Active'),
            'values' => ['1' => __('Yes'), '0' => __('No')],
        ]);
        $fieldset->addField('sort_order', 'text', [
            'name' => 'sort_order',
            'label' => __('Sort Order'),
            'title' => __('Sort Order'),
        ]);

        $form->setValues($model->getData());
        $this->setForm($form);

        return $this;
    }
}
