<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;
use Waves\Common\Value;

class ScriptMeta extends JsonBase
{
    function metaVersion(): int { return $this->json->get( 'version' )->asInt(); }
    /**
     * Gets a map of callable functions with their arguments as ArgMeta
     *
     * @return array<string, array<int, ArgMeta>>
     */
    function callableFunctions(): array
    {
        $map = [];
        $arrayFuncs = $this->json->get( 'callableFuncTypes' )->asArray();
        foreach( $arrayFuncs as $key => $value )
        {
            $function = Value::as( $key )->asString();
            $args = [];
            $arrayArgs = Value::as( $value )->asArray();
            foreach( $arrayArgs as $arg )
                $args[] = Value::as( $arg )->asArgMeta();
            $map[$function] = $args;
        }

        return $map;
    }
}
