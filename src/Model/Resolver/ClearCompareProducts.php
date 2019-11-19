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

use Magento\Catalog\Helper\Product\Compare;
use Magento\Catalog\Model\ResourceModel\Product\Compare\Item\CollectionFactory as CollectionFactoryAlias;
use Magento\Framework\Exception\LocalizedException as LocalizedExceptionAlias;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\ObjectManagerInterface as ObjectManagerInterfaceAlias;
use Magento\Quote\Model\QuoteIdMaskFactory;


/**
 * Class ClearCompareProducts
 * @package ScandiPWA\CompareGraphQl\Model\Resolver
 */
class ClearCompareProducts implements ResolverInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * Object manager
     *
     * @var ObjectManagerInterfaceAlias
     */
    protected $objectManager;

    /**
     * Item collection factory
     *
     * @var CollectionFactoryAlias
     */
    protected $itemCollectionFactory;

    /**
     * GetCartItems constructor.
     * @param ObjectManagerInterfaceAlias $_objectManager
     * @param CollectionFactoryAlias $itemCollectionFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        ObjectManagerInterfaceAlias $_objectManager,
        CollectionFactoryAlias $itemCollectionFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->objectManager = $_objectManager;
        $this->itemCollectionFactory = $itemCollectionFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Collect data to clear all products in compare list
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return bool
     * @throws LocalizedExceptionAlias
     * @throws GraphQlNoSuchEntityException
     */
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
                $quoteIdMask = $this->quoteIdMaskFactory
                    ->create()
                    ->load($args['guestCartId'], 'masked_id')
                    ->getQuoteId();
                $items->setVisitorId($quoteIdMask);
            } else {
                return false;
            }
        }

        try {
            $items->clear();
            $this->objectManager->get(Compare::class)->calculate();
            return true;
        } catch (LocalizedExceptionAlias $e) {
            return false;
        } catch (\Exception $e) {
            throw new GraphQlNoSuchEntityException(__('Something went wrong  clearing the comparison list.'));
        }
    }
}
