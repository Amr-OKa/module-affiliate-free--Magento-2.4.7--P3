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

use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;  // Add LoggerInterface for debugging

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $_fileSystem;

    /**
     * @var \Magento\Backend\Helper\Js
     */
    protected $jsHelper;

    /**
     * stdlib timezone.
     *
     * @var \Magento\Framework\Stdlib\DateTime\Timezone
     */
    protected $_stdTimezone;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Backend\Helper\Js $jsHelper
     * @param \Magento\Framework\Stdlib\DateTime\Timezone $_stdTimezone
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Backend\Helper\Js $jsHelper,
        \Magento\Framework\Stdlib\DateTime\Timezone $_stdTimezone,
        LoggerInterface $logger  // Injecting the logger
    )
    {
        $this->_fileSystem = $filesystem;
        $this->jsHelper = $jsHelper;
        $this->_stdTimezone = $_stdTimezone;
        $this->logger = $logger;  // Store the logger
        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Lof_Affiliate::account_save');
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $dateTimeNow = $this->_stdTimezone->date()->format('Y-m-d H:i:s');

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            // Log incoming data for debugging
            $this->logger->debug('Form Data: ' . print_r($data, true));

            $model = $this->_objectManager->create('Lof\Affiliate\Model\AccountAffiliate');
            $id = $this->getRequest()->getParam('accountaffiliate_id');

            if ($id) {
                $model->load($id);
            } else {
                $data['fullname'] = $data['firstname'] . ' ' . $data['lastname'];
            }

            // Set creation date
            $data['create_at'] = $dateTimeNow;

            // Log the model data before saving
            $this->logger->debug('Model Data before Save: ' . print_r($data, true));

            $model->setData($data);
            try {
                $model->save();
                $this->messageManager->addSuccess(__('You saved this Account Affiliate.'));
                $this->_objectManager->get('Magento\Backend\Model\Session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    // Log the redirect back action
                    $this->logger->debug('Redirecting back to edit page with ID: ' . $model->getId());
                    return $resultRedirect->setPath('*/*/edit', ['accountaffiliate_id' => $model->getId(), '_current' => true]);
                }

                // Log the redirect to the listing page
                $this->logger->debug('Redirecting to account listing page');
                return $resultRedirect->setPath('*/*/');
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                // Log specific exception message
                $this->logger->error('LocalizedException: ' . $e->getMessage());
                $this->messageManager->addError($e->getMessage());
            } catch (\RuntimeException $e) {
                // Log specific exception message
                $this->logger->error('RuntimeException: ' . $e->getMessage());
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                // Log general exception message
                $this->logger->error('Exception: ' . $e->getMessage());
                $this->messageManager->addException($e, __('Something went wrong while saving the testimonial.'));
                $this->messageManager->addError($e->getMessage());
            }

            $this->_getSession()->setFormData($data);
            return $resultRedirect->setPath('*/*/edit', ['accountaffiliate_id' => $this->getRequest()->getParam('accountaffiliate_id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}
