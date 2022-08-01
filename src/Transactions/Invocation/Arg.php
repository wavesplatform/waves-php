<?php declare( strict_types = 1 );

namespace Waves\Transactions\Invocation;

use Exception;
use Waves\Common\Base64String;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Common\Value;

class Arg
{
    const BINARY = 1;
    const BOOLEAN = 2;
    const INTEGER = 3;
    const STRING = 4;
    const LIST = 5;

    private int $type;
    private Value $value;

    static function as( int $type, Value $value ): Arg
    {
        $arg = new Arg;
        $arg->type = $type;
        $arg->value = $value;
        return $arg;
    }

    static function fromJson( Json $json ): Arg
    {
        $type = Arg::stringToType( $json->get( 'type' )->asString() );
        if( $type === Arg::LIST )
        {
            $args = [];
            foreach( $json->get( 'value' )->asArray() as $arg )
                $args[] = Arg::fromJson( Value::as( $arg )->asJson() );
            $value = Value::as( $args );
        }
        else
        if( $type === Arg::BINARY )
        {
            $value = Value::as( $json->get( 'value' )->asBase64Decoded() );
        }
        else
        {
            $value = $json->get( 'value' );
        }

        return Arg::as( $type, $value );
    }

    static function stringToType( string $stringType ): int
    {
        switch( $stringType )
        {
            case 'binary': return Arg::BINARY;
            case 'boolean': return Arg::BOOLEAN;
            case 'integer': return Arg::INTEGER;
            case 'string': return Arg::STRING;
            case 'list': return Arg::LIST;
            default: throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $stringType ) . '`', ExceptionCode::UNKNOWN_TYPE );
        }
    }

    static function typeToString( int $type ): string
    {
        switch( $type )
        {
            case Arg::BINARY: return 'binary';
            case Arg::BOOLEAN: return 'boolean';
            case Arg::INTEGER: return 'integer';
            case Arg::STRING: return 'string';
            case Arg::LIST: return 'list';
            default: throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $type ) . '`', ExceptionCode::UNKNOWN_TYPE );
        }
    }

    function type(): int
    {
        return $this->type;
    }

    function typeAsString(): string
    {
        return Arg::typeToString( $this->type() );
    }

    function value(): Value
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    private function valueAsJson()
    {
        switch( $this->type() )
        {
            case Arg::BINARY: return Base64String::fromBytes( $this->value()->asString() )->encodedWithPrefix();
            case Arg::BOOLEAN: return $this->value()->asBoolean();
            case Arg::INTEGER: return $this->value()->asInt();
            case Arg::STRING: return $this->value()->asString();
            case Arg::LIST:
            {
                $values = [];
                foreach( $this->value()->asArray() as $arg )
                {
                    if( !( $arg instanceof Arg ) )
                        throw new Exception( __FUNCTION__ . ' failed to detect Arg class', ExceptionCode::UNEXPECTED );
                    $values[] = $arg->toJsonValue();
                }
                return $values;
            }
            default: throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $this->type() ) . '`', ExceptionCode::UNKNOWN_TYPE );
        }
    }

    /**
     * @return array<string, mixed>
     */
    function toJsonValue(): array
    {
        return [ 'type' => $this->typeAsString(), 'value' => $this->valueAsJson() ];
    }
}
