<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Account\Address;
use Waves\Common\JsonBase;

class BlockHeaders extends JsonBase
{
    /**
     * @return array<int>
     */
    function features(): array { return $this->json->get( 'features' )->asArrayInt(); }
    function version(): int { return $this->json->get( 'version' )->asInt(); }
    function timestamp(): int { return $this->json->get( 'timestamp' )->asInt(); }
    function reference(): string { return $this->json->get( 'reference' )->asString(); }
    function baseTarget(): int { return $this->json->get( 'nxt-consensus' )->asJson()->get( 'base-target' )->asInt(); }
    function generationSignature(): string { return $this->json->get( 'nxt-consensus' )->asJson()->get( 'generation-signature' )->asString(); }
    function transactionsRoot(): string { return $this->json->get( 'transactionsRoot' )->asString(); }
    function id(): Id { return $this->json->get( 'id' )->asId(); }
    function desiredReward(): int { return $this->json->get( 'desiredReward' )->asInt(); }
    function generator(): Address { return $this->json->get( 'generator' )->asAddress(); }
    function signature(): string { return $this->json->get( 'signature' )->asString(); }
    function size(): int { return $this->json->get( 'blocksize' )->asInt(); }
    function transactionsCount(): int { return $this->json->get( 'transactionCount' )->asInt(); }
    function height(): int { return $this->json->get( 'height' )->asInt(); }
    function totalFee(): int { return $this->json->get( 'totalFee' )->asInt(); }
    function reward(): int { return $this->json->get( 'reward' )->asInt(); }
    function vrf(): string { return $this->json->get( 'VRF' )->asString(); }
}
