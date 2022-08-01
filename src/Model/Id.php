<?php declare( strict_types = 1 );

namespace Waves\Model;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Common\Base58String;

class Id
{
    const BYTE_LENGTH = 32;

    private Base58String $id;

    private function __construct(){}

    static function fromString( string $encoded ): Id
    {
        $id = new Id;
        $id->id = Base58String::fromString( $encoded );
        return $id;
    }

    static function fromBytes( string $bytes ): Id
    {
        if( strlen( $bytes ) !== Id::BYTE_LENGTH )
            throw new Exception( __FUNCTION__ . ' bad id length: ' . strlen( $bytes ), ExceptionCode::BAD_ASSET );
        $id = new Id;
        $id->id = Base58String::fromBytes( $bytes );
        return $id;
    }

    function bytes(): string
    {
        $bytes = $this->id->bytes();
        if( strlen( $bytes ) !== Id::BYTE_LENGTH )
            throw new Exception( __FUNCTION__ . ' bad id length: ' . strlen( $bytes ), ExceptionCode::BAD_ASSET );
        return $bytes;
    }

    function encoded(): string
    {
        return $this->id->encoded();
    }

    function toString(): string
    {
        return $this->encoded();
    }
}
