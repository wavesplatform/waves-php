<?php declare( strict_types = 1 );

namespace Waves\Model;

class WavesConfig
{
    private static ChainId $chainId;

    static function chainId( ChainId $chainId = null ): ChainId
    {
        if( isset( $chainId ) )
            self::$chainId = $chainId;
        else if( !isset( self::$chainId ) )
            self::$chainId = ChainId::MAINNET();
        return self::$chainId;
    }
}
