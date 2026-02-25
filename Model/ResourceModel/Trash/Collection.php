<?php
namespace Thinkbeat\SmartOrderDelete\Model\ResourceModel\Trash;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * The ID field name for this collection
     *
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Thinkbeat\SmartOrderDelete\Model\Trash::class,
            \Thinkbeat\SmartOrderDelete\Model\ResourceModel\Trash::class
        );
    }
}
