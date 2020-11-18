<?php

namespace Ls\Hospitality\Helper;

use \Ls\Omni\Client\Ecommerce\Entity\OrderHospLine;
use \Ls\Replication\Model\ReplItemModifierRepository;
use Magento\Catalog\Helper\Product\Configuration;
use Magento\Catalog\Model\Product\Interceptor;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Useful helper functions for Hospitality
 *
 */
class HospitalityHelper extends AbstractHelper
{

    /** @var ProductRepository $productRepository */
    public $productRepository;

    /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
    public $searchCriteriaBuilder;

    /**
     * @var Configuration
     */
    public $configurationHelper;

    /**
     * @var ReplItemModifierRepository
     */
    public $itemModifierRepository;

    /**
     * HospitalityHelper constructor.
     * @param Context $context
     * @param Configuration $configurationHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductRepository $productRepository
     * @param ReplItemModifierRepository $itemModifierRepository
     */
    public function __construct(
        Context $context,
        Configuration $configurationHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepository $productRepository,
        ReplItemModifierRepository $itemModifierRepository
    ) {
        parent::__construct($context);
        $this->configurationHelper    = $configurationHelper;
        $this->searchCriteriaBuilder  = $searchCriteriaBuilder;
        $this->productRepository      = $productRepository;
        $this->itemModifierRepository = $itemModifierRepository;
    }

    /**
     * @param $quoteItem
     * @return array
     */
    public function getItemModifierGivenQuoteItem($quoteItem)
    {
        $sku = $quoteItem->getSku();

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('sku', $sku, 'eq')->create();

        $productList = $this->productRepository->getList($searchCriteria)->getItems();

        /** @var Interceptor $product */
        $product = array_pop($productList);

        $uom                        = $product->getData('uom');
        $itemSku                    = explode("-", $sku);
        $lsrId                      = $itemSku[0];
        $selectedOptionsOfQuoteItem = $this->configurationHelper->getCustomOptions($quoteItem);
        $selectedOrderHospSubLine   = [];
        foreach ($selectedOptionsOfQuoteItem as $option) {
            $itemSubLineCode = $this->getItemSubLineCode($option['label']);
            $decodedValue    = htmlspecialchars_decode($option['value']);
            foreach (array_map('trim', explode(',', $decodedValue)) as $optionValue) {
                $itemModifier = $this->getItemModifier(
                    $lsrId,
                    $itemSubLineCode,
                    $optionValue,
                    $uom
                );
                if (!empty($itemModifier)) {
                    $subCode                    = reset($itemModifier)->getSubCode();
                    $selectedOrderHospSubLine[] =
                        ['ModifierGroupCode' => $itemSubLineCode, 'ModifierSubCode' => $subCode];
                }
            }
        }
        return $selectedOrderHospSubLine;
    }

    /**
     * @param OrderHospLine $line
     * @return float|null
     */
    public function getAmountGivenLine(OrderHospLine $line)
    {
        $amount = $line->getAmount();
        foreach ($line->getSubLines() as $subLine) {
            $amount += $subLine->getAmount();
        }
        return $amount;
    }

    /**
     * @param OrderHospLine $line
     * @return float|null
     */
    public function getPriceGivenLine(OrderHospLine $line)
    {
        $price = $line->getPrice();
        foreach ($line->getSubLines() as $subLine) {
            $price += $subLine->getPrice();
        }
        return $price;
    }

    /**
     * @param OrderHospLine $line
     * @param $item
     * @return bool
     */
    public function isSameAsSelectedLine(OrderHospLine $line, $item)
    {
        $selectedOrderHospSubLine = $this->getItemModifierGivenQuoteItem($item);
        if (count($selectedOrderHospSubLine) != count($line->getSubLines()->getOrderHospSubLine())) {
            return false;
        }
        foreach ($line->getSubLines() as $omniSubLine) {
            $found = false;
            foreach ($selectedOrderHospSubLine as $quoteSubLine) {
                $found = false;
                if ($omniSubLine->getModifierGroupCode() == $quoteSubLine['ModifierGroupCode'] &&
                    $omniSubLine->getModifierSubCode() == $quoteSubLine['ModifierSubCode']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $label
     * @return mixed|string
     */
    public function getItemSubLineCode($label)
    {
        $subString = explode('ls_mod_', $label);
        return strtoupper(str_replace("_", " ", end($subString)));
    }

    /**
     * @param $navId
     * @param $code
     * @param $value
     * @param $uom
     * @return mixed
     */
    public function getItemModifier($navId, $code, $value, $uom)
    {
        $itemModifier = $this->itemModifierRepository->getList(
            $this->searchCriteriaBuilder->addFilter('nav_id', $navId)
                ->addFilter('Code', $code)
                ->addFilter('Description', $value)
                ->addFilter('UnitOfMeasure', $uom)
                ->setPageSize(1)->setCurrentPage(1)
                ->create()
        );
        return $itemModifier->getItems();
    }
}
