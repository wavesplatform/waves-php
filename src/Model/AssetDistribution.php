<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class AssetDistribution extends JsonBase
{
    /**
     * @return array<string, int>
     */
    function items(): array { return $this->json->get( 'items' )->asMapStringInt(); }
    function lastItem(): string { return $this->json->get( 'lastItem' )->asString(); }
    function hasNext(): bool { return $this->json->getOr( 'hasNext', false )->asBoolean(); }
}
