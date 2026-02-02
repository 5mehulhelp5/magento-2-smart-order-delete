<?php
namespace Thinkbeat\SmartOrderDelete\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Thinkbeat\SmartOrderDelete\Model\Log::class,
            \Thinkbeat\SmartOrderDelete\Model\ResourceModel\Log::class
        );
    }
}
