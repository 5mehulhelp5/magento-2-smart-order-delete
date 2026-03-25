<?php
namespace Thinkbeat\SmartOrderDelete\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Log extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('thinkbeat_smart_order_delete_log', 'log_id');
    }
}
