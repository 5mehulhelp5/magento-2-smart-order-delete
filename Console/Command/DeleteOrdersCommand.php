<?php
namespace Thinkbeat\SmartOrderDelete\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Thinkbeat\SmartOrderDelete\Service\OrderDelete;
use Magento\Framework\App\State;

class DeleteOrdersCommand extends Command
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
     * @var State
     */
    protected $state;

    /**
     * @param CollectionFactory $collectionFactory
     * @param OrderDelete $orderDeleteService
     * @param State $state
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        OrderDelete $orderDeleteService,
        State $state
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->orderDeleteService = $orderDeleteService;
        $this->state = $state;
        parent::__construct();
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('order:delete')
            ->setDescription('Delete orders based on criteria')
            ->addOption(
                'status',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter by Order Status (comma separated)'
            )
            ->addOption(
                'id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter by Order ID (comma separated)'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force delete (bypass Trash Bin)'
            );
        
        parent::configure();
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            // Fix for area code not set
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area code already set, ignore
            $output->writeln('<info>Debug: Area code already set.</info>', OutputInterface::VERBOSITY_DEBUG);
        }

        $status = $input->getOption('status');
        $ids = $input->getOption('id');

        if (!$status && !$ids) {
            $output->writeln('<error>Please provide --status or --id to select orders.</error>');
            return 1;
        }

        $collection = $this->collectionFactory->create();

        if ($status) {
            $statuses = explode(',', $status);
            $collection->addFieldToFilter('status', ['in' => $statuses]);
        }

        if ($ids) {
            $idList = explode(',', $ids);
            $collection->addFieldToFilter('increment_id', ['in' => $idList]);
        }

        $count = $collection->getSize();
        if ($count == 0) {
            $output->writeln('<info>No orders found matching criteria.</info>');
            return 0;
        }

        $output->writeln("<info>Found $count orders. Deleting...</info>");

        $deleted = 0;
        $errors = 0;

        foreach ($collection as $order) {
            try {
                $output->write("Deleting Order #{$order->getIncrementId()}... ");
                $this->orderDeleteService->deleteOrder($order->getEntityId());
                $output->writeln('<info>Done</info>');
                $deleted++;
            } catch (\Exception $e) {
                $output->writeln("<error>Error: {$e->getMessage()}</error>");
                $errors++;
            }
        }

        $output->writeln('');
        $output->writeln("<info>Complete. Deleted: $deleted. Errors: $errors.</info>");

        return 0;
    }
}
