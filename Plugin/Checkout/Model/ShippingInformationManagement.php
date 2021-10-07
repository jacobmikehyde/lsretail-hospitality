<?php

namespace Ls\Hospitality\Plugin\Checkout\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;

/**
 * Class ShippingInformationManagement for service mode
 */
class ShippingInformationManagement
{
    /** @var QuoteRepository */
    public $quoteRepository;

    /**
     * ShippingInformationManagement constructor.
     * @param QuoteRepository $quoteRepository
     */

    public function __construct(
        QuoteRepository $quoteRepository
    ) {
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param $cartId
     * @param ShippingInformationInterface $addressInformation
     * @throws NoSuchEntityException
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $extAttributes      = $addressInformation->getExtensionAttributes();
        $serviceMode        = $extAttributes->getServiceMode();
        $pickupDate         = $extAttributes->getPickupDate();
        $pickupTimeslot     = $extAttributes->getPickupTimeslot();
        $pickupDateTimeslot = $pickupDate . ' ' . $pickupTimeslot;
        $pickupDateTimeslot = date('Y-m-d h:i A', strtotime($pickupDateTimeslot));
        $quote              = $this->quoteRepository->getActive($cartId);
        $quote->setServiceMode($serviceMode);
        $quote->setPickupDateTimeslot($pickupDateTimeslot);
    }
}
