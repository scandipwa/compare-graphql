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

namespace ScandiPWA\CompareGraphQl\Helper;

use Magento\Quote\Model\QuoteIdMaskFactory;

class Auth
{
    /** @var QuoteIdMaskFactory */
    private $quoteIdMaskFactory;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    public function getGuestCartId(array $data): ?string {
        return isset($data['guestCartId']) ? $data['guestCartId'] : null;
    }

    public function getVisitorId(string $guestCardId): int {
        return (int)$this->quoteIdMaskFactory
            ->create()
            ->load($guestCardId, 'masked_id')
            ->getQuoteId();
    }

    public function setAuthData($obj, $customerId, $guestCartId) {
        if ($customerId) {
            $obj->setCustomerId($customerId);
        } elseif ($guestCartId) {
            $visitorId = $this->getVisitorId($guestCartId);
            $obj->setVisitorId($visitorId);
        }

        return $obj;
    }
}
