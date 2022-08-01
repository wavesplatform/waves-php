<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class Votes extends JsonBase
{
    function increase(): int { return $this->json->get( 'increase' )->asInt(); }
    function decrease(): int { return $this->json->get( 'decrease' )->asInt(); }
}
