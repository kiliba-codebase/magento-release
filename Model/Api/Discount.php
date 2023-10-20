<?php


namespace Kiliba\Connector\Model\Api;

use Kiliba\Connector\Api\Module\DiscountInterface;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Model\Import\Visit;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\OfflineShipping\Model\SalesRule\Rule as FreeShippingOption;
use Magento\SalesRule\Api\CouponManagementInterface;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\ConditionInterface;
use Magento\SalesRule\Api\Data\ConditionInterfaceFactory;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as CustomerGroupCollectionFactory;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\Rule;

class Discount extends AbstractApiAction implements DiscountInterface
{
    const PARAM_CODE             = "code";
    const PARAM_DATE_FROM        = "date_from";
    const PARAM_DATE_TO          = "date_to";
    const PARAM_QUANTITY         = "quantity";
    const PARAM_QUANTITY_USER    = "quantity_per_user";
    const PARAM_APPLY_TAX        = "minimum_amount_tax"; // boolean
    const PARAM_FREE_SHIPPING    = "free_shipping"; // boolean
    const PARAM_DISCOUNT_AMOUNT  = "reduction_amount";
    const PARAM_DISCOUNT_PERCENT = "reduction_percent";
    const PARAM_MINIMUM_AMOUNT   = "minimum_amount"; // optional

    const DISCOUNT_NAME = "Kiliba";
    const DISCOUNT_DESCRIPTION = "Kiliba Cart rule";

    const CREATION_REQUIRED_PARAM = [
        self::PARAM_CODE,
        self::PARAM_QUANTITY,
        self::PARAM_QUANTITY_USER,
        self::PARAM_APPLY_TAX,
        self::PARAM_FREE_SHIPPING,
        self::PARAM_DISCOUNT_AMOUNT,
    ];

    /**
     * @var RuleInterfaceFactory
     */
    protected $_ruleInterfaceFactory;

    /**
     * @var RuleRepositoryInterface
     */
    protected $_ruleRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var Coupon
     */
    protected $_couponModel;

    /**
     * @var CustomerGroupCollectionFactory
     */
    protected $_customerGroupCollectionFactory;

    /**
     * @var ConditionInterfaceFactory
     */
    protected $_conditionFactory;

    /**
     * @var CouponInterfaceFactory
     */
    protected $_couponFactory;

    /**
     * @var CouponRepositoryInterface
     */
    protected $_couponRepository;

    /**
     * @var CouponManagementInterface
     */
    protected $_couponManager;

