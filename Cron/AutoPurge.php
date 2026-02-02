<?php
namespace Thinkbeat\SmartOrderDelete\Cron;

use Thinkbeat\SmartOrderDelete\Model\ResourceModel\Trash\CollectionFactory;
use Thinkbeat\SmartOrderDelete\Service\OrderDelete;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class AutoPurge
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var OrderDelete
     */
    protected $orderDeleteService;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param CollectionFactory $collectionFactory
     * @param OrderDelete $orderDeleteService
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        OrderDelete $orderDeleteService,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->orderDeleteService = $orderDeleteService;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Execute cron job for auto purging trash
     *
     * @return void
     */
    public function execute()
    {
        $enabled = $this->scopeConfig->getValue(
            'thinkbeat_smartdelete/cleanup/auto_purge_enabled',
            ScopeInterface::SCOPE_STORE
        );
        if (!$enabled) {
            return;
        }

        $days = (int)$this->scopeConfig->getValue(
            'thinkbeat_smartdelete/cleanup/auto_purge_days',
            ScopeInterface::SCOPE_STORE
        );
        if ($days <= 0) {
            return;
        }

        $date = new \DateTime();
        $date->modify('-' . $days . ' days');
        $cutoffDate = $date->format('Y-m-d H:i:s');

        try {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('deleted_at', ['lt' => $cutoffDate]);

            $count = 0;
            foreach ($collection as $item) {
                $this->orderDeleteService->deleteTrashItem($item->getId());
                $count++;
            }

            if ($count > 0) {
                $this->logger->info("Thinkbeat AutoPurge: Purged $count orders from Trash Bin.");
            }
        } catch (\Exception $e) {
            $this->logger->error("Thinkbeat AutoPurge Error: " . $e->getMessage());
        }
    }
}
