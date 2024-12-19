<?php
/**
 * Landofcoder
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the venustheme.com license that is
 * available through the world-wide-web at this URL:
 * https://landofcoder.com/license
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category   Landofcoder
 * @package    Lof_Affiliate
 * @copyright  Copyright (c) 2016 Landofcoder (https://landofcoder.com)
 * @license    https://landofcoder.com/LICENSE-1.0.html
 */

namespace Lof\Affiliate\Controller\Adminhtml\AccountAffiliate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Magento\Framework\App\Action\Action;

class Index extends Action
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
    }

    /**
     * Check the permission to run it
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Lof_Affiliate::account');
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        try {
            // Ensure that the result page is correctly created
            $resultPage = $this->resultPageFactory->create();

            // Set active menu item
            $resultPage->setActiveMenu("Lof_Affiliate::account_settings");

            // Set page title
            $resultPage->getConfig()->getTitle()->prepend(__('Account Affiliate'));

            // Add breadcrumb item
            $resultPage->addBreadcrumb(__('Lof_Affiliate'), __('Account Affiliate'));
            $resultPage->addBreadcrumb(__('Manage Account Affiliate'), __('Manage Account Affiliate'));

            return $resultPage;
        } catch (\Exception $e) {
            // Log the error for easier debugging
            $this->_logger->critical($e->getMessage());
            // Handle the error gracefully by returning a proper response
            return $this->_redirect('*/*/index');
        }
    }
}
