<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/compare-graphql
 * @link    https://github.com/scandipwa/compare-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Product\Compare\Item\CollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\CompareGraphQl\Helper\Auth as AuthHelper;

/**
 * Class ClearCompareProducts
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class ClearCompareProducts implements ResolverInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Item collection factory
     *
     * @var CollectionFactory
     */
    protected $itemCollectionFactory;

    /**
     * @var AuthHelper
     */
    protected $authHelper;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $itemCollectionFactory
     * @param AuthHelper $authHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CollectionFactory $itemCollectionFactory,
        AuthHelper $authHelper
    ) {
        $this->storeManager = $storeManager;
        $this->itemCollectionFactory = $itemCollectionFactory;
        $this->authHelper = $authHelper;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $customerId = (int)$context->getUserId();
        $guestCartId = $this->authHelper->getGuestCartId($args);

        if (!$customerId && !$guestCartId) {
            return false;
        }

        $collection = $this->itemCollectionFactory->create();

        $this->authHelper->setAuthData(
            $collection,
            $customerId,
            $guestCartId
        );

        $store = $this->storeManager->getStore();
        $collection->setStore($store);

        $collection->clear();

        return true;
    }
}
