<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\Request;

use Magenx\Gdpr\Model\DsrRequest;
use Magenx\Gdpr\Model\DsrRequestFactory;
use Magenx\Gdpr\Model\ResourceModel\DsrRequest as RequestResource;
use Magento\Backend\App\Action;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Deny extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::requests';

    public function __construct(
        Action\Context $context,
        private readonly DsrRequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly DateTime $dateTime
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('magenx_gdpr/request/index');

        if (!$id) {
            return $resultRedirect;
        }

        $model = $this->requestFactory->create();
        $this->requestResource->load($model, $id);

        if (!$model->getId() || $model->getData('status') !== DsrRequest::STATUS_PENDING) {
            $this->messageManager->addErrorMessage(__('This request is no longer pending.'));
            return $resultRedirect;
        }

        try {
            $model->setData('status', DsrRequest::STATUS_DENIED);
            $model->setData('resolved_at', $this->dateTime->gmtDate());
            $this->requestResource->save($model);
            $this->messageManager->addSuccessMessage(__('The request was denied.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while denying the request.'));
        }

        return $resultRedirect;
    }
}
