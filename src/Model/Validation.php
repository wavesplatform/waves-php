<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class Validation extends JsonBase
{
    function isValid(): bool { return $this->json->get( 'valid' )->asBoolean(); }
    function validationTime(): int { return $this->json->get( 'validationTime' )->asInt(); }
    function error(): string { return $this->json->get( 'error' )->asString(); }
}
