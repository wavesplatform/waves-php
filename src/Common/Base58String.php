<?php declare( strict_types = 1 );

namespace Waves\Common;

use Waves\Util\Functions;

class Base58String
{
    private string $bytes;
    private string $encoded;

    private function __construct(){}

    static function emptyString(): Base58String
    {
        $base58String = new Base58String;
        $base58String->bytes = '';
        $base58String->encoded = '';
        return $base58String;
    }

    static function fromString( string $encoded ): Base58String
    {
        $base58String = new Base58String;
        $base58String->encoded = $encoded;
        return $base58String;
    }

    static function fromBytes( string $bytes ): Base58String
    {
        $base58String = new Base58String;
        $base58String->bytes = $bytes;
        return $base58String;
    }

    function bytes(): string
    {
        if( !isset( $this->bytes ) )
            $this->bytes = Functions::base58Decode( $this->encoded );
        return $this->bytes;
    }

    function encoded(): string
    {
        if( !isset( $this->encoded ) )
            $this->encoded = Functions::base58Encode( $this->bytes );
        return $this->encoded;
    }

    function toString(): string
    {
        return $this->encoded();
    }
}
