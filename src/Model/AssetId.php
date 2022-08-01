<?php declare( strict_types = 1 );

namespace Waves\Model;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Common\Base58String;

class AssetId
{
    const BYTE_LENGTH = 32;
    const WAVES_STRING = "WAVES";

    private Base58String $assetId;

    private function __construct(){}

    static function WAVES(): AssetId
    {
        return new AssetId;
    }

    static function fromString( string $encoded ): AssetId
    {
        if( strtoupper( $encoded ) === AssetId::WAVES_STRING )
            return AssetId::WAVES();

        $assetId = new AssetId;
        $assetId->assetId = Base58String::fromString( $encoded );
        return $assetId;
    }

    static function fromBytes( string $bytes ): AssetId
    {
        if( $bytes === '' )
            return AssetId::WAVES();

        if( strlen( $bytes ) !== AssetId::BYTE_LENGTH )
            throw new Exception( __FUNCTION__ . ' bad asset length: ' . strlen( $bytes ), ExceptionCode::BAD_ASSET );
        $assetId = new AssetId;
        $assetId->assetId = Base58String::fromBytes( $bytes );
        return $assetId;
    }

    function isWaves(): bool
    {
        return !isset( $this->assetId );
    }

    function bytes(): string
    {
        if( $this->isWaves() )
            return '';
        $bytes = $this->assetId->bytes();
        if( strlen( $bytes ) !== AssetId::BYTE_LENGTH )
            throw new Exception( __FUNCTION__ . ' bad asset length: ' . strlen( $bytes ), ExceptionCode::BAD_ASSET );
        return $bytes;
    }

    function encoded(): string
    {
        return $this->isWaves() ? AssetId::WAVES_STRING : $this->assetId->encoded();
    }

    function toString(): string
    {
        return $this->encoded();
    }

    /**
     * @return string|null
     */
    function toJsonValue()
    {
        return $this->isWaves() ? null : $this->encoded();
    }
}
