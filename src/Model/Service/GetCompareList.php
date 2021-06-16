<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Service;

use Magento\Catalog\Model\CompareListIdToMaskedListId;
use Magento\CompareListGraphQl\Model\Service\GetCompareList as SourceGetCompareList;
use Magento\CompareListGraphQl\Model\Service\GetComparableItems;
use Magento\CompareListGraphQl\Model\Service\GetComparableAttributes;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;


/**
 * Get products compare list
 */
class GetCompareList extends SourceGetCompareList
{
    public const ATTRIBUTE_VALUE_NOT_AVAILABLE = "N/A";

    /**
     * @param GetComparableItems $comparableItemsService
     * @param GetComparableAttributes $comparableAttributesService
     * @param CompareListIdToMaskedListId $compareListIdToMaskedListId
     */
    public function __construct(
        GetComparableItems $comparableItemsService,
        GetComparableAttributes $comparableAttributesService,
        CompareListIdToMaskedListId $compareListIdToMaskedListId
    ) {
        parent::__construct(
            $comparableItemsService,
            $comparableAttributesService,
            $compareListIdToMaskedListId
        );
    }

    /**
     * Get compare list information
     *
     * @param int $listId
     * @param ContextInterface $context
     *
     * @return array
     * @throws GraphQlInputException
     */
    public function execute(int $listId, ContextInterface $context)
    {
        $compareList = parent::execute($listId, $context);

        // Return only attributes, which have value for at least one of the products being compared
        $finalComparableAttributes = [];
        foreach ($compareList['attributes'] as $attribute){
            if($this->hasAttributeValueForProducts($attribute, $compareList['items'])){
                $finalComparableAttributes[] = $attribute;
            }
        }

        $compareList['attributes'] = $finalComparableAttributes;

        // Clean values for comparable arrtibutes before returing them
        $this->cleanAttributeValues($compareList['items']);

        return $compareList;
    }

    public function hasAttributeValueForProducts($attribute, $items)
    {
        foreach ($items as $item) {
            if ($this->getItemAttributeValue($item['attributes'], $attribute['code']) !== self::ATTRIBUTE_VALUE_NOT_AVAILABLE) {
                return true;
            }
        }

        return false;
    }

    protected function getItemAttributeValue($itemAttributes, $attributeCode){
        foreach ($itemAttributes as $attribute){
            if ($attribute['code'] === $attributeCode){
                $attributeValue = $attribute['value'];

                if ($attributeValue instanceof \Magento\Framework\Phrase){
                    return $attributeValue->getText();
                }

                return $attributeValue;
            }
        }

        return null;
    }

    public function cleanAttributeValues(&$items){
        foreach ($items as $index => $item) {
            foreach ($item['attributes'] as $attrIndex => $attribute){
                if ($this->getItemAttributeValue($item['attributes'], $attribute['code']) === self::ATTRIBUTE_VALUE_NOT_AVAILABLE) {
                    $items[$index]['attributes'][$attrIndex]['value'] = __("-");
                }
            }
        }
    }
}
