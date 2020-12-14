<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/quote-graphql
 * @link    https://github.com/scandipwa/quote-graphql
 */

namespace ScandiPWA\CompareGraphQl\Observer;

use Magento\Catalog\Model\Product\Compare\ItemFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Event\Observer;
use Magento\Integration\Model\Oauth\TokenFactory;

class MergeCustomerAndGuestCompareListObserver implements ObserverInterface {
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var ItemFactory
     */
    private $compareItemFactory;

    /**
     * @var TokenFactory
     */
    private $tokenFactory;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ItemFactory $compareItemFactory
     * @param TokenFactory $tokenFactory
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ItemFactory $compareItemFactory,
        TokenFactory $tokenFactory
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->compareItemFactory = $compareItemFactory;
        $this->tokenFactory = $tokenFactory;
    }

    public function execute(Observer $observer) {
        $visitorId = $this->getVisitorId($observer->getData('guest_quote_id'));
        $customerId = $this->getCustomerId($observer->getData('customer_token'));

        $item = $this->compareItemFactory->create();

        $item->addVisitorId($visitorId);
        $item->setCustomerId($customerId);

        $item->bindCustomerLogin();
    }

    private function getVisitorId($guestQuoteId) {
        return $this->quoteIdMaskFactory
            ->create()
            ->load($guestQuoteId, 'masked_id')
            ->getQuoteId();
    }

    private function getCustomerId($customerToken) {
        return $this->tokenFactory
            ->create()
            ->loadByToken($customerToken)
            ->getCustomerId();
    }
}
