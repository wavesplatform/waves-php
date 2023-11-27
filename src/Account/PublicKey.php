<?php declare( strict_types = 1 );

namespace Waves\Account;

use Exception;
use Waves\Common\Base58String;
use Waves\Common\ExceptionCode;
use Waves\Model\ChainId;

class PublicKey
{
    const BYTES_LENGTH = 32;
    const ETH_BYTES_LENGTH = 64;

    private Base58String $key;
    private Address $address;

    private function __construct(){}

    static function fromBytes( string $key ): PublicKey
    {
        $publicKey = new PublicKey;
        $publicKey->key = Base58String::fromBytes( $key );
        return $publicKey;
    }

    static function fromString( string $key ): PublicKey
    {
        $publicKey = new PublicKey;
        $publicKey->key = Base58String::fromString( $key );
        return $publicKey;
    }

    static function fromPrivateKey( PrivateKey $key ): PublicKey
    {
        $publicKey = new PublicKey;
        $wk = new \deemru\WavesKit;
        $wk->setPrivateKey( $key->bytes(), true );
        $bytes = $wk->getPublicKey( true );
        if( !is_string( $bytes ) || strlen( $bytes ) !== PublicKey::BYTES_LENGTH )
            throw new Exception( __FUNCTION__ . ' bad key', ExceptionCode::BAD_KEY );
        $publicKey->key = Base58String::fromBytes( $bytes );
        return $publicKey;
    }

    function address( ChainId $chainId = null ): Address
    {
        if( !isset( $this->address ) )
            $this->address = Address::fromPublicKey( $this, $chainId );
        return $this->address;
    }

    function attachAddress( Address $address ): void
    {
        $this->address = $address;
    }

    function bytes(): string
    {
        return $this->key->bytes();
    }

    function toString(): string
    {
        return $this->key->toString();
    }
}
