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
    public const ADMIN_RESOURCE = 'Thinkbeat_SmartOrderDelete::config';

    protected $resultJsonFactory;
    protected $autoDeleteService;

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
     * Execute manual run — passes $forceRun = true so the deletion proceeds
     * even when "Enable Automatic Delete" is set to No in configuration.
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            // forceRun = true: bypass the isAutoDeleteEnabled() check.
            // The admin explicitly clicked "Run Manually", so we always proceed.
            $count = $this->autoDeleteService->processAutoDelete(true);
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
