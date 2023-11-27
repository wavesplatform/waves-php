<?php declare( strict_types = 1 );

namespace Waves\Account;

use Exception;
use Waves\Common\Base58String;
use Waves\Common\ExceptionCode;

class PrivateKey
{
    const LENGTH = 32;

    private Base58String $key;
    private PublicKey $publicKey;

    private function __construct(){}

    static function fromSeed( string $seed, int $nonce = 0 ): PrivateKey
    {
        $privateKey = new PrivateKey;
        $bytes = ( new \deemru\WavesKit )->getPrivateKey( true, $seed, pack( 'N', $nonce ) );
        if( !is_string( $bytes ) || strlen( $bytes ) !== PrivateKey::LENGTH )
            throw new Exception( __FUNCTION__ . ' bad key', ExceptionCode::BAD_KEY );
        $privateKey->key = Base58String::fromBytes( $bytes );
        return $privateKey;
    }

    static function fromBytes( string $key ): PrivateKey
    {
        $privateKey = new PrivateKey;
        $privateKey->key = Base58String::fromBytes( $key );
        return $privateKey;
    }

    static function fromString( string $key ): PrivateKey
    {
        $privateKey = new PrivateKey;
        $privateKey->key = Base58String::fromString( $key );
        return $privateKey;
    }

    function publicKey(): PublicKey
    {
        if( !isset( $this->publicKey ) )
            $this->publicKey = PublicKey::fromPrivateKey( $this );
        return $this->publicKey;
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
