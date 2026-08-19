<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\CookieGroup;

use Magenx\Gdpr\Model\CookieGroupFactory;
use Magenx\Gdpr\Model\ResourceModel\CookieGroup as CookieGroupResource;
use Magento\Backend\App\Action;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::cookie_groups';

    public function __construct(
        Action\Context $context,
        private readonly CookieGroupFactory $groupFactory,
        private readonly CookieGroupResource $groupResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $resultRedirect->setPath('magenx_gdpr/cookieGroup/index');
        }

        $id = (int) ($data['group_id'] ?? 0);
        $model = $this->groupFactory->create();
        if ($id) {
            $this->groupResource->load($model, $id);
        }

        $data['is_required'] = !empty($data['is_required']) ? 1 : 0;
        $data['is_active'] = !empty($data['is_active']) ? 1 : 0;
        $model->addData($data);

        try {
            $this->groupResource->save($model);
            $this->messageManager->addSuccessMessage(__('The cookie group has been saved.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the cookie group.'));
        }

        if ($this->getRequest()->getParam('back')) {
            return $resultRedirect->setPath('magenx_gdpr/cookieGroup/edit', ['id' => $model->getId()]);
        }

        return $resultRedirect->setPath('magenx_gdpr/cookieGroup/index');
    }
}
