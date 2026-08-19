<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Block\Adminhtml\CookieGroup\Edit;

use Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    protected function _construct(): void
    {
        parent::_construct();
        $this->setId('magenx_gdpr_cookiegroup_form');
    }

    protected function _prepareForm(): self
    {
        /** @var \Magenx\Gdpr\Model\CookieGroup $model */
        $model = $this->_coreRegistry->registry('magenx_gdpr_cookiegroup');

        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'edit_form',
                'action' => $this->getUrl('magenx_gdpr/cookieGroup/save'),
                'method' => 'post',
            ],
        ]);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Cookie Group')]);

        if ($model->getId()) {
            $fieldset->addField('group_id', 'hidden', ['name' => 'group_id']);
        }

        $fieldset->addField('code', 'text', [
            'name' => 'code',
            'label' => __('Code'),
            'title' => __('Code'),
            'note' => __('Machine code, e.g. "necessary". Matches the storefront consent category when shared.'),
            'required' => true,
        ]);
        $fieldset->addField('label', 'text', [
            'name' => 'label',
            'label' => __('Label'),
            'title' => __('Label'),
            'required' => true,
        ]);
        $fieldset->addField('description', 'textarea', [
            'name' => 'description',
            'label' => __('Description'),
            'title' => __('Description'),
        ]);
        $fieldset->addField('is_required', 'select', [
            'name' => 'is_required',
            'label' => __('Is Required'),
            'title' => __('Is Required'),
            'note' => __('Required categories are always granted and cannot be declined by visitors.'),
            'values' => ['1' => __('Yes'), '0' => __('No')],
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
