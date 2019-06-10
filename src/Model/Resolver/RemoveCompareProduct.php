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
use Magento\Catalog\Model\Product\Compare\ItemFactory as ItemFactoryAlias;
use Magento\Customer\Model\Session as SessionAlias;
use Magento\Customer\Model\Visitor as VisitorAlias;
use Magento\Framework\Event\ManagerInterface as ManagerInterfaceAlias;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterfaceAlias;


/**
 * Class RemoveCompareProduct
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class RemoveCompareProduct implements ResolverInterface
{

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * Customer visitor
     *
     * @var VisitorAlias
     */
    protected $_customerVisitor;

    /**
     * Customer session
     *
     * @var SessionAlias
     */
    protected $_customerSession;

    /**
     * @var StoreManagerInterfaceAlias
     */
    protected $_storeManager;

    /**
     * @var ManagerInterfaceAlias
     */
    protected $_eventManager;

    /**
     * Compare item factory
     *
     * @var ItemFactoryAlias
     */
    protected $compareItemFactory;

    /**
     * GetCartItems constructor.
     * @param ProductRepositoryInterface $productRepository
     * @param VisitorAlias $_customerVisitor
     * @param SessionAlias $_customerSession
     * @param StoreManagerInterfaceAlias $_storeManager
     * @param ManagerInterfaceAlias $_eventManager
     * @param ItemFactoryAlias $compareItemFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        VisitorAlias $_customerVisitor,
        SessionAlias $_customerSession,
        StoreManagerInterfaceAlias $_storeManager,
        ManagerInterfaceAlias $_eventManager,
        ItemFactoryAlias $compareItemFactory
    ) {
        $this->productRepository = $productRepository;
        $this->_customerVisitor = $_customerVisitor;
        $this->_customerSession = $_customerSession;
        $this->_storeManager = $_storeManager;
        $this->_eventManager = $_eventManager;
        $this->compareItemFactory = $compareItemFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $customerId = (int)$context->getUserId();

        if (!isset($args['product_sku'])) {
            throw new GraphQlInputException(__('Please specify valid product'));
        }

        $product = $this->productRepository->get($args['product_sku']);

        $productId = (int)$product->getId();

        if ($productId) {
            $storeId = $this->_storeManager->getStore()->getId();
            try {
                $product = $this->productRepository->getById($productId, false, $storeId);
            } catch (NoSuchEntityException $e) {
                $product = null;
            }

            if ($product) {
                $item = $this->compareItemFactory->create();

                if (isset($args['guestCartId'])) {
                    $this->_customerVisitor->setId($args['guestCartId']);
                } else {
                    if ($customerId) {
                        $this->_customerSession->setCustomerId($customerId);
                    } else {
                        return false;
                    }
                }

                $item->loadByProduct($product);

                if ($item->getId()) {
                    $item->delete();
                    $this->_eventManager->dispatch('catalog_product_compare_remove_product', ['product' => $item]);

                    return true;
                } else {

                    return false;
                }
            }
        }
    }
}


