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
use Magento\Catalog\Model\Product\Compare\ListCompare;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Visitor;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Catalog\Model\Product\Compare\ItemFactory;

/**
 * Class AddProductToCompare
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class AddProductToCompare implements ResolverInterface
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
     * @var ItemFactory
     */
    private $compareItemFactory;

    /**
     * @param ListCompare $compareList
     * @param Visitor $customerVisitor
     * @param Session $customerSession
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ItemFactory $compareItemFactory
     */
    public function __construct(
        ListCompare $compareList,
        Visitor $customerVisitor,
        Session $customerSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ItemFactory $compareItemFactory
    ) {
        $this->compareList = $compareList;
        $this->customerVisitor = $customerVisitor;
        $this->customerSession = $customerSession;
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

        $productId = (int)$args['product_id'];

        if (!$productId) {
            return false;
        }

        $item = $this->compareItemFactory->create();
        $item->addVisitorId($this->customerVisitor->getId());

        if ($this->customerSession->isLoggedIn()) {
            $item->setCustomerId($this->customerSession->getCustomerId());
        }

        $item->loadByProduct($productId);

        if (!$item->getId()) {
            $item->addProductData($productId);
            $item->save();
        }

        return true;
    }
}
