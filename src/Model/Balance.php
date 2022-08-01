<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class Balance extends JsonBase
{
    function getAddress(): string { return $this->json->get( 'id' )->asString(); }
    function getBalance(): int { return $this->json->get( 'balance' )->asInt(); }
}
