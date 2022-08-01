<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Account\Address;
use Waves\Account\PublicKey;
use Waves\Common\JsonBase;

class AssetDetails extends JsonBase
{
    function assetId(): AssetId { return $this->json->get( 'assetId' )->asAssetId(); }
    function issueHeight(): int { return $this->json->get( 'issueHeight' )->asInt(); }
    function issueTimestamp(): int { return $this->json->get( 'issueTimestamp' )->asInt(); }
    function issuer(): Address { return $this->json->get( 'issuer' )->asAddress(); }
    function issuerPublicKey(): PublicKey { return $this->json->get( 'issuerPublicKey' )->asPublicKey(); }
    function name(): string { return $this->json->get( 'name' )->asString(); }
    function description(): string { return $this->json->get( 'description' )->asString(); }
    function decimals(): int { return $this->json->get( 'decimals' )->asInt(); }
    function isReissuable(): bool { return $this->json->get( 'reissuable' )->asBoolean(); }
    function quantity(): int { return $this->json->get( 'quantity' )->asInt(); }
    function isScripted(): bool { return $this->json->get( 'scripted' )->asBoolean(); }
    function minSponsoredAssetFee(): int { return $this->json->getOr( 'minSponsoredAssetFee', 0 )->asInt(); }
    function originTransactionId(): Id { return $this->json->get( 'originTransactionId' )->asId(); }
    function scriptDetails(): ScriptDetails { return $this->json->getOr( 'scriptDetails', ScriptDetails::EMPTY )->asJson()->asScriptDetails(); }
}
