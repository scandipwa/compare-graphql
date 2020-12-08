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
     * GetCartItems constructor.
     * @param ListCompare $listCompare
     * @param StoreManagerInterfaceAlias $storeManager
     * @param ConfigAlias $catalogConfig
     * @param VisibilityAlias $catalogProductVisibility
     */
    public function __construct(
        ListCompare $listCompare,
        StoreManagerInterfaceAlias $storeManager,
        ConfigAlias $catalogConfig,
        VisibilityAlias $catalogProductVisibility
    ) {
        $this->listCompare = $listCompare;
        $this->storeManager = $storeManager;
        $this->catalogConfig = $catalogConfig;
        $this->catalogProductVisibility = $catalogProductVisibility;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {

        $customerId = (int)$context->getUserId();
        $storeId = $this->storeManager->getStore()->getId();
        $collection = $this->listCompare->getItemCollection();

        $collection->setStoreId($storeId);

        if (isset($args['guestCartId'])) {
            $collection->setVisitorId($args['guestCartId']);
        } else {
            if ($customerId) {
                $collection->setCustomerId($customerId);
            } else {
                return [];
            }
        }

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


