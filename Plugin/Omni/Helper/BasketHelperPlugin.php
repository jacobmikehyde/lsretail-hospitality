<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItemSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\SubLineType;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Helper\BasketHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;

/**
 * BasketHelperPlugin Plugin
 */
class BasketHelperPlugin
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * BasketHelper constructor.
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * @param BasketHelper $subject
     * @param Entity\OneList $list
     * @return Entity\OneList[]
     * @throws NoSuchEntityException
     */
    public function beforeSaveToOmni(BasketHelper $subject, Entity\OneList $list)
    {
        $industry = $subject->lsr->getCurrentIndustry();
        $list->setIsHospitality($industry == LSR::LS_INDUSTRY_VALUE_HOSPITALITY);
        return [$list];
    }

    /**
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param Quote $quote
     * @param Entity\OneList $oneList
     * @return mixed
     * @throws NoSuchEntityException|InvalidEnumException
     */
    public function aroundSetOneListQuote(
        BasketHelper $subject,
        callable $proceed,
        Quote $quote,
        Entity\OneList $oneList
    ) {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($quote, $oneList);
        }

        $quoteItems = $quote->getAllVisibleItems();
        if (count($quoteItems) == 0) {
            $subject->unSetCouponCode();
        }

        // @codingStandardsIgnoreLine
        $items = new Entity\ArrayOfOneListItem();

        $itemsArray = [];
        foreach ($quoteItems as $quoteItem) {
            // initialize the default null value
            $variant = $barcode = null;

            $sku = $quoteItem->getSku();

            $searchCriteria = $subject->searchCriteriaBuilder->addFilter('sku', $sku, 'eq')->create();

            $productList = $subject->productRepository->getList($searchCriteria)->getItems();

            $product = array_pop($productList);

            $barcode = $product->getData('barcode');

            /** TODO this is wrong, the data should be coming from lsr_uom not uom */
            $uom   = $product->getData('uom');
            $parts = explode('-', $sku);
            // first element is lsr_id
            $lsr_id = array_shift($parts);
            // second element, if it exists, is variant id
            $variant_id = count($parts) ? array_shift($parts) : null;
            if (!is_numeric($variant_id)) {
                $variant_id = null;
            }
            $oneListSubLinesArray = [];
            $selectedSubLines     = $this->hospitalityHelper->getSelectedOrderHospSubLineGivenQuoteItem($quoteItem);
            if (!empty($selectedSubLines['modifier'])) {
                foreach ($selectedSubLines['modifier'] as $subLine) {
                    $oneListSubLine         = (new Entity\OneListItemSubLine())
                        ->setModifierGroupCode($subLine['ModifierGroupCode'])
                        ->setModifierSubCode($subLine['ModifierSubCode'])
                        ->setQuantity(1)
                        ->setType(SubLineType::MODIFIER);
                    $oneListSubLinesArray[] = $oneListSubLine;
                }
            }
            if (!empty($selectedSubLines['recipe'])) {
                foreach ($selectedSubLines['recipe'] as $subLine) {
                    $oneListSubLine         = (new Entity\OneListItemSubLine())
                        ->setItemId($subLine['ItemId'])
                        ->setQuantity(0)
                        ->setType(SubLineType::MODIFIER);
                    $oneListSubLinesArray[] = $oneListSubLine;
                }
            }
            // @codingStandardsIgnoreLine
            $list_item    = (new Entity\OneListItem())
                ->setQuantity($quoteItem->getData('qty'))
                ->setItemId($lsr_id)
                ->setId('')
                ->setBarcodeId($barcode)
                ->setVariantId($variant_id)
                ->setUnitOfMeasureId($uom)
                ->setOnelistSubLines(
                    (new ArrayOfOneListItemSubLine())->setOneListItemSubLine($oneListSubLinesArray)
                );
            $itemsArray[] = $list_item;
        }
        $items->setOneListItem($itemsArray);

        $oneList->setItems($items)
            ->setPublishedOffers($subject->_offers());

        return $oneList;
    }

    /**
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param Entity\OneList $oneList
     * @return Entity\OneListCalculateResponse|Entity\OneListHospCalculateResponse|Entity\Order|Entity\OrderHosp|ResponseInterface|null
     * @throws NoSuchEntityException
     * @throws InvalidEnumException
     */
    public function aroundCalculate(BasketHelper $subject, callable $proceed, Entity\OneList $oneList)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($oneList);
        }
        // @codingStandardsIgnoreLine
        $storeId = $subject->getDefaultWebStore();
        $cardId  = $oneList->getCardId();

        /** @var Entity\ArrayOfOneListItem $oneListItems */
        $oneListItems = $oneList->getItems();

        /** @var Entity\OneListCalculateResponse $response */
        $response = false;

        if (!($oneListItems->getOneListItem() == null)) {
            /** @var Entity\OneListItem || Entity\OneListItem[] $listItems */
            $listItems = $oneListItems->getOneListItem();

            if (!is_array($listItems)) {
                /** Entity\ArrayOfOneListItem $items */
                // @codingStandardsIgnoreLine
                $items = new Entity\ArrayOfOneListItem();
                $items->setOneListItem($listItems);
                $listItems = $items;
            }
            // @codingStandardsIgnoreStart
            $oneListRequest = (new Entity\OneList())
                ->setCardId($cardId)
                ->setListType(Entity\Enum\ListType::BASKET)
                ->setItems($listItems)
                ->setStoreId($storeId)
                ->setIsHospitality($oneList->getIsHospitality());

            /** @var Entity\OneListCalculate $entity */
            if ($subject->getCouponCode() != "" and $subject->getCouponCode() != null) {
                $offer  = new Entity\OneListPublishedOffer();
                $offers = new Entity\ArrayOfOneListPublishedOffer();
                $offers->setOneListPublishedOffer($offer);
                $offer->setId($subject->getCouponCode());
                $offer->setType("Coupon");
                $oneListRequest->setPublishedOffers($offers);
            } else {
                $oneListRequest->setPublishedOffers($subject->_offers());
            }

            $entity  = new Entity\OneListHospCalculate();
            $request = new Operation\OneListHospCalculate();

            $entity->setOneList($oneListRequest);
            $response = $request->execute($entity);
        }
        if (($response == null)) {
            // @codingStandardsIgnoreLine
            $oneListCalResponse = new Entity\OneListCalculateResponse();
            return $oneListCalResponse->getResult();
        }
        if (property_exists($response, "OneListCalculateResult")) {
            // @codingStandardsIgnoreLine
            $subject->setOneListCalculationInCheckoutSession($response->getResult());
            return $response->getResult();
        }
        if (is_object($response)) {
            $subject->setOneListCalculationInCheckoutSession($response->getResult());
            return $response->getResult();
        } else {
            return $response;
        }
    }

    /**
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param $item
     * @return string
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundGetItemRowTotal(BasketHelper $subject, callable $proceed, $item)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($item);
        }
        $itemSku = explode("-", $item->getSku());
        $uom     = '';
        // @codingStandardsIgnoreLine
        if (count($itemSku) < 2) {
            $itemSku[1] = null;
        }
        $baseUnitOfMeasure = $item->getProduct()->getData('uom');
        // @codingStandardsIgnoreLine
        $uom        = $subject->itemHelper->getUom($itemSku, $baseUnitOfMeasure);
        $rowTotal   = "";
        $basketData = $subject->getOneListCalculation();
        $orderLines = $basketData->getOrderLines()->getOrderHospLine();
        foreach ($orderLines as $line) {
            if (
                $itemSku[0] == $line->getItemId() &&
                $itemSku[1] == $line->getVariantId() &&
                $uom == $line->getUomId() &&
                $this->hospitalityHelper->isSameAsSelectedLine($line, $item)
            ) {
                $rowTotal = $this->hospitalityHelper->getAmountGivenLine($line);
                break;
            }
        }
        return $rowTotal;
    }

    /**
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param $couponCode
     * @return Entity\OneListCalculateResponse|Entity\Order|Phrase|string
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws Exception
     */
    public function aroundSetCouponCode(BasketHelper $subject, callable $proceed, $couponCode)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($couponCode);
        }
        $couponCode = trim($couponCode);
        if ($couponCode == "") {
            $subject->couponCode = '';
            $subject->setCouponQuote("");
            $subject->update(
                $subject->get()
            );
            $subject->itemHelper->setDiscountedPricesForItems(
                $subject->checkoutSession->getQuote(),
                $subject->getBasketSessionValue()
            );

            return $status = '';
        }
        $subject->couponCode = $couponCode;
        $status              = $subject->update(
            $subject->get()
        );

        $checkCouponAmount = $subject->data->orderBalanceCheck(
            $subject->checkoutSession->getQuote()->getLsGiftCardNo(),
            $subject->checkoutSession->getQuote()->getLsGiftCardAmountUsed(),
            $subject->checkoutSession->getQuote()->getLsPointsSpent(),
            $status
        );

        if (!is_object($status) && $checkCouponAmount) {
            $subject->couponCode = '';
            $subject->update(
                $subject->get()
            );
            $subject->setCouponQuote($subject->couponCode);

            return $status;
        }
        foreach ($status->getOrderLines()->getOrderHospLine() as $basket) {
            $discountsLines = $basket->getDiscountLines();
            foreach ($discountsLines as $orderDiscountLine) {
                if ($orderDiscountLine->getDiscountType() == 'Coupon') {
                    $status = "success";
                    $subject->itemHelper->setDiscountedPricesForItems(
                        $subject->checkoutSession->getQuote(),
                        $subject->getBasketSessionValue()
                    );
                    $subject->setCouponQuote($subject->couponCode);
                    return $status;
                }
            }
        }
        $this->setCouponQuote("");
        return __("Coupon Code is not valid for these item(s)");
    }
}