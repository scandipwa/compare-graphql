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
use Magento\CatalogGraphQl\Model\ProductDataProvider;
use \Magento\Catalog\Model\Product\Media\Config as MediaConfig;


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
     * @var ProductDataProvider
     */
    private $productDataProvider;

    /**
     * @var MediaConfig
     */
    private $mediaConfig;

    /**
     * GetCartItems constructor.
     * @param ListCompare $listCompare
     * @param StoreManagerInterfaceAlias $storeManager
     * @param ConfigAlias $catalogConfig
     * @param VisibilityAlias $catalogProductVisibility
     * @param Visitor $customerVisitor
     * @param Session $customerSession
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ProductDataProvider $productDataProvider
     * @param MediaConfig $mediaConfig
     */
    public function __construct(
        ListCompare $listCompare,
        StoreManagerInterfaceAlias $storeManager,
        ConfigAlias $catalogConfig,
        VisibilityAlias $catalogProductVisibility,
        Visitor $customerVisitor,
        Session $customerSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ProductDataProvider $productDataProvider,
        MediaConfig $mediaConfig
    ) {
        $this->listCompare = $listCompare;
        $this->storeManager = $storeManager;
        $this->catalogConfig = $catalogConfig;
        $this->catalogProductVisibility = $catalogProductVisibility;
        $this->customerVisitor = $customerVisitor;
        $this->customerSession = $customerSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->productDataProvider = $productDataProvider;
        $this->mediaConfig = $mediaConfig;
    }

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
            return null;
        }

        $collection = $this->listCompare->getItemCollection();

        if ($customerId) {
            $collection->setCustomerId($customerId);
        } elseif ($guestCardId) {
            $quoteIdMask = $this->quoteIdMaskFactory
                ->create()
                ->load($guestCardId, 'masked_id')
                ->getQuoteId();

            $collection->setVisitorId($quoteIdMask);
        }

        $store = $this->storeManager->getStore();
        $collection->setStore($store);

        try {
            // This loads items but throws undefined method exception which can be ignored
            $collection->load();
        } catch (\Exception $e) {}

        $count = $collection->count();
        $products = [];

        if ($count) {
            $productIds = $collection->getProductIds();

            foreach ($productIds as $productId) {
                $item = $this->productDataProvider->getProductDataById((int)$productId);

                if (isset($item['thumbnail']) && !empty($item['thumbnail'])) {
                    $item['thumbnail'] = [
                        'path' => $item['thumbnail'],
                        'url' => $this->mediaConfig->getMediaUrl($item['thumbnail']),
                    ];
                }

                $products[] = $item;
            }
        }

        return [
            'count' => $count,
            'products' => $products
        ];
    }
}
