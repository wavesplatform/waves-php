<?php declare( strict_types = 1 );

namespace Waves\Common;

class JsonBase
{
    protected Json $json;

    function __construct( Json $json = null )
    {
        if( !isset( $json ) )
            $json = Json::as( [] );
        $this->json = $json;
    }

    function toString(): string
    {
        return $this->json->toString();
    }

    function json(): Json
    {
        return $this->json;
    }
}
