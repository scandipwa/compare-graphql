<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Service;

use Magento\Catalog\Block\Product\Compare\ListCompare;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\CompareListGraphQl\Model\Service\Collection\GetComparableItemsCollection as ComparableItemsCollection;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\CompareListGraphQl\Model\Service\GetComparableItems as SourceGetComparableItems;
use Magento\Catalog\Helper\Image;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Get products compare list
 */
class GetComparableItems extends SourceGetComparableItems
{
    /**
     * @var ListCompare
     */
    private $blockListCompare;

    /**
     * @var ComparableItemsCollection
     */
    private $comparableItemsCollection;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var Image
     */
    protected $_imageBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Emulation
     */
    protected $emulation;

    /**
     * @param ListCompare $listCompare
     * @param ComparableItemsCollection $comparableItemsCollection
     * @param ProductRepository $productRepository
     * @param Image $_imageBuilder
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     */
    public function __construct(
        ListCompare $listCompare,
        ComparableItemsCollection $comparableItemsCollection,
        ProductRepository $productRepository,
        Image $_imageBuilder,
        StoreManagerInterface $storeManager,
        Emulation $emulation
    ) {
        parent::__construct($listCompare, $comparableItemsCollection, $productRepository);

        $this->blockListCompare = $listCompare;
        $this->comparableItemsCollection = $comparableItemsCollection;
        $this->productRepository = $productRepository;
        $this->_imageBuilder=$_imageBuilder;
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
    }

    /**
     * Get comparable items
     *
     * @param int $listId
     * @param ContextInterface $context
     *
     * @return array
     * @throws GraphQlInputException
     */
    public function execute(int $listId, ContextInterface $context)
    {
        $items = [];
        foreach ($this->comparableItemsCollection->execute($listId, $context) as $item) {
            /** @var Product $item */
            $items[] = [
                'uid' => $item->getId(),
                'product' => $this->getProductData((int)$item->getId()),
                'attributes' => $this->getProductComparableAttributes($listId, $item, $context)
            ];
        }

        return $items;
    }

    /**
     * Get product data
     *
     * @param int $productId
     * @return array
     * @throws GraphQlInputException
     */
    private function getProductData(int $productId): array
    {
        $productData = [];
        try {
            $item = $this->productRepository->getById($productId);
            $imagePath = $item->getData('thumbnail');

            $productData = $item->getData();
            $productData['entity_id'] = $item->getId();
            $productData['model'] = $item;
            $productData['stock_item'] = [];
            $productData['stock_status'] = $item['quantity_and_stock_status']['is_in_stock'] ? 'IN_STOCK' : 'OUT_OF_STOCK';
            $productData['categories'] = [];
            $productData['attributes'] = [];
            $productData['tier_prices'] = [];
            $productData['thumbnail'] = [
                'path' => $imagePath,
                'url' => $this->getImageUrl('thumbnail', $imagePath, $item)
            ];
            $productData['small_image'] = [
                'path' => $imagePath,
                'url' => $this->getImageUrl('small_image', $imagePath, $item)
            ];
            $productData['image'] = [
                'path' => $imagePath,
                'url' => $this->getImageUrl('image', $imagePath, $item)
            ];
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }

        return $productData;
    }

    /**
     * Get comparable attributes for product
     *
     * @param int $listId
     * @param Product $product
     * @param ContextInterface $context
     *
     * @return array
     */
    private function getProductComparableAttributes(int $listId, Product $product, ContextInterface $context): array
    {
        $attributes = [];
        $itemsCollection = $this->comparableItemsCollection->execute($listId, $context);
        foreach ($itemsCollection->getComparableAttributes() as $item) {
            $attributes[] = [
                'code' =>  $item->getAttributeCode(),
                'value' => $this->blockListCompare->getProductAttributeValue($product, $item)
            ];
        }

        return $attributes;
    }

    /**
     * @param string $imageType
     * @param string|null $imagePath
     * @param $product
     * @return string
     */
    protected function getImageUrl(
        string $imageType,
        ?string $imagePath,
        $product
    ): string {
        $storeId = $this->storeManager->getStore()->getId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        if (!isset($imagePath) || $imagePath == 'no_selection') {
            $imageUrl = $this->_imageBuilder->getDefaultPlaceholderUrl($imageType);
            $this->emulation->stopEnvironmentEmulation();
            return $imageUrl;
        }

        $imageId = sprintf('scandipwa_%s', $imageType);

        $image = $this->_imageBuilder
            ->init(
                $product,
                $imageId,
                ['type' => $imageType]
            )
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false);

        $this->emulation->stopEnvironmentEmulation();

        return $image->getUrl();
    }
}
