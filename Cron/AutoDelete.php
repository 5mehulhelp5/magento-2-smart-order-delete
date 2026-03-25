<?php
namespace Thinkbeat\SmartOrderDelete\Cron;

use Thinkbeat\SmartOrderDelete\Service\AutoDeleteService;
use Psr\Log\LoggerInterface;

class AutoDelete
{
    /**
     * @var AutoDeleteService
     */
    protected $autoDeleteService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param AutoDeleteService $autoDeleteService
     * @param LoggerInterface $logger
     */
    public function __construct(
        AutoDeleteService $autoDeleteService,
        LoggerInterface $logger
    ) {
        $this->autoDeleteService = $autoDeleteService;
        $this->logger = $logger;
    }

    /**
     * Execute auto delete job
     *
     * @return void
     */
    public function execute()
    {
        try {
            $this->logger->info("Thinkbeat SmartOrderDelete: Executing AutoDelete cron.");
            $count = $this->autoDeleteService->processAutoDelete();
            $this->logger->info("Thinkbeat SmartOrderDelete: Processed $count orders.");
        } catch (\Throwable $e) {
            $this->logger->error("Thinkbeat SmartOrderDelete AutoDelete cron failed: " . $e->getMessage());
        }
    }
}
