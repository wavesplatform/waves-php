<?php declare( strict_types = 1 );

namespace Waves\Account;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Common\Base58String;
use Waves\Model\ChainId;
use Waves\Model\WavesConfig;

class Address
{
    const BYTE_LENGTH = 26;
    const STRING_LENGTH = 35;

    private Base58String $address;

    private function __construct(){}

    static function fromString( string $encoded ): Address
    {
        $address = new Address;
        $address->address = Base58String::fromString( $encoded );
        return $address;
    }

    static function fromBytes( string $bytes ): Address
    {
        if( strlen( $bytes ) !== Address::BYTE_LENGTH )
            throw new Exception( __FUNCTION__ . ' bad address length: ' . strlen( $bytes ), ExceptionCode::BAD_ADDRESS );
        $address = new Address;
        $address->address = Base58String::fromBytes( $bytes );
        return $address;
    }

    static function fromPublicKey( PublicKey $publicKey, ChainId $chainId = null ): Address
    {
        $address = new Address;
        $wk = new \deemru\WavesKit( ( isset( $chainId ) ? $chainId : WavesConfig::chainId() )->asString() );
        $wk->setPublicKey( $publicKey->bytes(), true );
        $address->address = Base58String::fromBytes( $wk->getAddress( true ) );
        return $address;
    }

    function chainId(): ChainId
    {
        return ChainId::fromString( $this->bytes()[1] );
    }

    function bytes(): string
    {
        $bytes = $this->address->bytes();
        if( strlen( $bytes ) !== Address::BYTE_LENGTH )
            throw new Exception( __FUNCTION__ . ' bad address length: ' . strlen( $bytes ), ExceptionCode::BAD_ADDRESS );
        return $bytes;
    }

    function encoded(): string
    {
        return $this->address->encoded();
    }

    function toString(): string
    {
        return $this->encoded();
    }

    function publicKeyHash(): string
    {
        return substr( $this->bytes(), 2, 20 );
    }
}
