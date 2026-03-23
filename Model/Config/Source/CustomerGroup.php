<?php
namespace Thinkbeat\SmartOrderDelete\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;

class CustomerGroup implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @param CollectionFactory $groupCollectionFactory
     */
    public function __construct(CollectionFactory $groupCollectionFactory)
    {
        $this->groupCollectionFactory = $groupCollectionFactory;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $collection = $this->groupCollectionFactory->create();
        $options = [];
        
        foreach ($collection as $group) {
            $options[] = [
                'value' => $group->getId(),
                'label' => $group->getCustomerGroupCode()
            ];
        }
        
        return $options;
    }
}
