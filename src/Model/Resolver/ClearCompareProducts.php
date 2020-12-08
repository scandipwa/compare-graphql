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

use Magento\Catalog\Helper\Product\Compare;
use Magento\Catalog\Model\ResourceModel\Product\Compare\Item\CollectionFactory as CollectionFactoryAlias;
use Magento\Framework\Exception\LocalizedException as LocalizedExceptionAlias;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\ObjectManagerInterface as ObjectManagerInterfaceAlias;

/**
 * Class ClearCompareProducts
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class ClearCompareProducts implements ResolverInterface
{
    /**
     * @var ObjectManagerInterfaceAlias
     */
    protected $objectManager;

    /* Item collection factory
    *
    * @var CollectionFactoryAlias
    */
    protected $itemCollectionFactory;

    /**
     * GetCartItems constructor.
     * @param ObjectManagerInterfaceAlias $_objectManager
     * @param CollectionFactoryAlias $itemCollectionFactory
     */
    public function __construct(
        ObjectManagerInterfaceAlias $_objectManager,
        CollectionFactoryAlias $itemCollectionFactory
    ) {
        $this->objectManager = $_objectManager;
        $this->itemCollectionFactory = $itemCollectionFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $customerId = (int)$context->getUserId();
        $items = $this->itemCollectionFactory->create();

        if ($customerId) {
            $items->setCustomerId($customerId);
        } else {
            if (isset($args['guestCartId'])) {
                $items->setVisitorId($args['guestCartId']);
            } else {
                return false;
            }
        }

        try {
            $items->clear();
            $this->objectManager->get(Compare::class)->calculate();
            return true;
//        } catch (LocalizedExceptionAlias $e) {
//            return false;
        } catch (\Exception $e) {
            throw new GraphQlNoSuchEntityException(__('Something went wrong  clearing the comparison list.'));
        }
    }
}
