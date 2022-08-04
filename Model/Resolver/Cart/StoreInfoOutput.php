<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Cart;

use \Ls\Omni\Helper\Data as DataHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Zend_Log_Exception;

class StoreInfoOutput implements ResolverInterface
{
    /**
     * @var DataHelper
     */
    public $dataHelper;

    /**
     * @param DataHelper $dataHelper
     */
    public function __construct(
        DataHelper $dataHelper,
        CheckoutSession $checkoutSession
    ) {
        $this->dataHelper       = $dataHelper;
        $this->checkoutSession  = $checkoutSession;
    }

    /**
     * Add proper swatch image path
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     *
     * @return array
     *
     * @throws NoSuchEntityException|Zend_Log_Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {

        $storeInfo = [];
        $cart   = $this->checkoutSession->getQuote();
        $pickupStoreId = $cart->getPickupStore();
        $pickupStoreName = ($pickupStoreId) ? $this->dataHelper->getStoreNameById($pickupStoreId) : "";

        if ($pickupStoreId && $cart->getShippingAddress()->getShippingMethod() == "clickandcollect_clickandcollect") {
            $storeInfo["store_id"] = $pickupStoreId;
            $storeInfo["store_name"] = $pickupStoreName;
            $storeInfo["pickup_date_timeslot"] = ($cart->getPickupDateTimeslot()) ? $cart->getPickupDateTimeslot() : "";
        }

        return $storeInfo;
    }
}
