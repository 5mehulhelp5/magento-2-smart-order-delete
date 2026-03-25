<?php
namespace Thinkbeat\SmartOrderDelete\Model;

use Magento\Framework\Model\AbstractModel;

class Trash extends AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Thinkbeat\SmartOrderDelete\Model\ResourceModel\Trash::class);
    }
}
