<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\Base64String;
use Waves\Common\JsonBase;

class ScriptDetails extends JsonBase
{
    const EMPTY = [ 'script' => '', 'scriptComplexity' => 0 ];

    function script(): Base64String { return $this->json->get( 'script' )->asBase64String(); }
    function complexity(): int { return $this->json->get( 'scriptComplexity' )->asInt(); }
}
