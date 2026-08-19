<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\Cookie;

use Magenx\Gdpr\Model\CookieFactory;
use Magenx\Gdpr\Model\ResourceModel\Cookie as CookieResource;
use Magento\Backend\App\Action;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::cookies';

    public function __construct(
        Action\Context $context,
        private readonly CookieFactory $cookieFactory,
        private readonly CookieResource $cookieResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id) {
            try {
                $model = $this->cookieFactory->create();
                $this->cookieResource->load($model, $id);
                $this->cookieResource->delete($model);
                $this->messageManager->addSuccessMessage(__('The cookie has been deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the cookie.'));
                return $resultRedirect->setPath('magenx_gdpr/cookie/edit', ['id' => $id]);
            }
        }

        return $resultRedirect->setPath('magenx_gdpr/cookie/index');
    }
}
