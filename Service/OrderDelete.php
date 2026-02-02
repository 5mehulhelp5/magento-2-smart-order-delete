<?php
namespace Thinkbeat\SmartOrderDelete\Service;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Thinkbeat\SmartOrderDelete\Model\TrashFactory;
use Thinkbeat\SmartOrderDelete\Model\LogFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditmemoCollectionFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Registry;

class OrderDelete
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var TrashFactory
     */
    protected $trashFactory;

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var AuthSession
     */
    protected $authSession;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var InvoiceCollectionFactory
     */
    protected $invoiceCollectionFactory;

    /**
     * @var ShipmentCollectionFactory
     */
    protected $shipmentCollectionFactory;

    /**
     * @var CreditmemoCollectionFactory
     */
    protected $creditmemoCollectionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Order\ItemFactory
     */
    protected $orderItemFactory;

    /**
     * @var \Magento\Sales\Model\Order\AddressFactory
     */
    protected $orderAddressFactory;

    /**
     * @var \Magento\Sales\Model\Order\PaymentFactory
     */
    protected $orderPaymentFactory;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $orderStatusHistoryFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param TrashFactory $trashFactory
     * @param LogFactory $logFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param AuthSession $authSession
     * @param Json $json
     * @param InvoiceCollectionFactory $invoiceCollectionFactory
     * @param ShipmentCollectionFactory $shipmentCollectionFactory
     * @param CreditmemoCollectionFactory $creditmemoCollectionFactory
     * @param LoggerInterface $logger
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Order\ItemFactory $orderItemFactory
     * @param \Magento\Sales\Model\Order\AddressFactory $orderAddressFactory
     * @param \Magento\Sales\Model\Order\PaymentFactory $orderPaymentFactory
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderStatusHistoryFactory
     * @param Registry $registry
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        TrashFactory $trashFactory,
        LogFactory $logFactory,
        ScopeConfigInterface $scopeConfig,
        AuthSession $authSession,
        Json $json,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        ShipmentCollectionFactory $shipmentCollectionFactory,
        CreditmemoCollectionFactory $creditmemoCollectionFactory,
        LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\ItemFactory $orderItemFactory,
        \Magento\Sales\Model\Order\AddressFactory $orderAddressFactory,
        \Magento\Sales\Model\Order\PaymentFactory $orderPaymentFactory,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderStatusHistoryFactory,
        Registry $registry
    ) {
        $this->orderRepository = $orderRepository;
        $this->trashFactory = $trashFactory;
        $this->logFactory = $logFactory;
        $this->scopeConfig = $scopeConfig;
        $this->authSession = $authSession;
        $this->json = $json;
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        $this->creditmemoCollectionFactory = $creditmemoCollectionFactory;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderAddressFactory = $orderAddressFactory;
        $this->orderPaymentFactory = $orderPaymentFactory;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->registry = $registry;
    }

    /**
     * Delete order (Soft or Hard based on config)
     *
     * @param int $orderId
     * @return bool
     * @throws \Exception
     */
    public function deleteOrder($orderId)
    {
        // Emulate Secure Area
        $registeredSecure = false;
        if (!$this->registry->registry('isSecureArea')) {
            $this->registry->register('isSecureArea', true);
            $registeredSecure = true;
        }

        $trash = null;

        try {
            $order = $this->orderRepository->get($orderId);
            $incrementId = $order->getIncrementId();
            $email = $order->getCustomerEmail();
            $grandTotal = $order->getGrandTotal();
            $status = $order->getStatus();

            $isSoftDelete = $this->scopeConfig->getValue(
                'thinkbeat_smartdelete/general/soft_delete_enabled',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

            if ($isSoftDelete) {
                $trash = $this->moveToTrash($order);
                $actionType = 'Soft Delete';
            } else {
                $actionType = 'Hard Delete';
            }

            // Perform Hard Delete (it cascades usually)
            $this->orderRepository->delete($order);

            // Log
            $this->logAction($incrementId, $actionType, 'Order deleted successfully.');

            return true;
        } catch (\Exception $e) {
            // Rollback Trash if Soft Delete succeeded but Hard Delete failed
            if ($trash && $trash->getId()) {
                try {
                    $trash->delete();
                } catch (\Exception $cleanupEx) {
                    $this->logger->error(
                        'Failed to cleanup trash for order ' . $orderId . ': ' . $cleanupEx->getMessage()
                    );
                }
            }

            $this->logger->error('Error deleting order ' . $orderId . ': ' . $e->getMessage());
            throw $e;
        } finally {
            if ($registeredSecure) {
                $this->registry->unregister('isSecureArea');
            }
        }
    }

    /**
     * Move order data to trash table
     *
     * @param \Magento\Sales\Model\Order $order
     * @return \Thinkbeat\SmartOrderDelete\Model\Trash
     */
    protected function moveToTrash($order)
    {
        $orderData = $order->getData();
        
        // Load Relations
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = $item->getData();
        }
        $orderData['items'] = $items;

        $addresses = [];
        if ($order->getBillingAddress()) {
            $addresses['billing'] = $order->getBillingAddress()->getData();
        }
        if ($order->getShippingAddress()) {
            $addresses['shipping'] = $order->getShippingAddress()->getData();
        }
        $orderData['addresses'] = $addresses;

        $payments = [];
        foreach ($order->getPaymentsCollection() as $payment) {
            $payments[] = $payment->getData();
        }
        $orderData['payments'] = $payments;

        $statusHistory = [];
        foreach ($order->getStatusHistoryCollection() as $history) {
            $statusHistory[] = $history->getData();
        }
        $orderData['status_history'] = $statusHistory;

        // Load Invoices
        $invoices = [];
        $invoiceCollection = $this->invoiceCollectionFactory->create()->setOrderFilter($order);
        foreach ($invoiceCollection as $invoice) {
            $invData = $invoice->getData();
            $invItems = [];
            foreach ($invoice->getItems() as $item) {
                $invItems[] = $item->getData();
            }
            $invData['items'] = $invItems;
            $invoices[] = $invData;
        }
        $orderData['invoices'] = $invoices;

        // Load Shipments
        $shipments = [];
        $shipmentCollection = $this->shipmentCollectionFactory->create()->setOrderFilter($order);
        foreach ($shipmentCollection as $shipment) {
            $shipData = $shipment->getData();
            $shipItems = [];
            foreach ($shipment->getItems() as $item) {
                $shipItems[] = $item->getData();
            }
            $shipData['items'] = $shipItems;
            $shipments[] = $shipData;
        }
        $orderData['shipments'] = $shipments;

        // Load Creditmemos
        $creditmemos = [];
        $creditmemoCollection = $this->creditmemoCollectionFactory->create()->setOrderFilter($order);
        foreach ($creditmemoCollection as $creditmemo) {
            $cmData = $creditmemo->getData();
            $cmItems = [];
            foreach ($creditmemo->getItems() as $item) {
                $cmItems[] = $item->getData();
            }
            $cmData['items'] = $cmItems;
            $creditmemos[] = $cmData;
        }
        $orderData['creditmemos'] = $creditmemos;

        $trash = $this->trashFactory->create();
        $trash->setOrderId($order->getEntityId());
        $trash->setIncrementId($order->getIncrementId());
        $trash->setGrandTotal($order->getGrandTotal());
        $trash->setOrderStatus($order->getStatus());
        $trash->setCustomerEmail($order->getCustomerEmail());
        $trash->setOrderData($this->json->serialize($orderData));
        
        $adminUser = $this->authSession->getUser();
        $adminName = $adminUser ? $adminUser->getUsername() : 'System/CLI';
        $trash->setDeletedBy($adminName);
        
        $trash->save();
        
        return $trash;
    }

    /**
     * Log the deletion action
     *
     * @param string $incrementId
     * @param string $action
     * @param string $details
     * @return void
     */
    protected function logAction($incrementId, $action, $details = '')
    {
        $log = $this->logFactory->create();
        $adminUser = $this->authSession->getUser();
        $adminName = $adminUser ? $adminUser->getUsername() : 'System/CLI';
        
        $log->setAdminUser($adminName);
        $log->setOrderIncrementId($incrementId);
        $log->setActionType($action);
        $log->setDetails($details);
        $log->save();
    }

    /**
     * Restore order from trash
     *
     * @param int $trashId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function restoreOrder($trashId)
    {
        $trash = $this->trashFactory->create()->load($trashId);
        if (!$trash->getId()) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(__('Trash entry not found.'));
        }

        $orderData = $this->json->unserialize($trash->getOrderData());
        
        // Check if order with same Increment ID already exists
        if (isset($orderData['increment_id'])) {
            $existingOrder = $this->orderFactory->create()->loadByIncrementId($orderData['increment_id']);
            if ($existingOrder->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Order #%1 already exists in the system. Cannot restore.', $orderData['increment_id'])
                );
            }
        }

        // 1. Initialize Order
        $order = $this->orderFactory->create();
        
        // 2. Set Basic Data
        $originalId = $orderData['entity_id'] ?? null;
        $ignoredKeys = [
            'entity_id', 'items', 'addresses', 'payments', 'status_history',
            'invoices', 'shipments', 'creditmemos', 'extension_attributes',
            'send_email', 'email_sent'
        ];
        foreach ($orderData as $key => $value) {
            if (!in_array($key, $ignoredKeys)) {
                $order->setData($key, $value);
            }
        }
        
        $order->setId(null);
        // Attempt to restore original Entity ID if available and free
        if ($originalId) {
            try {
                // Check if ID is taken
                $this->orderRepository->get($originalId);
                // If we are here, ID exists. Use new ID (already null).
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                // ID is free, force it.
                $order->setEntityId($originalId);
                $order->isObjectNew(true); // Force INSERT with specific ID
            }
        }

        // 3. Addresses
        if (isset($orderData['addresses'])) {
            foreach ($orderData['addresses'] as $addrType => $addrData) {
                if (empty($addrData)) {
                    continue;
                }
                $address = $this->orderAddressFactory->create();
                $address->setData($addrData);
                $address->setId(null);
                $address->setParentId(null);
                // Ensure type is set if key-based
                if ($addrType == 'billing' && !$address->getAddressType()) {
                    $address->setAddressType('billing');
                }
                if ($addrType == 'shipping' && !$address->getAddressType()) {
                    $address->setAddressType('shipping');
                }
                
                if ($address->getAddressType() == 'billing') {
                    $order->setBillingAddress($address);
                } else {
                    $order->setShippingAddress($address);
                }
            }
        }

        // 4. Items (with Parent/Child linkage)
        if (isset($orderData['items'])) {
            $itemMap = [];
            // Pass 1: Create objects
            foreach ($orderData['items'] as $itemData) {
                $item = $this->orderItemFactory->create();
                $item->setData($itemData);
                $item->setId(null);
                $item->setOrderId(null);
                $itemMap[$itemData['item_id']] = $item;
            }
            // Pass 2: Link and Add
            foreach ($orderData['items'] as $itemData) {
                $item = $itemMap[$itemData['item_id']];
                if (!empty($itemData['parent_item_id']) && isset($itemMap[$itemData['parent_item_id']])) {
                    $parent = $itemMap[$itemData['parent_item_id']];
                    $item->setParentItem($parent);
                    $item->setParentItemId(null); // Clear ID dependency, rely on object
                }
                $order->addItem($item);
            }
        }

        // 5. Payment
        if (isset($orderData['payments'])) {
            foreach ($orderData['payments'] as $paymentData) {
                $payment = $this->orderPaymentFactory->create();
                $payment->setData($paymentData);
                $payment->setId(null);
                $payment->setParentId(null);
                $order->setPayment($payment);
                break; // Take primary payment
            }
        }

        // 6. Save (This generates new IDs)
        $this->orderRepository->save($order);
        $newOrderId = $order->getEntityId();

        // 7. Status History (needs Order ID)
        if (isset($orderData['status_history'])) {
            foreach ($orderData['status_history'] as $histData) {
                $history = $this->orderStatusHistoryFactory->create();
                $history->setData($histData);
                $history->setId(null);
                $history->setParentId($newOrderId);
                $history->save();
            }
        }

        // Log and Clean up Trash
        $this->logAction($trash->getIncrementId(), 'Restore', 'Order restored. New Entity ID: ' . $newOrderId);
        $trash->delete();

        return true;
    }
    
    /**
     * Purge old trash records
     *
     * @return void
     */
    public function purgeTrash()
    {
        // Logic to delete old trash records
        // TODO: Implement later
        $this->logger->info('Purge Trash triggered but not implemented.');
    }

    /**
     * Delete item from trash permanently
     *
     * @param int $trashId
     * @return void
     */
    public function deleteTrashItem($trashId)
    {
        $trash = $this->trashFactory->create()->load($trashId);
        if ($trash->getId()) {
            $trash->delete();
        }
    }
}
