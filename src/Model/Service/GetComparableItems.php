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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\CompareListGraphQl\Model\Service\GetComparableItems as SourceGetComparableItems;
use Magento\Catalog\Helper\Image;

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
     * @param ListCompare $listCompare
     * @param ComparableItemsCollection $comparableItemsCollection
     * @param ProductRepository $productRepository
     * @param Image $_imageBuilder
     */
    public function __construct(
        ListCompare $listCompare,
        ComparableItemsCollection $comparableItemsCollection,
        ProductRepository $productRepository,
        Image $_imageBuilder
    ) {
        parent::__construct($listCompare, $comparableItemsCollection, $productRepository);

        $this->blockListCompare = $listCompare;
        $this->comparableItemsCollection = $comparableItemsCollection;
        $this->productRepository = $productRepository;
        $this->_imageBuilder=$_imageBuilder;
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
            $productData['model'] = $item;
            $productData['thumbnail'] = [
                'path' => $imagePath,
                'url' => $this->getImageUrl('thumbnail', $imagePath, $item)
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
        if (!isset($imagePath)) {
            return $this->_imageBuilder->getDefaultPlaceholderUrl($imageType);
        }

        $imageId = sprintf('scandipwa_%s', $imageType);

        return $this->_imageBuilder
            ->init(
                $product,
                $imageId,
                ['type' => $imageType]
            )
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false)
            ->getUrl();
    }
}
