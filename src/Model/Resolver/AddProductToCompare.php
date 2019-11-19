<?php
/**
 * ScandiPWA_CompareGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Resolver;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product\Compare;
use Magento\Catalog\Model\Product\Compare\ListCompare;
use Magento\Customer\Model\Session as SessionAlias;
use Magento\Customer\Model\Visitor as VisitorAlias;
use Magento\Quote\Model\QuoteIdMaskFactory;
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
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

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
     *
     * @param ProductRepositoryInterface $productRepository
     * @param ListCompare $listCompare
     * @param VisitorAlias $customerVisitor
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param SessionAlias $customerSession
     * @param StoreManagerInterfaceAlias $storeManager
     * @param ManagerInterfaceAlias $eventManager
     * @param ObjectManagerInterfaceAlias $objectManager
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ListCompare $listCompare,
        VisitorAlias $customerVisitor,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        SessionAlias $customerSession,
        StoreManagerInterfaceAlias $storeManager,
        ManagerInterfaceAlias $eventManager,
        ObjectManagerInterfaceAlias $objectManager
    ) {
        $this->productRepository = $productRepository;
        $this->listCompare = $listCompare;
        $this->customerVisitor = $customerVisitor;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
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
        if ($productId) {
            if (isset($args['guestCartId'])) {
                $quoteIdMask = $this->quoteIdMaskFactory
                    ->create()
                    ->load($args['guestCartId'], 'masked_id')
                    ->getQuoteId();
                $this->customerVisitor->setId($quoteIdMask);
            } else {
                if ($customerId) {
                    $this->customerSession->setCustomerId($customerId);
                } else {
                    return [];
                }
            }
        }
        $storeId = $this->storeManager->getStore()->getId();
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('We cannot add product to review right now'));
        }

        if ($product) {
            $this->listCompare->addProduct($product);
            $this->eventManager->dispatch('catalog_product_compare_add_product', ['product' => $product]);
        }

        $this->objectManager->get(Compare::class)->calculate();

        return $product->getData();
    }
}
