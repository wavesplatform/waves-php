<?php

require_once __DIR__ . '/../vendor/autoload.php';

function prepare(): void
{
    if( file_exists( __DIR__ . '/config.php' ) )
        require_once __DIR__ . '/config.php';

    $wavesConfig = getenv( 'WAVES_CONFIG' );
    if( !is_string( $wavesConfig ) )
        return;

    //echo $wavesConfig; // print value

    $wavesConfig = hex2bin( $wavesConfig );
    if( !is_string( $wavesConfig ) )
        return;

    $wavesConfig = json_decode( $wavesConfig, true );
    if( $wavesConfig === false )
        return;

    if( !is_array( $wavesConfig ) )
        return;

    foreach( $wavesConfig as $key => $value )
        if( is_string( $key ) )
            define( $key, $value );
}

prepare();
