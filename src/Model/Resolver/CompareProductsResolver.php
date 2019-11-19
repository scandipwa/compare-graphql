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

use Exception;
use Magento\CatalogGraphQl\Model\ProductDataProvider;
use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use ScandiPWA\CompareGraphQl\Helper\Configurable as ConfigurableHelper;
use Magento\Catalog\Model\Config as ConfigAlias;
use Magento\Catalog\Model\Product\Compare\ListCompare;
use Magento\Catalog\Model\Product\Visibility as VisibilityAlias;
use Magento\Quote\Model\QuoteIdMaskFactory;
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
     * @var ProductDataProvider
     */
    private $productDataProvider;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var ConfigurableHelper
     */
    protected $configurableHelper;

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
     * @param ProductDataProvider $productDataProvider
     * @param ConfigurableHelper $configurableHelper
     * @param ListCompare $listCompare
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param StoreManagerInterfaceAlias $storeManager
     * @param ConfigAlias $catalogConfig
     * @param VisibilityAlias $catalogProductVisibility
     */
    public function __construct(
        ProductDataProvider $productDataProvider,
        ConfigurableHelper $configurableHelper,
        ListCompare $listCompare,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        StoreManagerInterfaceAlias $storeManager,
        ConfigAlias $catalogConfig,
        VisibilityAlias $catalogProductVisibility
    ) {
        $this->productDataProvider = $productDataProvider;
        $this->configurableHelper = $configurableHelper;
        $this->listCompare = $listCompare;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->storeManager = $storeManager;
        $this->catalogConfig = $catalogConfig;
        $this->catalogProductVisibility = $catalogProductVisibility;
    }

    /**
     * Returns the products present in compare list.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws Exception
     */
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
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($args['guestCartId'], 'masked_id')->getQuoteId();

            $collection->setVisitorId($quoteIdMask);
        } else {
            if ($customerId) {
                $collection->setCustomerId($customerId);
            } else {
                return [];
            }
        }

        $collection
            ->useProductItem()
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addAttributeToSelect('status')
            ->loadComparableAttributes()
            ->addMinimalPrice()
            ->addTaxPercents();

        $this->configurableHelper->getConfigurableOptions(
            $collection,
            ConfigurableHelper::DEFAULT_ID_FIELD,
            ConfigurableHelper::DEFAULT_FIELDS,
            true
        );

        try {
            $collection->load();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }

        if ($collection->count() === 0) {
            return [];
        }

        /** @var Product[] $items */
        $items = $collection->getItems();
        $productData = $collection->getData();

        foreach ($productData as $key => $product) {
            $id = $product['entity_id'];

            $productData[$key] = array_merge(
                $productData[$key],
                $this->productDataProvider->getProductDataById((int)$id)
            );

            $productData[$key]['model'] = $items[$id];
        }



        return [
            'products' => $productData,
            'count' => count($productData),
            'model' => $items
        ];
    }
}
