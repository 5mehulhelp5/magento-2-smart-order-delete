<?php
namespace Thinkbeat\SmartOrderDelete\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * Cron backend model.
 *
 * Magento 2.4.7+ compatibility: removed Magento\Framework\Registry from
 * constructor — it was only passed to parent::__construct() and served
 * no functional purpose. AbstractModel still supports it but injecting
 * the deprecated Registry via DI triggers deprecation warnings in 2.4.7+.
 */
class Cron extends Value
{
    private const CRON_STRING_PATH = 'thinkbeat_smartdelete/auto_delete/cron_expr';

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param WriterInterface $configWriter
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * After save handler — persist the cron expression built from the time/frequency fields.
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterSave()
    {
        $time      = $this->getData('groups/auto_delete/fields/time/value');
        $frequency = $this->getData('groups/auto_delete/fields/schedule/value');

        if ($time && $frequency) {
            if (is_string($time)) {
                $time = explode(',', $time);
            }

            $cronExprArray = [
                isset($time[1]) ? (int)$time[1] : 0,   // Minute
                isset($time[0]) ? (int)$time[0] : 0,   // Hour
                $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*',
                '*',
                $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY  ? '1' : '*',
            ];

            $cronExprString = implode(' ', $cronExprArray);

            try {
                $this->configWriter->save(
                    self::CRON_STRING_PATH,
                    $cronExprString,
                    $this->getScope() ?: \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    $this->getScopeId() ?: 0
                );
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("We can't save the cron expression.")
                );
            }
        }

        return parent::afterSave();
    }
}
