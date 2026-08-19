<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\Cookie;

use Magenx\Gdpr\Model\CookieFactory;
use Magenx\Gdpr\Model\ResourceModel\Cookie as CookieResource;
use Magento\Backend\App\Action;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
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
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $resultRedirect->setPath('magenx_gdpr/cookie/index');
        }

        $id = (int) ($data['cookie_id'] ?? 0);
        $model = $this->cookieFactory->create();
        if ($id) {
            $this->cookieResource->load($model, $id);
        }

        $data['group_id'] = (int) ($data['group_id'] ?? 0);
        $data['is_active'] = !empty($data['is_active']) ? 1 : 0;
        $model->addData($data);

        try {
            $this->cookieResource->save($model);
            $this->messageManager->addSuccessMessage(__('The cookie has been saved.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the cookie.'));
        }

        if ($this->getRequest()->getParam('back')) {
            return $resultRedirect->setPath('magenx_gdpr/cookie/edit', ['id' => $model->getId()]);
        }

        return $resultRedirect->setPath('magenx_gdpr/cookie/index');
    }
}
