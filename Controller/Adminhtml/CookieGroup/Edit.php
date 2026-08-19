<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\CookieGroup;

use Magenx\Gdpr\Model\CookieGroupFactory;
use Magenx\Gdpr\Model\ResourceModel\CookieGroup as CookieGroupResource;
use Magento\Backend\App\Action;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::cookie_groups';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $coreRegistry,
        private readonly CookieGroupFactory $groupFactory,
        private readonly CookieGroupResource $groupResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = $this->groupFactory->create();

        if ($id) {
            $this->groupResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This cookie group no longer exists.'));
                return $this->_redirect('magenx_gdpr/cookieGroup/index');
            }
        }

        $this->coreRegistry->register('magenx_gdpr_cookiegroup', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Magenx_Gdpr::cookie_groups');
        $resultPage->getConfig()->getTitle()->prepend(
            $model->getId() ? __('Edit Cookie Group') : __('New Cookie Group')
        );

        return $resultPage;
    }
}
