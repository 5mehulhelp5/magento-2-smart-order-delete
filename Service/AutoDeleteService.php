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
     * Process auto deletion logic.
     *
     * @param bool $forceRun  Set TRUE for "Run Manually" button so it works even
     *                        when "Enable Automatic Delete" is set to No in config.
     *                        The cron passes FALSE (default) and respects the flag.
     * @return int  Number of orders deleted
     */
    public function processAutoDelete(bool $forceRun = false): int
    {
        $moduleEnabled = $this->isModuleEnabled();

        $autoEnabled   = $this->isAutoDeleteEnabled();

        $this->logger->info(sprintf(
            'SmartOrderDelete Starting. Module Enabled: %s | Auto Enabled: %s | Force Run: %s',
            $moduleEnabled ? 'yes' : 'no',
            $autoEnabled   ? 'yes' : 'no',
            $forceRun      ? 'yes' : 'no'
        ));

        // Module must always be enabled.
        if (!$moduleEnabled) {
            $this->logger->info('SmartOrderDelete: Module disabled, skipping.');
            return 0;
        }

        // For scheduled cron: also require Auto Delete to be enabled.
        // For manual runs ($forceRun = true): skip this check so the admin
        // can trigger deletion regardless of the auto-delete toggle.
        if (!$forceRun && !$autoEnabled) {
            $this->logger->info('SmartOrderDelete: Auto Delete disabled and not a forced run, skipping.');
            return 0;
        }

        $collection = $this->orderCollectionFactory->create();

        // 1. Retention Period
        $days = (int)$this->getConfig('thinkbeat_smartdelete/auto_delete/retention_period');
        if ($days > 0) {
            $date = new \DateTime();
            $date->modify('-' . $days . ' days');
            $collection->addFieldToFilter('created_at', ['lt' => $date->format('Y-m-d H:i:s')]);
        }

        // 2. Order Status
        $statuses = $this->getConfig('thinkbeat_smartdelete/auto_delete/order_status');
        if ($statuses !== null && $statuses !== '') {
            $statusArray = is_array($statuses) ? $statuses : explode(',', (string)$statuses);
            $collection->addFieldToFilter('status', ['in' => $statusArray]);
        }

        // 3. Customer Groups
        $groups = $this->getConfig('thinkbeat_smartdelete/auto_delete/customer_groups');
        if ($groups !== null && $groups !== '') {
            $groupArray = is_array($groups) ? $groups : explode(',', (string)$groups);
            $collection->addFieldToFilter('customer_group_id', ['in' => $groupArray]);
        }

        // 4. Store Views
        $stores = $this->getConfig('thinkbeat_smartdelete/auto_delete/store_ids');
        if ($stores !== null && $stores !== '') {
            $storeArray = is_array($stores) ? $stores : explode(',', (string)$stores);
            $collection->addFieldToFilter('store_id', ['in' => $storeArray]);
        }

        // 5. Shipping Countries
        $countries = $this->getConfig('thinkbeat_smartdelete/auto_delete/shipping_countries');
        if ($countries !== null && $countries !== '') {
            $countryArray = is_array($countries) ? $countries : explode(',', (string)$countries);
            $collection->getSelect()->joinLeft(
                ['soa_shipping' => $collection->getTable('sales_order_address')],
                'main_table.entity_id = soa_shipping.parent_id AND soa_shipping.address_type = "shipping"',
                []
            )->joinLeft(
                ['soa_billing' => $collection->getTable('sales_order_address')],
                'main_table.entity_id = soa_billing.parent_id AND soa_billing.address_type = "billing"',
                []
            )->where(
                'COALESCE(soa_shipping.country_id, soa_billing.country_id) IN (?)',
                $countryArray
            );
        }

        // 6. Max Order Total
        $maxTotal = $this->getConfig('thinkbeat_smartdelete/auto_delete/max_order_total');
        if ($maxTotal !== null && $maxTotal !== '') {
            $collection->addFieldToFilter('grand_total', ['lteq' => $maxTotal]);
        }

        $this->logger->info('SmartOrderDelete Query: ' . $collection->getSelect()->__toString());
        $this->logger->info('SmartOrderDelete Total matched orders: ' . $collection->getSize());

        $count = 0;
        foreach ($collection as $order) {
            try {
                $this->orderDeleteService->deleteOrder($order->getId());
                $count++;
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'SmartOrderDelete: Failed for Order %s — %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info('SmartOrderDelete Finished. Deleted: ' . $count);
        return $count;
    }

    /**
     * Check if module enabled
     *
     * @return bool
     */
    protected function isModuleEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'thinkbeat_smartdelete/general/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if auto delete enabled
     *
     * @return bool
     */
    protected function isAutoDeleteEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'thinkbeat_smartdelete/auto_delete/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Config
     *
     * @param string $path
     * @return mixed
     */
    protected function getConfig(string $path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }
}
