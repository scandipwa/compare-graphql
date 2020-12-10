<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/compare-graphql
 * @link    https://github.com/scandipwa/quote-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Product\Compare\Item\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Visitor;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product\Compare\ListCompare;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ClearCompareProducts
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class ClearCompareProducts implements ResolverInterface
{
    /**
     * @var ListCompare
     */
    private $compareList;

    /**
     * @var Visitor
     */
    private $customerVisitor;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Item collection factory
     *
     * @var CollectionFactory
     */
    protected $itemCollectionFactory;

    /**
     * @param ListCompare $compareList
     * @param StoreManagerInterface $storeManager
     * @param Visitor $customerVisitor
     * @param Session $customerSession
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CollectionFactory $itemCollectionFactory
     */
    public function __construct(
        ListCompare $compareList,
        StoreManagerInterface $storeManager,
        Visitor $customerVisitor,
        Session $customerSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CollectionFactory $itemCollectionFactory
    ) {
        $this->compareList = $compareList;
        $this->storeManager = $storeManager;
        $this->customerVisitor = $customerVisitor;
        $this->customerSession = $customerSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->itemCollectionFactory = $itemCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (isset($args['guestCartId'])) {
            $quoteIdMask = $this->quoteIdMaskFactory
                ->create()
                ->load($args['guestCartId'], 'masked_id')
                ->getQuoteId();
            $this->customerVisitor->setId($quoteIdMask);
        } else {
            $customerId = (int)$context->getUserId();

            if ($customerId) {
                $this->customerSession->setCustomerId($customerId);
            } else {
                return false;
            }
        }

        $collection = $this->itemCollectionFactory->create();
        $collection->setVisitorId($this->customerVisitor->getId());

        if ($this->customerSession->isLoggedIn()) {
            $collection->setCustomerId($this->customerSession->getCustomerId());
        }

        $store = $this->storeManager->getStore();

        $collection->setStore($store);

        try {
            // This loads items but throws undefined method exception
            $collection->load();
        } catch (\Exception $e) {}

        $collection->clear();

        return true;
    }
}
