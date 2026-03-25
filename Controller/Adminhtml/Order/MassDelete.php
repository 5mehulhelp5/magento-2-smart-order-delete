<?php
namespace Thinkbeat\SmartOrderDelete\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Thinkbeat\SmartOrderDelete\Service\OrderDelete;
use Magento\Framework\Controller\ResultFactory;

class MassDelete extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Thinkbeat_SmartOrderDelete::delete';

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var OrderDelete
     */
    protected $orderDeleteService;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param OrderDelete $orderDeleteService
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        OrderDelete $orderDeleteService
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->orderDeleteService = $orderDeleteService;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            
            foreach ($collection as $order) {
                // We use the service to delete each order safely
                $this->orderDeleteService->deleteOrder($order->getEntityId());
                $count++;
            }
            
            $this->messageManager->addSuccessMessage(__('A total of %1 order(s) have been deleted.', $count));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting orders: %1', $e->getMessage()));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/order/index');
    }
}
