<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::requests';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Magenx_Gdpr::requests');
        $resultPage->getConfig()->getTitle()->prepend(__('Data Requests'));

        return $resultPage;
    }
}
