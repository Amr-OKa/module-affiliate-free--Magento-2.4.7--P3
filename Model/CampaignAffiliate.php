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

namespace Lof\Affiliate\Model;

use Lof\Affiliate\Api\Data\CampaignInterface;

class CampaignAffiliate extends \Magento\Rule\Model\AbstractModel implements CampaignInterface
{
    // Constants for statuses and actions
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;

    const TO_PERCENT_ACTION = 'to_percent';
    const BY_PERCENT_ACTION = 'by_percent';
    const TO_FIXED_ACTION = 'to_fixed';
    const BY_FIXED_ACTION = 'by_fixed';
    const CART_FIXED_ACTION = 'cart_fixed';
    const BUY_X_GET_Y_ACTION = 'buy_x_get_y';

    // Model properties
    protected $_conditions;
    protected $_actions;
    protected $_form;
    protected $_isDeleteable = true;
    protected $_isReadonly = false;
    protected $_url;
    protected $_campaignlHelper;
    protected $_campaignFactory;
    protected $_resource;
    protected $_resourceModel;
    protected $session;
    protected $_eventPrefix = 'salesrule_rule';
    protected $_eventObject = 'rule';
    protected $_condCombineFactory;
    protected $_condProdCombineF;
    protected $_validatedAddresses = [];
    protected $_formFactory;
    protected $_localeDate;

    /**
     * Constructor
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\App\ResourceConnection $resourceModel,
        \Lof\Affiliate\Model\ResourceModel\CampaignAffiliate\CollectionFactory $campaignFactory,
        \Lof\Affiliate\Helper\Data $campaignlHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $url,
        \Magento\SalesRule\Model\Rule\Condition\CombineFactory $condCombineFactory,
        \Magento\SalesRule\Model\Rule\Condition\Product\CombineFactory $condProdCombineF,
        \Magento\Customer\Model\Session $customerSession,
        \Lof\Affiliate\Model\ResourceModel\CampaignAffiliate $resource = null,
        \Lof\Affiliate\Model\ResourceModel\CampaignAffiliate\Collection $resourceCollection = null,
        array $data = []
    ) {
        $this->_resource = $resource;
        $this->_resourceModel = $resourceModel;
        $this->_campaignlHelper = $campaignlHelper;
        $this->_campaignFactory = $campaignFactory;
        $this->_storeManager = $storeManager;
        $this->_url = $url;
        $this->_condCombineFactory = $condCombineFactory;
        $this->_condProdCombineF = $condProdCombineF;
        $this->session = $customerSession;

        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Initialize the model
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\Lof\Affiliate\Model\ResourceModel\CampaignAffiliate::class);
        $this->setIdFieldName('campaign_id');
    }

    /**
     * Initialize rule model data from array.
     */
    public function loadPost(array $data)
    {
        parent::loadPost($data);

        if (isset($data['store_labels'])) {
            $this->setStoreLabels($data['store_labels']);
        }

        return $this;
    }

    /**
     * Get rule condition combine model instance
     */
    public function getConditionsInstance()
    {
        return $this->_condCombineFactory->create();
    }

    /**
     * Get rule condition product combine model instance
     */
    public function getActionsInstance()
    {
        return $this->_condProdCombineF->create();
    }

    /**
     * Get sales rule customer group Ids
     */
    public function getCustomerGroupIds()
    {
        if (!$this->hasCustomerGroupIds()) {
            $customerGroupIds = $this->_getResource()->getCustomerGroupIds($this->getId());
            $this->setData('customer_group_ids', (array)$customerGroupIds);
        }
        return $this->_getData('customer_group_ids');
    }

    /**
     * Get Rule label by specified store
     */
    public function getStoreLabel($store = null)
    {
        $storeId = $this->_storeManager->getStore($store)->getId();
        $labels = (array)$this->getStoreLabels();

        if (isset($labels[$storeId])) {
            return $labels[$storeId];
        } elseif (isset($labels[0]) && $labels[0]) {
            return $labels[0];
        }

        return false;
    }

    /**
     * Set and retrieve rule store labels
     */
    public function getStoreLabels()
    {
        if (!$this->hasStoreLabels()) {
            $labels = $this->_getResource()->getStoreLabels($this->getId());
            $this->setStoreLabels($labels);
        }

        return $this->_getData('store_labels');
    }

    /**
     * Getter and Setter methods for properties
     */
    public function getFromDate()
    {
        return $this->getData('from_date');
    }

    public function getToDate()
    {
        return $this->getData('to_date');
    }

    /**
     * Prevent blocks recursion before saving
     */
    public function beforeSave()
    {
        $needle = 'campaign_id="' . $this->getId() . '"';
        $content = $this->getContent();
        if (empty($content) || (!empty($content) && false == @strstr($content, $needle))) {
            return parent::beforeSave();
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Make sure that category content does not reference the block itself.')
        );
    }

    /**
     * Load by attribute
     */
    public function loadByAttribute($attribute, $value)
    {
        $this->load($value, $attribute);
        return $this;
    }

