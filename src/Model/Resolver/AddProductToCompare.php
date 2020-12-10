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

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Catalog\Model\Product\Compare\ItemFactory;

/**
 * Class AddProductToCompare
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class AddProductToCompare implements ResolverInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var ItemFactory
     */
    private $compareItemFactory;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ItemFactory $compareItemFactory
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ItemFactory $compareItemFactory
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->compareItemFactory = $compareItemFactory;
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
        $productId = (int)$args['product_id'];
        $customerId = (int)$context->getUserId();
        $guestCardId = isset($args['guestCartId']) ? $args['guestCartId'] : null;

        if (!$productId || !($customerId || $guestCardId)) {
            return false;
        }

        $item = $this->compareItemFactory->create();

        if ($guestCardId) {
            $quoteIdMask = $this->quoteIdMaskFactory
                ->create()
                ->load($guestCardId, 'masked_id')
                ->getQuoteId();

            $item->addVisitorId($quoteIdMask);
        }

        if ($customerId) {
            $item->setCustomerId($customerId);
        }

        $item->loadByProduct($productId);

        if (!$item->getId()) {
            $item->addProductData($productId);
            $item->save();
        }

        return true;
    }
}
