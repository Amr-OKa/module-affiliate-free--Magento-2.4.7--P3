<?php

namespace Lof\Affiliate\Controller\Account;

use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\UrlFactory;
use Magento\Customer\Model\Registration;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Framework\Escaper;
use Lof\Affiliate\Model\AccountAffiliate;
use Lof\Affiliate\Helper\Data as AffiliateData;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;

/**
 * Account creation controller for affiliate account management
 */
class CreatePost extends \Magento\Customer\Controller\AbstractAccount
{
    protected $accountManagement;
    protected $addressHelper;
    protected $formFactory;
    protected $subscriberFactory;
    protected $regionDataFactory;
    protected $addressDataFactory;
    protected $customerDataFactory;
    protected $customerUrl;
    protected $escaper;
    protected $customerExtractor;
    protected $urlModel;
    protected $dataObjectHelper;
    protected $accountRedirect;
    protected $_stdTimezone;
    protected $_accountAffiliate;
    protected $_affiliateData;
    protected $scopeConfig;
    protected $storeManager;
    protected $session;

    // Add the registration property here
    protected $registration;

    public function __construct(
        Context $context,
        Session $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        AccountManagementInterface $accountManagement,
        \Magento\Framework\Data\Form\FormKey\Validator $formFactory,
        SubscriberFactory $subscriberFactory,
        RegionInterfaceFactory $regionDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerUrl $customerUrl,
        Registration $registration, // Inject Registration service here
        Escaper $escaper,
        CustomerExtractor $customerExtractor,
        DataObjectHelper $dataObjectHelper,
        AccountRedirect $accountRedirect,
        Timezone $stdTimezone,
        AccountAffiliate $accountAffiliate,
        AffiliateData $affiliateData,
        UrlFactory $urlFactory
    ) {
        $this->session = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->formFactory = $formFactory;
        $this->registration = $registration; // Initialize the registration service
        $this->accountManagement = $accountManagement;
        $this->subscriberFactory = $subscriberFactory;
        $this->regionDataFactory = $regionDataFactory;
        $this->addressDataFactory = $addressDataFactory;
        $this->customerDataFactory = $customerDataFactory;
        $this->customerUrl = $customerUrl;
        $this->escaper = $escaper;
        $this->urlModel = $urlFactory->create();
        $this->dataObjectHelper = $dataObjectHelper;
        $this->accountRedirect = $accountRedirect;
        $this->_stdTimezone = $stdTimezone;
        $this->_accountAffiliate = $accountAffiliate;
        $this->_affiliateData = $affiliateData;
        parent::__construct($context);
    }

