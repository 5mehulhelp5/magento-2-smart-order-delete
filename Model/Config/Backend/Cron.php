<?php
namespace Thinkbeat\SmartOrderDelete\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\App\Config\Storage\WriterInterface;

class Cron extends Value
{
    /**
     * Cron string path
     *
     * @var string
     */
    private const CRON_STRING_PATH = 'thinkbeat_smartdelete/auto_delete/cron_expr';

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var string
     */
    protected $_runModelPath = '';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param WriterInterface $configWriter
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * After save handler
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterSave()
    {
        $time = $this->getData('groups/auto_delete/fields/time/value');
        $frequency = $this->getData('groups/auto_delete/fields/schedule/value');

        if ($time && $frequency) {
            if (is_string($time)) {
                $time = explode(',', $time);
            }
            $cronExprArray = [
                isset($time[1]) ? (int)$time[1] : 0, // Minute
                isset($time[0]) ? (int)$time[0] : 0, // Hour
                $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*', // Day of Month
                '*', // Month
                $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*', // Day of Week
            ];

            $cronExprString = join(' ', $cronExprArray);

            try {
                $this->configWriter->save(
                    self::CRON_STRING_PATH,
                    $cronExprString,
                    $this->getScope() ?: \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    $this->getScopeId() ?: 0
                );
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t save the cron expression.'));
            }
        }

        return parent::afterSave();
    }
}
