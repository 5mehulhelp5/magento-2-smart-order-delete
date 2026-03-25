<?php
namespace Thinkbeat\SmartOrderDelete\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class RunManually extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Thinkbeat_SmartOrderDelete::system/config/run_manually.phtml';

    /**
     * Get HTML for the element
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get URL for the manual run controller
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('thinkbeat_smartdelete/auto/run');
    }

    /**
     * Generate HTML for the run button
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'thinkbeat_run_manually_btn',
            'label' => __('Run Manually'),
            'onclick' => 'javascript:runAutoDelete(); return false;'
        ]);

        return $button->toHtml();
    }
}
