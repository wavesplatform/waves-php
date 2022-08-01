<?php declare( strict_types = 1 );

namespace Waves\Transactions\Invocation;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Common\Value;

class FunctionCall
{
    const DEFAULT_NAME = 'default';

    private string $name;
    /**
     * @var array<int, Arg>
     */
    private array $args;

    /**
     * @param string|null $name
     * @param array<int, Arg>|null $args
     * @return FunctionCall
     */
    static function as( string $name = null, array $args = null ): FunctionCall
    {
        $func = new FunctionCall;
        $func->name = $name ?? FunctionCall::DEFAULT_NAME;
        $func->args = $args ?? [];
        return $func;
    }

    static function fromJson( Json $json ): FunctionCall
    {
        $name = $json->getOr( 'function', FunctionCall::DEFAULT_NAME )->asString();
        $args = [];
        foreach( $json->get( 'args' )->asArray() as $arg )
            $args[] = Arg::fromJson( Value::as( $arg )->asJson() );
        return FunctionCall::as( $name, $args );
    }

    function name(): string
    {
        return $this->name;
    }

    function isDefault(): bool
    {
        return $this->name == FunctionCall::DEFAULT_NAME;
    }

    /**
     * @return array<int, Arg>
     */
    function args(): array
    {
        return $this->args;
    }

    /**
     * @return array<string, mixed>
     */
    function toJsonValue(): array
    {
        $args = [];
        foreach( $this->args() as $arg )
            $args[] = $arg->toJsonValue();
        return
        [
            'function' => $this->name(),
            'args' => $args,
        ];
    }

    /**
     * @param array<mixed, mixed> $args
     * @return string
     */
    static function argsBodyBytes( array $args ): string
    {
        $bytes = pack( 'N', count( $args ) );
        foreach( $args as $arg )
        {
            if( !( $arg instanceof Arg ) )
                throw new Exception( __FUNCTION__ . ' failed to detect Arg class', ExceptionCode::UNEXPECTED );
            $value = $arg->value();
            switch( $arg->type() )
            {
                case Arg::INTEGER:
                    $bytes .= chr( 0 ) . pack( 'J', $value->asInt() );
                    break;

                case Arg::BINARY:
                    $value = $value->asString();
                    $bytes .= chr( 1 ) . pack( 'N', strlen( $value ) ) . $value;
                    break;

                case Arg::STRING:
                    $value = $value->asString();
                    $bytes .= chr( 2 ) . pack( 'N', strlen( $value ) ) . $value;
                    break;

                case Arg::BOOLEAN:
                    $bytes .= chr( $value->asBoolean() ? 6 : 7 );
                    break;

                case Arg::LIST:
                    $bytes .= chr( 11 ) . FunctionCall::argsBodyBytes( $value->asArray() );
                    break;

                default:
                    throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $arg->type() ) . '`', ExceptionCode::UNKNOWN_TYPE );
            }
        }
        return $bytes;
    }

    function toBodyBytes(): string
    {
        if( $this->isDefault() )
            return chr( 0 );

        $bytes = chr( 1 ) . chr( 9 ). chr( 1 );
        $bytes .= pack( 'N', strlen( $this->name() ) ) . $this->name();
        $bytes .= FunctionCall::argsBodyBytes( $this->args() );
        return $bytes;
    }
}