    public function __construct(
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        ConfigHelper $configHelper,
        KilibaLogger $kilibaLogger,
        Visit $visitManager,
        DeletedItem $deletedItemManager,
        RuleInterfaceFactory $ruleInterfaceFactory,
        RuleRepositoryInterface $ruleRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Coupon $couponModel,
        CouponInterfaceFactory $couponFactory,
        CouponRepositoryInterface $couponRepository,
        CouponManagementInterface $couponManager,
        CustomerGroupCollectionFactory $customerGroupCollectionFactory,
        ConditionInterfaceFactory $conditionFactory
    ) {
        parent::__construct($request, $resourceConnection, $configHelper, $kilibaLogger,$visitManager,$deletedItemManager);
        $this->_ruleInterfaceFactory = $ruleInterfaceFactory;
        $this->_ruleRepository = $ruleRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_couponModel = $couponModel;
        $this->_couponFactory = $couponFactory;
        $this->_couponRepository = $couponRepository;
        $this->_couponManager = $couponManager;
        $this->_customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->_conditionFactory = $conditionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createMagentoDiscountCode()
    {
        $result = $this->_checkToken();
        if (!$result["success"]) {
            $result["status"] = self::STATUS_ERROR;
            return [$result];
        }

        // get target website
        $accountId = $this->_request->getParam("accountId");
        $return = $this->_getTargetedStore($accountId);
        if (is_array($return)) {
            $result["status"] = self::STATUS_ERROR;
            return $return;
        }
        $websiteId = $return;
        
        // check required parameters
        $params = $this->_request->getParams();
        $stopForErrors = false;
        foreach (self::CREATION_REQUIRED_PARAM as $requiredKey) {
            if (!array_key_exists($requiredKey, $params)) {
                if (
                    ($requiredKey == self::PARAM_DISCOUNT_AMOUNT
                    && !array_key_exists(self::PARAM_DISCOUNT_PERCENT, $params))
                    || $requiredKey != self::PARAM_DISCOUNT_AMOUNT
                ) {
                    $this->logOnMissingParam("'".$requiredKey."'");
                    $stopForErrors = true;
                }
            }
        }
        if ($stopForErrors) {
            return [[
                "success" => false,
                "code" => self::ERROR_CODE_MISSING_PARAM,
                "status" => self::STATUS_ERROR
            ]];
        }

        $isAmount = false;
        if (array_key_exists(self::PARAM_DISCOUNT_AMOUNT, $params)) {
            $isAmount = true;
            $discount = $params[self::PARAM_DISCOUNT_AMOUNT];
        } else {
            $discount = $params[self::PARAM_DISCOUNT_PERCENT];
        }

        $minimumAmount = $params[self::PARAM_MINIMUM_AMOUNT] ?? null;
        $dateFrom = $params[self::PARAM_DATE_FROM] ?? null;
        $dateTo = $params[self::PARAM_DATE_TO] ?? null;

        return $this->_createDiscountRule(
            $params[self::PARAM_CODE],
            $websiteId,
            $params[self::PARAM_QUANTITY],
            $params[self::PARAM_QUANTITY_USER],
            $dateFrom,
            $dateTo,
            $discount,
            $isAmount,
            $minimumAmount,
            $params[self::PARAM_APPLY_TAX],
            $params[self::PARAM_FREE_SHIPPING]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMagentoDiscountCode() {
        $result = $this->_checkToken();
        if (!$result["success"]) {
            $result["status"] = self::STATUS_ERROR;
            return [$result];
        }

        $code = $this->_request->getParam(self::PARAM_CODE);

        if (empty($code)) {
            $this->logOnMissingParam("'code'");
            return [[
                "success" => false,
                "code" => self::ERROR_CODE_MISSING_PARAM,
                "status" => self::STATUS_ERROR
            ]];
        }

        if ($ruleId = $this->_isDiscountAlreadyExist($code)) {
            try {
                $this->_couponManager->deleteByCodes([$code]);
                $this->_ruleRepository->deleteById($ruleId);
                return [["success" => true, "status" => self::STATUS_SUCCESS]];
            } catch (\Exception $e) {
                $this->_kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_ERROR,
                    "Delete discount code",
                    $e->getMessage(),
                    $this->_websiteId
                );
                return [[
                    "success" => false,
                    "code" => self::ERROR_CODE_CANNOT_DELETE_DISCOUNT,
                    "status" => self::STATUS_ERROR
                ]];
            }
        } else {
            return [[
                "success" => false,
                "code" => self::ERROR_CODE_DISCOUNT_DOESNT_EXIST,
                "status" => self::STATUS_ERROR
            ]];
        }
    }

    public function purgeMagentoDiscountCode() {
        $result = $this->_checkToken();
        if (!$result["success"]) {
            $result["status"] = self::STATUS_ERROR;
            return [$result];
        }

        $this->_searchCriteriaBuilder
            ->addFilter("name", self::DISCOUNT_NAME)
            ->addFilter("to_date", date("Y-m-d"), "lt");

        $inactivePromoRuleCollection = $this->_ruleRepository->getList($this->_searchCriteriaBuilder->create());

        try {
            $numberDeleted = 0;
            foreach ($inactivePromoRuleCollection->getItems() as $inactiveRule) {
                // will delete coupon associated thanks to foreign key cascade
                $this->_ruleRepository->deleteById($inactiveRule->getRuleId());
                $numberDeleted++;
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Purge old promo code",
                $e->getMessage(),
                $this->_websiteId
            );
            return [[
                "success" => false,
                "code" => self::ERROR_CODE_CANNOT_DELETE_DISCOUNT,
                "status" => self::STATUS_ERROR
            ]];
        }

        $this->_kilibaLogger->addLog(
            KilibaLogger::LOG_TYPE_INFO,
            "Purge old promo code",
            $numberDeleted . " were deleted",
            $this->_websiteId
        );
        return [[
            "success" => true,
            "result" => $numberDeleted,
            "status" => self::STATUS_SUCCESS]];
    }

    /**
     * @param string $code
     * @param int $websiteId
     * @param int $useCoupon
     * @param int $useCustomer
     * @param ?string $fromDate
     * @param ?string $toDate
     * @param int $discount
     * @param bool $isAmount // 1 amount discount, 0 percent discount
     * @param int $minimumAmount
     * @param bool $minimumIncludeTax
     * @param bool $freeShipping
     * @return array
     */
    protected function _createDiscountRule(
        $code,
        $websiteId,
        $useCoupon,
        $useCustomer,
        $fromDate,
        $toDate,
        $discount,
        $isAmount,
        $minimumAmount,
        $minimumIncludeTax,
        $freeShipping
    ) {
        if ($this->_isDiscountAlreadyExist($code)) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Tried to create already existing code",
                $code . " code already exist",
                $this->_websiteId
            );
            return [[
                "success" => false,
                "status" => self::STATUS_DISCOUNT_ALREADY_EXIST
            ]];
        }

        $customerGroupIds = $this->getAvailableCustomerGroupIds();

        try {
            $isAmount ? $action = Rule::CART_FIXED_ACTION : $action = Rule::BY_PERCENT_ACTION;

            /** @var RuleInterface $cartPriceRule */
            $cartPriceRule = $this->_ruleInterfaceFactory->create();
            $cartPriceRule->setName(self::DISCOUNT_NAME)
                ->setDescription(self::DISCOUNT_DESCRIPTION)
                ->setCouponType(RuleInterface::COUPON_TYPE_SPECIFIC_COUPON)
                ->setCustomerGroupIds($customerGroupIds)
                ->setWebsiteIds([$websiteId])
                ->setIsActive(true)
                ->setUsesPerCoupon($useCoupon)
                ->setUsesPerCustomer($useCustomer)
                ->setSimpleAction($action)
                ->setDiscountAmount($discount)
                ->setSimpleFreeShipping(RuleInterface::FREE_SHIPPING_NONE)
                ->setStopRulesProcessing(false) // ?
            ;

            if ($freeShipping) {
                $cartPriceRule->setSimpleFreeShipping(FreeShippingOption::FREE_SHIPPING_ITEM);
            }

            if (!empty($minimumAmount)) {
                // base_subtotal_total_incl_tax doesn't exit in 2.3 ??
                $minimumIncludeTax ? $subtotalAttribute = "base_subtotal"
                    : $subtotalAttribute = "base_subtotal_with_discount";

                /** @var ConditionInterface $condition */
                $condition = $this->_conditionFactory->create();
                $condition->setConditionType(\Magento\SalesRule\Model\Rule\Condition\Address::class)
                    ->setAttributeName($subtotalAttribute)
                    ->setOperator(">=")
                    ->setValue($minimumAmount);

                /** @var ConditionInterface $combine */
                $combine = $this->_conditionFactory->create();
                $combine->setConditionType(\Magento\SalesRule\Model\Rule\Condition\Combine::class)
                    ->setAggregatorType('all')
                    ->setConditions([$condition])
                    ->setValue(true);

                $cartPriceRule->setCondition($combine);
            }

            /** @var RuleInterface $savedCartPriceRule */
            $savedCartPriceRule = $this->_ruleRepository->save($cartPriceRule);

            // set date on existing object to avoid 0000-00-00
            $needToSaveDate = false;
            if (!empty($fromDate)) {
                $savedCartPriceRule->setFromDate($fromDate);
                $needToSaveDate = true;
            }
            if (!empty($toDate)) {
                $savedCartPriceRule->setToDate($toDate);
                $needToSaveDate = true;
            }
            if ($needToSaveDate) {
                $savedCartPriceRule = $this->_ruleRepository->save($savedCartPriceRule);
            }

            $this->createCoupon($savedCartPriceRule->getRuleId(), $code);
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Error append while discount rule creation",
                $e->getMessage(),
                $this->_websiteId
            );
            return [[
                "success" => false,
                "code" => self::ERROR_CODE_CANNOT_CREATE_DISCOUNT,
                "status" => self::STATUS_ERROR
            ]];
        }

        return [["success" => true, "status" => self::STATUS_DISCOUNT_CREATED]];
    }

    protected function _isDiscountAlreadyExist($code) {
        return $this->_couponModel->loadByCode($code)->getRuleId();
    }

    protected function getAvailableCustomerGroupIds()
    {
        /** @var CustomerGroupCollection $collection */
        $collection = $this->_customerGroupCollectionFactory->create()
            ->addFieldToSelect('customer_group_id');

        return $collection->getAllIds();
    }

    /**
     * @param int $ruleId
     * @param string $code
     */
    protected function createCoupon($ruleId, $code) {
        /** @var CouponInterface $coupon */
        $coupon = $this->_couponFactory->create();
        $coupon->setCode($code)
            ->setIsPrimary(true)
            ->setRuleId($ruleId);

        try {
            $this->_couponRepository->save($coupon);
        } catch (\Exception $e) {
            // if coupon couldn't be create delete rule
            $this->_ruleRepository->deleteById($ruleId);

            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Couldn't create coupon code, initiate rollback",
                $e->getMessage(),
                $this->_websiteId
            );
        }
    }
}