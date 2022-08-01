<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class ArgMeta extends JsonBase
{
    function name(): string { return $this->json->get( 'name' )->asString(); }
    function type(): string { return $this->json->get( 'type' )->asString(); }
}
