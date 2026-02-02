<?php
namespace Thinkbeat\SmartOrderDelete\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Thinkbeat\SmartOrderDelete\Service\OrderDelete;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;

class AutoDeleteService
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var OrderDelete
     */
    protected $orderDeleteService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $orderCollectionFactory
     * @param OrderDelete $orderDeleteService
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $orderCollectionFactory,
        OrderDelete $orderDeleteService,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderDeleteService = $orderDeleteService;
        $this->logger = $logger;
    }

    /**
     * Process auto deletion logic
     *
     * @return int
     */
    public function processAutoDelete()
    {
        $this->logger->info(
            'SmartOrderDelete Starting. Enum Enabled: ' .
            $this->isModuleEnabled() .
            ' Auto Enabled: ' .
            $this->isAutoDeleteEnabled()
        );

        if (!$this->isModuleEnabled() || !$this->isAutoDeleteEnabled()) {
             return 0;
        }

        $collection = $this->orderCollectionFactory->create();

        // 1. Excluded Period (Retention)
        $days = (int)$this->getConfig('thinkbeat_smartdelete/auto_delete/retention_period');
        if ($days > 0) {
            $date = new \DateTime();
            $date->modify('-' . $days . ' days');
            $collection->addFieldToFilter('created_at', ['lt' => $date->format('Y-m-d H:i:s')]);
        }

        // 2. Order Status
        $statuses = $this->getConfig('thinkbeat_smartdelete/auto_delete/order_status');
        if ($statuses) {
            $collection->addFieldToFilter('status', ['in' => explode(',', $statuses)]);
        }

        // 3. Customer Groups
        $groups = $this->getConfig('thinkbeat_smartdelete/auto_delete/customer_groups');
        // Empty array might mean "All" depending on interpretation.
        if ($groups !== null) {
             // Multiselect logic: if unselected in Magento config, it returns null.
             // Assuming if User doesn't select group, filter is ignored? Or nothing deleted?
             // Usually "Select All" is required if they want all.
             // Let's safe guard: if no groups selected, maybe require at least one filter?
             // Actually standard Magento logic: if config is set, apply it.
             $collection->addFieldToFilter('customer_group_id', ['in' => explode(',', $groups)]);
        }

        // 4. Store Views
        $stores = $this->getConfig('thinkbeat_smartdelete/auto_delete/store_ids');
        if ($stores !== null) {
            $collection->addFieldToFilter('store_id', ['in' => explode(',', $stores)]);
        }

        // 5. Shipping Countries (Requires join)
        // This is complex on flat order table vs address table join.
        // For performance/simplicity, we iterate or join if critical.
        // Let's check config first.
        $countries = $this->getConfig('thinkbeat_smartdelete/auto_delete/shipping_countries');
        if ($countries) {
             // Join shipping address
             $collection->getSelect()->join(
                 ['soa' => $collection->getTable('sales_order_address')],
                 'main_table.entity_id = soa.parent_id AND soa.address_type = "shipping"',
                 []
             )->where(
                 'soa.country_id IN (?)',
                 explode(',', $countries)
             );
        }

        // 6. Max Order Total
        $maxTotal = $this->getConfig('thinkbeat_smartdelete/auto_delete/max_order_total');
        if ($maxTotal !== null && $maxTotal !== '') {
            $collection->addFieldToFilter('grand_total', ['lteq' => $maxTotal]);
        }

        // Debugging: Log the query and filters
        $this->logger->info('SmartOrderDelete Auto Run Query: ' . $collection->getSelect()->__toString());
        $this->logger->info('SmartOrderDelete Config Max Total: ' . $maxTotal);
        
        $count = 0;
        foreach ($collection as $order) {
            try {
                $this->orderDeleteService->deleteOrder($order->getId());
                $count++;
            } catch (\Exception $e) {
                $this->logger->error("Auto delete failed for Order {$order->getIncrementId()}: " . $e->getMessage());
            }
        }
        
        return $count;
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    protected function isModuleEnabled()
    {
        return $this->scopeConfig->isSetFlag('thinkbeat_smartdelete/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Check if auto delete is enabled
     *
     * @return bool
     */
    protected function isAutoDeleteEnabled()
    {
        return $this->scopeConfig->isSetFlag('thinkbeat_smartdelete/auto_delete/enabled', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get config value
     *
     * @param string $path
     * @return mixed
     */
    protected function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }
}