    protected function extractAddress()
    {
        if (!$this->getRequest()->getPost('create_address')) {
            return null;
        }

        $addressForm = $this->formFactory->create('customer_address', 'customer_register_address');
        $allowedAttributes = $addressForm->getAllowedAttributes();
        $addressData = [];

        $regionDataObject = $this->regionDataFactory->create();
        foreach ($allowedAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $value = $this->getRequest()->getParam($attributeCode);
            if ($value !== null) {
                if ($attributeCode === 'region_id') {
                    $regionDataObject->setRegionId($value);
                } elseif ($attributeCode === 'region') {
                    $regionDataObject->setRegion($value);
                } else {
                    $addressData[$attributeCode] = $value;
                }
            }
        }

        $addressDataObject = $this->addressDataFactory->create();
        $this->dataObjectHelper->populateWithArray($addressDataObject, $addressData, '\Magento\Customer\Api\Data\AddressInterface');
        $addressDataObject->setRegion($regionDataObject);

        $addressDataObject->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false));
        $addressDataObject->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
                          ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false));
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        // If the user is already logged in or registration is not allowed, redirect to the account page
        if ($this->session->isLoggedIn() || !$this->registration->isAllowed()) {
            $resultRedirect->setPath('*/*/');  // Redirect to account page
            return $resultRedirect;
        }

        // Check if the affiliate account exists
        $emailCustomer = $this->session->getCustomer()->getEmail();
        $checkAccountExist = $this->_accountAffiliate->checkAccountExist($emailCustomer);

        // Log the status of affiliate account check
        $this->_logger->info('Affiliate account check for email ' . $emailCustomer . ' returned: ' . $checkAccountExist);

        // If the account does not exist, create affiliate account
        if ($this->session->isLoggedIn() && $checkAccountExist == '0') {
            $customerData = $this->session->getCustomer();
            $this->_affiliateData->createAffiliateAccount($data, $customerData);
            $resultRedirect->setPath('*/*/edit');  // Redirect to the edit page after successful creation
            return $resultRedirect;
        }

        if (!$this->getRequest()->isPost()) {
            $url = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
            $resultRedirect->setUrl($this->_redirect->error($url));
            return $resultRedirect;
        }

        $this->session->regenerateId();

        try {
            $address = $this->extractAddress();
            $addresses = $address === null ? [] : [$address];

            // Create the customer account
            $customer = $this->customerExtractor->extract('customer_account_create', $this->_request);
            $customer->setAddresses($addresses);

            $password = $this->getRequest()->getParam('password');
            $confirmation = $this->getRequest()->getParam('password_confirmation');
            $redirectUrl = $this->session->getBeforeAuthUrl();

            $this->checkPasswordConfirmation($password, $confirmation);

            $customer = $this->accountManagement
                ->createAccount($customer, $password, $redirectUrl);

            // Log successful account creation
            $this->_logger->info('Customer account created successfully for email: ' . $customer->getEmail());

            // Create affiliate account after customer account is created
            $this->_affiliateData->createAffiliateAccount($data, $customer);

            // Optionally subscribe to newsletter
            if ($this->getRequest()->getParam('is_subscribed', false)) {
                $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
            }

            $this->_eventManager->dispatch('customer_register_success', ['account_controller' => $this, 'customer' => $customer]);

            $confirmationStatus = $this->accountManagement->getConfirmationStatus($customer->getId());
            if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                $email = $this->customerUrl->getEmailConfirmationUrl($customer->getEmail());
                $this->messageManager->addSuccess(
                    __('You must confirm your account. Please check your email for the confirmation link or <a href="%1">click here</a> for a new link.', $email)
                );
                $resultRedirect->setUrl($this->_redirect->success($this->urlModel->getUrl('*/*/index', ['_secure' => true])));

            } else {
                $this->session->setCustomerDataAsLoggedIn($customer);
                $this->messageManager->addSuccess($this->getSuccessMessage());

                // Fix for redirect loop
                $requestedRedirect = $this->accountRedirect->getRedirectCookie();
                if (!$this->scopeConfig->getValue('customer/startup/redirect_dashboard') && $requestedRedirect) {
                    $resultRedirect->setUrl($this->_redirect->success($requestedRedirect));
                    $this->accountRedirect->clearRedirectCookie();
                    return $resultRedirect;
                }

                // Fix for redirect loop, fallback to account dashboard or default URL
                $resultRedirect = $this->accountRedirect->getRedirect();
            }

            return $resultRedirect;
        } catch (StateException $e) {
            $this->_logger->error('Account creation failed: ' . $e->getMessage());
            $url = $this->urlModel->getUrl('customer/account/forgotpassword');
            $message = __(
                'There is already an account with this email address. If you are sure that it is your email address, <a href="%1">click here</a> to get your password and access your account.',
                $url
            );
            $this->messageManager->addError($message);
        } catch (InputException $e) {
            $this->_logger->error('Input error during account creation: ' . $e->getMessage());
            $this->messageManager->addError($this->escaper->escapeHtml($e->getMessage()));
            foreach ($e->getErrors() as $error) {
                $this->messageManager->addError($this->escaper->escapeHtml($error->getMessage()));
            }
        } catch (LocalizedException $e) {
            $this->_logger->error('Localized error: ' . $e->getMessage());
            $this->messageManager->addError($this->escaper->escapeHtml($e->getMessage()));
        } catch (\Exception $e) {
            $this->_logger->critical('Unexpected error during account creation: ' . $e->getMessage());
            $this->messageManager->addError(__('Something went wrong while creating your account.'));
        }

        $resultRedirect->setPath('*/*/create');
        return $resultRedirect;
    }
}
