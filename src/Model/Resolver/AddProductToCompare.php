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

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product\Compare\ItemFactory;
use ScandiPWA\CompareGraphQl\Helper\Auth as AuthHelper;

/**
 * Class AddProductToCompare
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class AddProductToCompare implements ResolverInterface
{
    /**
     * @var ItemFactory
     */
    private $compareItemFactory;

    /**
     * @var AuthHelper
     */
    protected $authHelper;

    /**
     * @param ItemFactory $compareItemFactory,
     * @param AuthHelper $authHelper
     */
    public function __construct(
        ItemFactory $compareItemFactory,
        AuthHelper $authHelper
    ) {
        $this->compareItemFactory = $compareItemFactory;
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
        $productId = (int)$args['product_id'];
        $customerId = (int)$context->getUserId();
        $guestCartId = $this->authHelper->getGuestCartId($args);

        if (!$productId || !($customerId || $guestCartId)) {
            return false;
        }

        $item = $this->compareItemFactory->create();

        $this->authHelper->setAuthData(
            $item,
            $customerId,
            $guestCartId
        );

        $item->loadByProduct($productId);

        if (!$item->getId()) {
            $item->addProductData($productId);
            $item->save();
        }

        return true;
    }
}
