<?php declare( strict_types = 1 );

namespace Waves\Model;

class Block extends BlockHeaders
{
    /**
     * @return array<int, TransactionWithStatus>
     */
    function transactions(): array { return $this->json->get( 'transactions' )->asJson()->asArrayTransactionWithStatus(); }
    function fee(): int { return $this->json->get( 'fee' )->asInt(); }
}
