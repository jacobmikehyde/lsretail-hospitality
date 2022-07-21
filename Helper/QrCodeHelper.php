<?php

namespace Ls\Hospitality\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Model\ResourceModel\ReplStore\CollectionFactory;
use Magento\Customer\Model\Session\Proxy;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * QR code operation helper
 */
class QrCodeHelper extends AbstractHelper
{
    /**
     * @var Proxy
     */
    public $customerSession;

    /**
     * @var CollectionFactory
     */
    public $storeCollection;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @param Proxy $customerSession
     * @param CollectionFactory $storeCollection
     * @param LSR $lsr
     * @param Context $context
     */
    public function __construct(
        Proxy $customerSession,
        CollectionFactory $storeCollection,
        LSR $lsr,
        Context $context
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->lsr             = $lsr;
        $this->storeCollection = $storeCollection;
    }

    /**
     * Decrypt the parameters pass to QR ordering
     *
     * @param $params
     * @return false|string
     */
    public function decrypt($params)
    {
        return base64_decode($params);
    }

    /**
     * Set QR code ordering data
     *
     * @param $params
     */
    public function setQrCodeOrderingInSession($params)
    {
        $this->customerSession->setData(LSR::LS_QR_CODE_ORDERING, $params);
    }

    /**
     * validate store id
     *
     * @param $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function validateStoreId($storeId)
    {
        $check      = false;
        $collection = $this->storeCollection
            ->create()
            ->addFieldToFilter('scope_id', $this->lsr->getCurrentStoreId())
            ->addFieldToFilter('nav_id', $storeId);
        if ($collection->getSize() > 0) {
            $check = true;
        }

        return $check;
    }
}