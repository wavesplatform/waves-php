<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class HistoryBalance extends JsonBase
{
    function height(): int { return $this->json->get( 'height' )->asInt(); }
    function balance(): int { return $this->json->get( 'balance' )->asInt(); }
}
