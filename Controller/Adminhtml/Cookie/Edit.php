<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\Cookie;

use Magenx\Gdpr\Model\CookieFactory;
use Magenx\Gdpr\Model\ResourceModel\Cookie as CookieResource;
use Magento\Backend\App\Action;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::cookies';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $coreRegistry,
        private readonly CookieFactory $cookieFactory,
        private readonly CookieResource $cookieResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $model = $this->cookieFactory->create();

        if ($id) {
            $this->cookieResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This cookie no longer exists.'));
                return $this->_redirect('magenx_gdpr/cookie/index');
            }
        }

        $this->coreRegistry->register('magenx_gdpr_cookie', $model);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Magenx_Gdpr::cookies');
        $resultPage->getConfig()->getTitle()->prepend($model->getId() ? __('Edit Cookie') : __('New Cookie'));

        return $resultPage;
    }
}
