<?php declare( strict_types = 1 );

namespace Waves\Model;

class TransactionInfo extends TransactionWithStatus
{
    function height(): int { return $this->json->get( 'height' )->asInt(); }
}
