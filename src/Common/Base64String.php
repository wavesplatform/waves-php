<?php declare( strict_types = 1 );

namespace Waves\Common;

use Exception;

class Base64String
{
    const PROLOG = 'base64:';
    private string $bytes;
    private string $encoded;

    private function __construct(){}

    static function emptyString(): Base64String
    {
        $base64String = new Base64String;
        $base64String->bytes = '';
        $base64String->encoded = '';
        return $base64String;
    }

    static function fromString( string $encoded ): Base64String
    {
        if( substr( $encoded, 0, 7 ) === Base64String::PROLOG )
            $encoded = substr( $encoded, 7 );

        $base64String = new Base64String;
        $base64String->encoded = $encoded;
        return $base64String;
    }

    static function fromBytes( string $bytes ): Base64String
    {
        $base64String = new Base64String;
        $base64String->bytes = $bytes;
        return $base64String;
    }

    function bytes(): string
    {
        if( !isset( $this->bytes ) )
        {
            $this->bytes = base64_decode( $this->encoded );
            if( !is_string( $this->bytes ) )
                throw new Exception( __FUNCTION__ . ' failed to decode string: ' . $this->encoded, ExceptionCode::BASE64_DECODE );
        }
        return $this->bytes;
    }

    function encoded(): string
    {
        if( !isset( $this->encoded ) )
            $this->encoded = base64_encode( $this->bytes );
        return $this->encoded;
    }

    function encodedWithPrefix(): string
    {
        return Base64String::PROLOG . $this->encoded();
    }

    function toString(): string
    {
        return $this->encodedWithPrefix();
    }

    /**
     * @return string|null
     */
    function toJsonValue()
    {
        if( $this->bytes() === '' )
            return null;
        return $this->encodedWithPrefix();
    }
}
