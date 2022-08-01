<?php declare( strict_types = 1 );

namespace Waves\Util;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Model\Id;

class Functions
{
    /**
    * Decodes binary data from base58 string
    *
    * @param string $string
    * @return string
    */
    static function base58Decode( string $string ): string
    {
        $decoded = \deemru\ABCode::base58()->decode( $string );
        if( $decoded === false )
            throw new Exception( __FUNCTION__ . ' failed to decode string: ' . $string, ExceptionCode::BASE58_DECODE );
        return $decoded;
    }

    /**
    * Encodes binary data to base58 string
    *
    * @param string $bytes
    * @return string
    */
    static function base58Encode( string $bytes ): string
    {
        $encoded = \deemru\ABCode::base58()->encode( $bytes );
        if( $encoded === false )
            // Unreachable for binary encodings
            throw new Exception( __FUNCTION__ . ' failed to encode bytes: ' . bin2hex( $bytes ), ExceptionCode::BASE58_ENCODE ); // @codeCoverageIgnore
        return $encoded;
    }

    static function calculateTransactionId( string $bodyBytes ): Id
    {
        return Id::fromBytes( (new \deemru\WavesKit)->blake2b256( $bodyBytes ) );
    }
}
