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

use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Swatches\Helper\Data;
use Magento\Catalog\Model\ProductRepository;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Catalog\Model\Product;

/**
 * Class ComparableAttributes
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class ComparableAttributesResolver implements ResolverInterface
{
    /**
     * @var Data
     */
    protected $swatchHelper;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * CustomAttributes constructor.
     *
     * @param Data $swatchHelper
     * @param ProductRepository $productRepository
     */
    public function __construct(
        Data $swatchHelper,
        ProductRepository $productRepository
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->productRepository = $productRepository;
    }

    /**
     * Fetches the data from persistence models and formats it according to the GraphQL schema.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return mixed|Value
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $product = $this->productRepository->getById($value['entity_id']);
        $attributes = $product->getAttributes();
        $result = [];

        foreach ($attributes as $attribute) {
            //if ($attribute->getIsVisibleOnFront() && $attribute->getIsComparable()) {
            if ($attribute->getIsVisible() && $attribute->getIsComparable()) {
                $attributeCode = $attribute->getAttributeCode();

                $result[] = [
                    'attribute_id' => $attribute->getAttributeId(),
                    'attribute_code' => $attributeCode,
                    'attribute_type' => $attribute->getFrontendInput(),
                    'attribute_label' => $attribute->getFrontendLabel(),
                    'attribute_value' => $this->getAttributeValue($product, $attributeCode),
                    'attribute_options' => $this->getAttributeOptions($attribute)
                ];
            }
        }

        return $result;
    }

    private function getAttributeValue(Product $product, string $attributeCode) {
        $value = $product->getAttributeText($attributeCode);

        if (!$value) {
            $value = $product->getData($attributeCode);
        }

        return $value ? : null;
    }

    private function getAttributeOptions(AbstractAttribute $attribute): array {
        $rawOptions = $attribute->getSource()->getAllOptions(true, true);
        array_shift($rawOptions);
        $optionIds = array_map(function ($option) {
            return $option['value'];
        }, $rawOptions);
        $swatchOptions = $this->swatchHelper->getSwatchesByOptionsId($optionIds);

        return array_map(function ($option) use ($swatchOptions) {
            $option['swatch_data'] = $swatchOptions[$option['value']] ?? [];
            return $option;
        }, $rawOptions);
    }
}
