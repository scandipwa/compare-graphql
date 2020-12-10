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
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ClearCompareProducts
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class ClearCompareProducts implements ResolverInterface
{
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
     * @param StoreManagerInterface $storeManager
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CollectionFactory $itemCollectionFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CollectionFactory $itemCollectionFactory
    ) {
        $this->storeManager = $storeManager;
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
        $customerId = (int)$context->getUserId();
        $guestCardId = isset($args['guestCartId']) ? $args['guestCartId'] : null;

        if (!$customerId && !$guestCardId) {
            return false;
        }

        $collection = $this->itemCollectionFactory->create();

        if ($guestCardId) {
            $quoteIdMask = $this->quoteIdMaskFactory
                ->create()
                ->load($guestCardId, 'masked_id')
                ->getQuoteId();

            $collection->setVisitorId($quoteIdMask);
        }

        if ($customerId) {
            $collection->setCustomerId($customerId);
        }

        $store = $this->storeManager->getStore();
        $collection->setStore($store);

        try {
            // This loads items but throws undefined method exception which can be ignored
            $collection->load();
        } catch (\Exception $e) {}

        $collection->clear();

        return true;
    }
}
