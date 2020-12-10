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

use Magento\Catalog\Model\Config as ConfigAlias;
use Magento\Catalog\Model\Product\Compare\ListCompare;
use Magento\Catalog\Model\Product\Visibility as VisibilityAlias;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterfaceAlias;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Visitor;
use Magento\Quote\Model\QuoteIdMaskFactory;


/**
 * Class CompareProductsResolver
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class CompareProductsResolver implements ResolverInterface
{

    /**
     * @var ListCompare
     */
    protected $listCompare;

    /**
     * @var StoreManagerInterfaceAlias
     */
    protected $storeManager;

    /**
     * Catalog config
     *
     * @var ConfigAlias
     */
    protected $catalogConfig;

    /**
     * Catalog product visibility
     *
     * @var VisibilityAlias
     */
    protected $catalogProductVisibility;

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
     * GetCartItems constructor.
     * @param ListCompare $listCompare
     * @param StoreManagerInterfaceAlias $storeManager
     * @param ConfigAlias $catalogConfig
     * @param VisibilityAlias $catalogProductVisibility
     * @param Visitor $customerVisitor
     * @param Session $customerSession
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        ListCompare $listCompare,
        StoreManagerInterfaceAlias $storeManager,
        ConfigAlias $catalogConfig,
        VisibilityAlias $catalogProductVisibility,
        Visitor $customerVisitor,
        Session $customerSession,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->listCompare = $listCompare;
        $this->storeManager = $storeManager;
        $this->catalogConfig = $catalogConfig;
        $this->catalogProductVisibility = $catalogProductVisibility;
        $this->customerVisitor = $customerVisitor;
        $this->customerSession = $customerSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

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

//        $customerId = (int)$context->getUserId();
        $storeId = $this->storeManager->getStore()->getId();
        $collection = $this->listCompare->getItemCollection();

        $collection->setVisitorId($this->customerVisitor->getId());

        if ($this->customerSession->isLoggedIn()) {
            $collection->setCustomerId($this->customerSession->getCustomerId());
        }

        $collection->setStoreId($storeId);

//        if (isset($args['guestCartId'])) {
//            $collection->setVisitorId($args['guestCartId']);
//        } else {
//            if ($customerId) {
//                $collection->setCustomerId($customerId);
//            } else {
//                return [];
//            }
//        }

        $collection->addAttributeToSelect(
            $this->catalogConfig->getProductAttributes()
        )->loadComparableAttributes()->addMinimalPrice()->addTaxPercents()->setVisibility(
            $this->catalogProductVisibility->getVisibleInSiteIds()
        );

        try {
            $collection->load();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $count = $collection->count();
        $products = [];
        $model = [];

        if ($count > 0) {
            $products = $collection->getData();
            $model = $collection->getItems();
        }

        return [
            'count' => $count,
            'products' => $products,
            'model' => $model
        ];
    }
}


