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

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Lof\Affiliate\Model\ResourceModel\AccountAffiliate\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Class MassEnable
 */
class MassEnable extends \Magento\Backend\App\Action
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * MassEnable constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger; // Injecting the logger for debugging
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute()
    {
        // Get the filtered collection based on selected items in the grid
        $collection = $this->filter->getCollection($this->collectionFactory->create());

        // Log the size of the collection for debugging purposes
        $this->logger->debug('Selected Items Size: ' . $collection->getSize());

        // Check if any items were selected in the grid
        if ($collection->getSize() == 0) {
            // Log that no items were selected
            $this->logger->debug('No items selected for mass enable.');
            
            // Throw an exception with a user-friendly message
            throw new LocalizedException(__('An item needs to be selected. Select and try again.'));
        }

        // Iterate through the collection and enable each selected item
        foreach ($collection as $item) {
            $item->setIsActive(true);  // Enable the item
            $item->save();  // Save the changes
        }

        // Add a success message with the number of records that were enabled
        $this->messageManager->addSuccess(__('A total of %1 record(s) have been enabled.', $collection->getSize()));

        // Redirect back to the grid page
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/'); // Redirect to the list of accounts
    }

    /**
     * {@inheritdoc}
     * Check if the user has permission to execute this action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Lof_Affiliate::account_save');
    }
}
