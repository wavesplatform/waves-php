<?php declare( strict_types = 1 );

namespace Waves\Model;

use Exception;
use Waves\Common\ExceptionCode;

class ChainId
{
    const MAINNET = 'W';
    const TESTNET = 'T';
    const STAGENET = 'S';
    const PRIVATE = 'R';

    private string $chainId;

    private function __construct(){}

    static function fromInt( int $int ): ChainId
    {
        if( $int < 0 || $int > 255 )
            throw new Exception( __FUNCTION__ . ' bad chainId value: ' . $int, ExceptionCode::BAD_CHAINID );
        $chainId = new ChainId;
        $chainId->chainId = chr( $int );
        return $chainId;
    }

    static function fromString( string $string ): ChainId
    {
        if( strlen( $string ) !== 1 )
            throw new Exception( __FUNCTION__ . ' bad chainId value: ' . strlen( $string ), ExceptionCode::BAD_CHAINID );
        $chainId = new ChainId;
        $chainId->chainId = $string;
        return $chainId;
    }

    static function MAINNET(): ChainId
    {
        static $chainId;
        if( !isset( $chainId ) )
            $chainId = ChainId::fromString( ChainId::MAINNET );
        return $chainId;
    }

    static function TESTNET(): ChainId
    {
        static $chainId;
        if( !isset( $chainId ) )
            $chainId = ChainId::fromString( ChainId::TESTNET );
        return $chainId;
    }

    static function STAGENET(): ChainId
    {
        static $chainId;
        if( !isset( $chainId ) )
            $chainId = ChainId::fromString( ChainId::STAGENET );
        return $chainId;
    }

    static function PRIVATE(): ChainId
    {
        static $chainId;
        if( !isset( $chainId ) )
            $chainId = ChainId::fromString( ChainId::PRIVATE );
        return $chainId;
    }

    function asInt(): int
    {
        return ord( $this->chainId );
    }

    function asString(): string
    {
        return $this->chainId;
    }
}
