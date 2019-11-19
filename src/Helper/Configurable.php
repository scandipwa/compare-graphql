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

namespace ScandiPWA\CompareGraphQl\Helper;

use Magento\Catalog\Model\Product as ProductInterface;
use Magento\Eav\Model\Config;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CatalogGraphQl\Helper\AbstractHelper as ScandiPWAHelper;

class Configurable extends ScandiPWAHelper
{
    const PARENT_URL_KEY = 'parent_url_key';
    const SUPER_ATTRIBUTES = 'super_attributes';

    const DEFAULT_ID_FIELD = 'e.entity_id';

    const DEFAULT_FIELDS = [
        self::PARENT_URL_KEY,
        self::SUPER_ATTRIBUTES
    ];

    private $attributeRepository;

    private $searchCriteriaBuilder;
    /**
     * @var Config
     */
    protected $eavConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Configurable constructor.
     *
     * @param Config $eavConfig
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        Config $eavConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductAttributeRepositoryInterface $attributeRepository,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->eavConfig = $eavConfig;
        $this->storeManager = $storeManager;
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param $node
     * @return string[]
     */
    protected function getFieldContent($node)
    {
        $images = [];
        $validFields = [
            'super_attributes',
            'attributes'
        ];

        foreach ($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) {
                continue;
            };

            $name = $selection->name->value;
            if (in_array($name, $validFields)) {
                $images[] = $name;
                break;
            }
        }

