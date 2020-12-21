<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/compare-graphql
 * @link    https://github.com/scandipwa/compare-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Resolver;

use Magento\Catalog\Model\Product\Compare\ListCompare;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product as ProductDataProvider;
use Magento\Framework\Api\SearchCriteriaBuilder;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use ScandiPWA\CompareGraphQl\Helper\Auth as AuthHelper;


/**
 * Class CompareProductsResolver
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class CompareProductsResolver implements ResolverInterface
{
    use ResolveInfoFieldsTrait;

    /**
     * @var ListCompare
     */
    protected $listCompare;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ProductDataProvider
     */
    private $productDataProvider;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var DataPostProcessor
     */
    private $postProcessor;

    /**
     * @var AuthHelper
     */
    protected $authHelper;

    /**
     * GetCartItems constructor.
     * @param ListCompare $listCompare
     * @param StoreManagerInterface $storeManager
     * @param ProductDataProvider $productDataProvider
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $postProcessor
     * @param AuthHelper $authHelper
     */
    public function __construct(
        ListCompare $listCompare,
        StoreManagerInterface $storeManager,
        ProductDataProvider $productDataProvider,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DataPostProcessor $postProcessor,
        AuthHelper $authHelper
    ) {
        $this->listCompare = $listCompare;
        $this->storeManager = $storeManager;
        $this->productDataProvider = $productDataProvider;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->postProcessor = $postProcessor;
        $this->authHelper = $authHelper;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $customerId = (int)$context->getUserId();
        $guestCartId = $this->authHelper->getGuestCartId($args);

        if (!$customerId && !$guestCartId) {
            return null;
        }

        $collection = $this->listCompare->getItemCollection();

        $this->authHelper->setAuthData(
            $collection,
            $customerId,
            $guestCartId
        );

        $store = $this->storeManager->getStore();
        $collection->setStore($store);

        try {
            // This loads items but throws undefined method exception which can be ignored
            $collection->load();
        } catch (\Exception $e) {}

        $count = $collection->count();
        $products = [];

        if ($count) {
            $path = 'compareProducts/products';
            $attributeCodes = $this->getFieldsFromProductInfo($info, $path);

            $productIds = $collection->getProductIds();
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $productIds, 'in')
                ->create();

            $productItems = $this->productDataProvider
                ->getList($searchCriteria, $attributeCodes)
                ->getItems();

            $products = $this->postProcessor->process($productItems, $path, $info, [
                'isSingleProduct' => false,
                'isCompare' => true
            ]);
        }

        return [
            'count' => $count,
            'products' => $products
        ];
    }
}
