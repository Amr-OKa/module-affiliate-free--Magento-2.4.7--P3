<?php
namespace Lof\Affiliate\Controller\Account;

use Magento\Customer\Model\Url; // Updated to use Url
use Magento\Customer\Model\Registration;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Escaper;
use Lof\Affiliate\Helper\Data as AffiliateHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Action;  // Ensure Action is imported
use Magento\Framework\App\Action\HttpGetActionInterface;  // For HTTP GET requests
use Magento\Customer\Api\Data\CustomerInterfaceFactory; // Correct use of factory

class Create extends Action implements HttpGetActionInterface
{
    /** @var Registration */
    protected $registration;

    /** @var Session */
    protected $session;

    /** @var AffiliateHelper */
    protected $helper;

    /** @var PageFactory */
    protected $resultPageFactory;

    /** @var LoggerInterface */
    protected $logger;

    /** @var RedirectFactory */
    protected $resultRedirectFactory;

    /** @var ManagerInterface */
    protected $messageManager;

    /** @var AccountManagementInterface */
    protected $accountManagement;

    /** @var Escaper */
    protected $escaper;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var AccountRedirect */
    protected $accountRedirect;

    /** @var Timezone */
    protected $_stdTimezone;

    /** @var Url */
    protected $customerUrl;

    /** @var RegionInterfaceFactory */
    protected $regionDataFactory;

    /** @var AddressInterfaceFactory */
    protected $addressDataFactory;

    /** @var CustomerExtractor */
    protected $customerExtractor;

    /** @var DataObjectHelper */
    protected $dataObjectHelper;

    /** @var CustomerInterfaceFactory */
    protected $customerInterfaceFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Session $customerSession
     * @param PageFactory $resultPageFactory
     * @param Registration $registration
     * @param AffiliateHelper $helper
     * @param LoggerInterface $logger
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param AccountManagementInterface $accountManagement
     * @param Escaper $escaper
     * @param StoreManagerInterface $storeManager
     * @param AccountRedirect $accountRedirect
     * @param Timezone $stdTimezone
     * @param Url $customerUrl
     * @param RegionInterfaceFactory $regionDataFactory
     * @param AddressInterfaceFactory $addressDataFactory
     * @param CustomerExtractor $customerExtractor
     * @param DataObjectHelper $dataObjectHelper
     * @param CustomerInterfaceFactory $customerInterfaceFactory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        PageFactory $resultPageFactory,
        Registration $registration,
        AffiliateHelper $helper,
        LoggerInterface $logger,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        AccountManagementInterface $accountManagement,
        Escaper $escaper,
        StoreManagerInterface $storeManager,
        AccountRedirect $accountRedirect,
        Timezone $stdTimezone,
        Url $customerUrl,
        RegionInterfaceFactory $regionDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        CustomerExtractor $customerExtractor,
        DataObjectHelper $dataObjectHelper,
        CustomerInterfaceFactory $customerInterfaceFactory
    ) {
        $this->session = $customerSession;
        $this->resultPageFactory = $resultPageFactory;
        $this->registration = $registration;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->accountManagement = $accountManagement;
        $this->escaper = $escaper;
        $this->storeManager = $storeManager;
        $this->accountRedirect = $accountRedirect;
        $this->_stdTimezone = $stdTimezone;
        $this->customerUrl = $customerUrl;
        $this->regionDataFactory = $regionDataFactory;
        $this->addressDataFactory = $addressDataFactory;
        $this->customerExtractor = $customerExtractor;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->customerInterfaceFactory = $customerInterfaceFactory;

        parent::__construct($context);
    }

    /**
     * Customer register form page
     *
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        // Ensure the user is logged in and registration is allowed
        if (!$this->session->isLoggedIn() || !$this->registration->isAllowed()) {
            $customerData = $this->session->getCustomer();

            try {
                // Check if an affiliate account already exists for the customer
                $existingAccount = $this->helper->getAffiliateAccountByEmail($customerData->getEmail());
                $this->messageManager->addSuccessMessage(__("Your affiliate account has been created successfully."));

                // If $existingAccount is an array, get the first item
                if (is_array($existingAccount)) {
                    $existingAccount = reset($existingAccount);
                }

                // Ensure $existingAccount is an object and check if it has an ID
                if ($existingAccount && is_object($existingAccount) && $existingAccount->getId()) {
                    throw new LocalizedException(__('An affiliate account with this email already exists.'));
                }

                // Create the affiliate account if none exists
                $data = [
                    'email' => $customerData->getEmail(),
                    'customer_id' => $customerData->getId(),
                ];
                $this->helper->createAffiliateAccount($data, $customerData);

                // Success message
                $this->messageManager->addSuccessMessage(__('Your affiliate account has been created successfully.'));

                // Redirect to affiliate dashboard after successful creation
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('affiliate/account/dashboard'); // Adjust path if needed
                return $resultRedirect;
            } catch (LocalizedException $e) {
                // Log the exception message for debugging
                $this->logger->error('Affiliate account creation error: ' . $e->getMessage());
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                // Log the exception message for debugging
                $this->logger->error('General error during affiliate account creation: ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('An error occurred while creating the affiliate account.'));
            }

            // Redirect back to the create page if an error occurs
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('affiliate/account/create'); // Adjust path if needed
            return $resultRedirect;
        }

        // If the user is not logged in or registration is not allowed, show the page
        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}
