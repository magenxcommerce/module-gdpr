<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\CookieGroup;

use Magenx\Gdpr\Model\CookieGroupFactory;
use Magenx\Gdpr\Model\ResourceModel\CookieGroup as CookieGroupResource;
use Magento\Backend\App\Action;

class Delete extends Action
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
        $id = (int) $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id) {
            try {
                $model = $this->groupFactory->create();
                $this->groupResource->load($model, $id);
                $this->groupResource->delete($model);
                $this->messageManager->addSuccessMessage(__('The cookie group has been deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the cookie group.'));
                return $resultRedirect->setPath('magenx_gdpr/cookieGroup/edit', ['id' => $id]);
            }
        }

        return $resultRedirect->setPath('magenx_gdpr/cookieGroup/index');
    }
}
