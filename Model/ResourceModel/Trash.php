<?php
namespace Thinkbeat\SmartOrderDelete\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Trash extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('thinkbeat_smart_order_delete_trash', 'entity_id');
    }
}
