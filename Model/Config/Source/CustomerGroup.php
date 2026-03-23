<?php
namespace Thinkbeat\SmartOrderDelete\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CustomerGroup implements OptionSourceInterface
{
    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @param GroupRepositoryInterface $groupRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        GroupRepositoryInterface $groupRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->groupRepository = $groupRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $options = [];
        try {
            $criteria = $this->searchCriteriaBuilder->create();
            $groups = $this->groupRepository->getList($criteria);
            
            foreach ($groups->getItems() as $group) {
                $options[] = [
                    'value' => (string)$group->getId(), // cast to string to prevent '0' being evaluated as empty in some admin UI versions
                    'label' => $group->getCode()
                ];
            }
        } catch (\Exception $e) {
            // Fallback empty if repository fails
        }
        
        return $options;
    }
}