    /**
     * Get list of campaigns based on display and group_id
     */
    public function loadListByAttribute($display_is_guest, $group_id = 0)
    {
        $rows = [];
        $table_name = $this->_resourceModel->getTableName('lof_affiliate_campaign');
        $connection = $this->_resource->getConnection();
        $select = $connection->select()->from(['ca' => $table_name]);

        if ($display_is_guest == '1') {
            $select->where('ca.display = ?', $display_is_guest);
        } else {
            $select->where('ca.display = ?', $display_is_guest)->where('ca.group_id = ?', $group_id);
        }

        $rows = $connection->fetchAll($select);

        return $rows;
    }

    /**
     * Get available statuses
     */
    public function getAvailableStatuses()
    {
        return [self::STATUS_ENABLED => __('Enabled'), self::STATUS_DISABLED => __('Disabled')];
    }

    /**
     * Get Yes/No field options
     */
    public function getYesNoField()
    {
        return [self::STATUS_ENABLED => __('Yes'), self::STATUS_DISABLED => __('No')];
    }

    /**
     * Get Discount options
     */
    public function getDiscountField()
    {
        return [
            'by_percent' => __('Percent of current cart total'),
            'cart_fixed' => __('Fixed Amount Commission For Whole Cart')
        ];
    }

    /**
     * Get Display options
     */
    public function getDisplayField()
    {
        return [
            self::STATUS_ENABLED => __('Allow Guest'),
            self::STATUS_DISABLED => __('Affiliate Member Only')
        ];
    }

    /**
     * Get Group Type options
     */
    public function getGroupType()
    {
        $data = [];
        $table_name = $this->_resourceModel->getTableName('lof_affiliate_group');
        $connection = $this->_resource->getConnection();
        $select = $connection->select()->from(['ce' => $table_name], ['group_id', 'name']);
        $rows = $connection->fetchAll($select);

        foreach ($rows as $key => $result) {
            $data[$result['group_id']] = $result['name'];
        }

        return empty($data) ? [0 => 'Default'] : $data;
    }

    /**
     * Get list of campaigns based on group_id
     */
    public function getListCampaigns($group_id)
    {
        $table_name = $this->_resourceModel->getTableName('lof_affiliate_campaign');
        $connection = $this->_resource->getConnection();
        $select = $connection->select()->from(['ca' => $table_name]);
        $select->where('ca.group_id = ?', $group_id)
            ->where('ca.is_active=?', 1);

        return $connection->fetchAll($select);
    }

    /**
     * Get campaigns by date range
     */
    public function getListCampaignsByDate($campaign_code, $currentDate)
    {
        $table_name = $this->_resourceModel->getTableName('lof_affiliate_campaign');
        $connection = $this->_resource->getConnection();
        $select = $connection->select()->from(['ca' => $table_name])
            ->where('ca.to_date >= ?', $currentDate)
            ->where('ca.from_date < ?', $currentDate)
            ->where('ca.tracking_code = ?', $campaign_code);

        return $connection->fetchRow($select);
    }

    // Getter and Setter methods for campaign properties

    public function getId()
    {
        return $this->getData(self::CAMPAIGN_ID);
    }

    public function setId($id)
    {
        return $this->setData(self::CAMPAIGN_ID, $id);
    }

    public function getName()
    {
        return $this->getData(self::NAME);
    }

    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }

    public function setDescription($description)
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getDisplay()
    {
        return $this->getData(self::DISPLAY);
    }

    public function setDisplay($display)
    {
        return $this->setData(self::DISPLAY, $display);
    }

    public function setFromDate($from_date)
    {
        return $this->setData(self::FROM_DATE, $from_date);
    }

    public function setToDate($to_date)
    {
        return $this->setData(self::TO_DATE, $to_date);
    }

    public function getDiscountAction()
    {
        return $this->getData(self::DISCOUNT_ACTION);
    }

    public function setDiscountAction($discount_action)
    {
        return $this->setData(self::DISCOUNT_ACTION, $discount_action);
    }

    public function getDiscountAmount()
    {
        return $this->getData(self::DISCOUNT_AMOUNT);
    }

    public function setDiscountAmount($discount_amount)
    {
        return $this->setData(self::DISCOUNT_AMOUNT, $discount_amount);
    }

    public function getCommission()
    {
        return $this->getData(self::COMMISSION);
    }

    public function setCommission($commission)
    {
        return $this->setData(self::COMMISSION, $commission);
    }

    public function getTrackingCode()
    {
        return $this->getData(self::TRACKING_CODE);
    }

    public function setTrackingCode($tracking_code)
    {
        return $this->setData(self::TRACKING_CODE, $tracking_code);
    }

    public function getGroupId()
    {
        return $this->getData(self::GROUP_ID);
    }

    public function setGroupId($group_id)
    {
        return $this->setData(self::GROUP_ID, $group_id);
    }

    public function getSignupCommission()
    {
        return $this->getData(self::SIGNUP_COMMISSION);
    }

    public function setSignupCommission($signup_commission)
    {
        return $this->setData(self::SIGNUP_COMMISSION, $signup_commission);
    }

    public function getLimitAccount()
    {
        return $this->getData(self::LIMIT_ACCOUNT);
    }

    public function setLimitAccount($limit_account)
    {
        return $this->setData(self::LIMIT_ACCOUNT, $limit_account);
    }

    public function getLimitBalance()
    {
        return $this->getData(self::LIMIT_BALANCE);
    }

    public function setLimitBalance($limit_balance)
    {
        return $this->setData(self::LIMIT_BALANCE, $limit_balance);
    }
}
