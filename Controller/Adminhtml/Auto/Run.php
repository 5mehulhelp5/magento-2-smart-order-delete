<?php
namespace Thinkbeat\SmartOrderDelete\Controller\Adminhtml\Auto;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Thinkbeat\SmartOrderDelete\Service\AutoDeleteService;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Run extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Thinkbeat_SmartOrderDelete::config';

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var AutoDeleteService
     */
    protected $autoDeleteService;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param AutoDeleteService $autoDeleteService
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AutoDeleteService $autoDeleteService
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->autoDeleteService = $autoDeleteService;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $count = $this->autoDeleteService->processAutoDelete();
            return $result->setData([
                'success' => true,
                'message' => (string)__('%1 orders processed/deleted.', $count)
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            ]);
        }
    }
}