        return $images;
    }

    /**
     * Getting Configurable Options
     *
     * @param $collection
     * @param string $id
     * @param array $fields
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfigurableOptions(
        &$collection,
        $id = self::DEFAULT_ID_FIELD,
        $fields = self::DEFAULT_FIELDS,
        $joinAttributeCodes = false
    ) {
        $url_key = $this->eavConfig->getAttribute(ProductInterface::ENTITY, 'url_key')->getId();
        $enabled = $this->eavConfig->getAttribute(ProductInterface::ENTITY, 'status')->getId();
        $storeId = $this->storeManager->getStore()->getStoreId();
        $status = "IF(status.value_id > 0, status.value, status_default.value)";

        if (!count($fields)) {
            return $collection;
        }

        $collection->getSelect()->joinLeft(
            ['_super_link' => $collection->getTable('catalog_product_super_link')],
            '_super_link.product_id = '.$id,
            ['parent_id']
        );

        $collection->getSelect()->joinLeft(
            ['status_default' => $collection->getTable('catalog_product_entity_int')],
            '(status_default.attribute_id = ' . $enabled . ') AND '.
            '(status_default.entity_id = parent_id) AND ' .
            '(status_default.store_id = 0)',
            ['value as status_enabled_default']
        );

        $collection->getSelect()->joinLeft(
            ['status' => $collection->getTable('catalog_product_entity_int')],
            '(status.attribute_id = ' . $enabled . ') AND ' .
            '(status.entity_id = parent_id) AND ' .
            '(status.store_id = ' . $storeId . ')',
            ['value AS status_enabled']
        );

        if (in_array(self::PARENT_URL_KEY, $fields)) {
            $collection->getSelect()->joinLeft(
                ['_entity_varchar' => $collection->getTable('catalog_product_entity_varchar')],
                '(_entity_varchar.entity_id = parent_id) AND ' .
                '(_entity_varchar.attribute_id = ' . $url_key . ') AND' .
                '(' . $status . ' = ' . ProductStatus::STATUS_ENABLED . ')',
                ['value AS parent_url_key']
            );
        }

        if ($joinAttributeCodes) {
            $subquery = new \Zend_Db_Expr('(SELECT `super_attr`.`product_id`,
            group_concat(DISTINCT `attribute_code_t`.`attribute_code`) AS `attribute_codes`
            FROM `catalog_product_super_attribute` AS `super_attr` 
            LEFT JOIN `eav_attribute` AS `attribute_code_t`
            ON ( `super_attr`.`attribute_id` = `attribute_code_t`.`attribute_id` ) 
            GROUP BY  `super_attr`.`product_id`)');

            $collection->getSelect()->joinLeft(
                ['attributes' => $subquery],
                'attributes.product_id = parent_id AND '.
                '(' . $status . ' = ' . ProductStatus::STATUS_ENABLED . ')',
                ['attribute_codes']
            );
        }

        return $collection;
    }

    /**
     * Get simple product parent ids from collection
     *
     * @param $collection
     * @return array
     */
    public function getParentIds($collection)
    {
        $parentIds = [];
        foreach ($collection->getItems() as $item) {
            $parentId = $item->getParentId();
            if (isset($parentId)) {
                $parentIds[] = $parentId;
            }
        }

        return $parentIds;
    }

    /**
     * Gather data for necessary super attributes from given collection
     *
     * @param $collection
     * @param array $attributes
     * @param array $parentAttributeMap
     * @param array $attributeCodes
     * @return mixed
     */
    public function getCollectionSuperAttributes(
        $collection,
        &$attributes = [],
        &$parentAttributeMap = [],
        &$attributeCodes = []
    ) {
        $parentIds = $this->getParentIds($collection);

        // we don't want to continue if no configurable children present in the collection
        if (empty($parentIds)) {
            return $collection;
        }

        // 1.2. get all parent super attribute ids from super attribute table (join attribute codes to that table)
        $attributesCodes = [];
        $mainTable = $collection->getTable('catalog_product_super_attribute');
        $attributeTable = $collection->getTable('eav_attribute');
        $connection = $collection->getConnection();
        $select = $connection->select()->from(
            ['m' => $mainTable],
            ['product_id', 'attribute_id']
        )->joinLeft(
            ['attr' => $attributeTable],
            $connection->quoteIdentifier(
                'attr.attribute_id'
            ) . ' = ' . $connection->quoteIdentifier(
                'm.attribute_id'
            ),
            ['attribute_code']
        )->where(
            'm.product_id IN ( ? )',
            $parentIds
        );

        // 2. map (1. array with attributes, [parent_id] = array with super attribute codes
        foreach ($connection->fetchAll($select) as $row) {
            $productId = $row['product_id'];
            $attributeCode = $row['attribute_code'];
            if (isset($attributeCode)) {
                $attributesCodes[] = $attributeCode;
            }

            if (isset($productId)) {
                $parentAttributeMap[$row['product_id']][] = $row['attribute_code'];
            }
        }

        if (empty($attributesCodes)) {
            return $collection;
        }

        $attributeCodes = array_unique($attributesCodes);

        // prepare super attributes data
        $this->prepareSuperAttributes($attributesCodes, $attributes);
    }

    /**
     * @param $collection
     * @return mixed
     */
    public function appendSuperAttributes(&$collection)
    {
        $this->getCollectionSuperAttributes(
            $collection,
            $attributes,
            $parentAttributeMap,
            $attributeCodes
        );

        if (!empty($attributeCodes) && !empty($attributes)) {
            // join those attributes to product collection
            $collection->addAttributeToSelect($attributeCodes, 'left');

            // clearing collection, otherwise cannot access data from third step as collection has already been
            // loaded once. maybe quicker to get all product IDs then create new product collection adding attributes
            $collection->clear();

            // for each collection items, by parent id access supper attribute codes and prepare data for updating item
            foreach ($collection->getItems() as $item) {
                $parentId = $item->getParentId();
                if (isset($parentId)) {
                    $superAttributeCodes = [];
                    $parentAttributeCodes = $parentAttributeMap[$parentId] ?? [];

                    foreach ($parentAttributeCodes as $att) {
                        $value = $item->getData($att);

                        if (isset($value, $attributes[$att][$value])) {
                            $superAttributeCodes[] = $attributes[$att][$value];
                        }
                    }

                    if (!empty($superAttributeCodes)) {
                        $item->setSuperAttributes($superAttributeCodes);
                    }
                }
            }
        }
    }

    public function prepareSuperAttributes($attributeCodes, &$attributes)
    {
        // 4. prepare super attributes data
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('main_table.attribute_code', $attributeCodes, 'in')
            ->create();

        /** @var SearchCriteriaInterface $searchCriteria */
        $attributeRepository = $this->attributeRepository->getList($searchCriteria);
        $detailedAttributes = $attributeRepository->getItems();

        /** @var Attribute $attribute */
        foreach ($detailedAttributes as $attribute) {
            $key = $attribute->getAttributeCode();
            $options = $attribute->getOptions();

            foreach ($options as $option) {
                if ($option->getValue()) {
                    $value = $option->getValue();

                    if (isset($value)) {
                        $attributes[$key][$value] =
                            [
                                'attribute_code' => $key,
                                'attribute_label' => $option->getDefaultValue(),
                                'attribute_value' => $value
                            ];
                    }
                }
            }
        }
    }

    public function getSuperAttributesForCart($products, $info)
    {
        $fields = $this->getFieldsFromProductInfo($info);
        if (empty($fields)) {
            return [];
        }

        $flippedFields = array_flip($fields);

        if (!isset($flippedFields[self::SUPER_ATTRIBUTES]) || isset($flippedFields['attributes'])) {
            return [];
        }
        $attributeCodes = [];

        // Collect attributes for request
        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();
            // Create storage for future attributes
            $productAttributesMap[$id] = [];
            $superAttributes = $product->getSuperAttributeValues();

            if (!empty($superAttributes)) {
                foreach ($superAttributes as $key => $value) {
                    $attributeCodes[] = $key;
                    $productAttributesMap[$id][$key] = $value;
                }
            }
        }

        if (empty($attributeCodes)) {
            return [];
        }

        // get necessary super attributes data
        $this->prepareSuperAttributes($attributeCodes, $attributes);

        $superAttributesMap = [];
        foreach ($products as $product) {
            $id = $product->getId();

            $superAttributes = $product->getSuperAttributeValues();
            if (!isset($superAttributes)) {
                continue;
            }
            $superAttributesMap[$id] = [];

            foreach ($superAttributes as $key => $value) {
                if (isset($attributes[$key][$value])) {
                    $superAttributesMap[$id][] = $attributes[$key][$value];
                }
            }
        }

        return $superAttributesMap;
    }
}
