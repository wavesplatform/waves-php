<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class TransactionStatus extends JsonBase
{
    function id(): Id { return $this->json->get( 'id' )->asId(); }
    function status(): int { return $this->json->get( 'status' )->asStatus(); }
    function applicationStatus(): int { return $this->json->get( 'applicationStatus' )->asApplicationStatus(); }
    function height(): int { return $this->json->getOr( 'height', 0 )->asInt(); }
    function confirmations(): int { return $this->json->getOr( 'confirmations', 0 )->asInt(); }
}
