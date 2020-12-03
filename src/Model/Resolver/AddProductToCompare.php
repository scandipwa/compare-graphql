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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product\Compare;
use Magento\Catalog\Model\Product\Compare\ListCompare;
use Magento\Customer\Model\Session as SessionAlias;
use Magento\Customer\Model\Visitor as VisitorAlias;
use Magento\Framework\Event\ManagerInterface as ManagerInterfaceAlias;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\ObjectManagerInterface as ObjectManagerInterfaceAlias;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterfaceAlias;

/**
 * Class AddProductToCompare
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class AddProductToCompare implements ResolverInterface
{

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var ListCompare
     */
    protected $listCompare;

    /**
     * Customer visitor
     *
     * @var VisitorAlias
     */
    protected $customerVisitor;

    /**
     * Customer session
     *
     * @var SessionAlias
     */
    protected $customerSession;

    /**
     * @var StoreManagerInterfaceAlias
     */
    protected $storeManager;

    /**
     * @var ManagerInterfaceAlias
     */
    protected $eventManager;

    /**
     * @var ObjectManagerInterfaceAlias
     */
    protected $objectManager;

    /**
     * GetCartItems constructor.
     * @param ProductRepositoryInterface $productRepository
     * @param ListCompare $listCompare
     * @param VisitorAlias $customerVisitor
     * @param SessionAlias $customerSession
     * @param StoreManagerInterfaceAlias $storeManager
     * @param ManagerInterfaceAlias $eventManager
     * @param ObjectManagerInterfaceAlias $objectManager
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ListCompare $listCompare,
        VisitorAlias $customerVisitor,
        SessionAlias $customerSession,
        StoreManagerInterfaceAlias $storeManager,
        ManagerInterfaceAlias $eventManager,
        ObjectManagerInterfaceAlias $objectManager
    ) {
        $this->productRepository = $productRepository;
        $this->listCompare = $listCompare;
        $this->customerVisitor = $customerVisitor;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->objectManager = $objectManager;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     * @throws NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $customerId = $context->getUserId();

        if (!isset($args['product_sku'])) {
            throw new GraphQlInputException(__('Please specify valid product'));
        }

        $product = $this->productRepository->get($args['product_sku']);
        $productId = (int)$product->getId();

        if ($productId && (isset($args['guestCartId']) || $customerId)) {
            if ($customerId) {
                $this->customerSession->setCustomerId($customerId);
            } else {
                if (isset($args['guestCartId'])) {
                    $this->customerVisitor->setId($args['guestCartId']);
                } else {
                    return false;
                }
            }
        }
        $storeId = $this->storeManager->getStore()->getId();
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('We cannot add product to review right now'));
        }

        $result = false;

        if ($product) {
            $this->listCompare->addProduct($product);
            $this->eventManager->dispatch('catalog_product_compare_add_product', ['product' => $product]);
            $result = true;
        }

        $this->objectManager->get(Compare::class)->calculate();

        return $result;
    }
}
