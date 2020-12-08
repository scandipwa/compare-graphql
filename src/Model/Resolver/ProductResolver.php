<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Resolver;

use Magento\CatalogGraphQl\Model\ProductDataProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;

/**
 * Fetches the Product data according to the GraphQL schema
 */
class ProductResolver implements ResolverInterface
{
    /**
     * @var ProductDataProvider
     */
    private $productDataProvider;

    /**
     * @var MediaConfig
     */
    private $mediaConfig;

    /**
     * @param ProductDataProvider $productDataProvider
     */
    public function __construct(ProductDataProvider $productDataProvider, MediaConfig $mediaConfig)
    {
        $this->productDataProvider = $productDataProvider;
        $this->mediaConfig = $mediaConfig;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $data = [];

        if (isset($value['model'])) {
            $compareProductsOutput = $value['model'];
            foreach ($compareProductsOutput as $product) {
                $item = $this->productDataProvider->getProductDataById((int)$product->getProductId());

                $item['thumbnail'] = [
                    'url' => $this->mediaConfig->getMediaUrl($product->getThumbnail())
                ];

                $data[] = $item;
            }

            return $data;
        }

        return null;
    }
}
