<?php

declare(strict_types=1);

namespace Magenx\Gdpr\Controller\Adminhtml\Cookie;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Magenx_Gdpr::cookies';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Magenx_Gdpr::cookies');
        $resultPage->getConfig()->getTitle()->prepend(__('Cookies'));

        return $resultPage;
    }
}
