<?php declare( strict_types = 1 );

namespace Waves\Model;

use Exception;
use Waves\Common\Base64String;
use Waves\Common\ExceptionCode;
use Waves\Common\JsonBase;
use Waves\Common\Value;

class DataEntry extends JsonBase
{
    /**
     * @param string $key
     * @param integer $type
     * @param mixed $value
     * @return DataEntry
     */
    static function build( string $key, int $type, $value = null ): DataEntry
    {
        if( $type === EntryType::DELETE )
            $json = [ 'key' => $key, 'type' => null ];
        else
        if( !isset( $value ) )
            throw new Exception( __FUNCTION__ . ' value expected but not set', ExceptionCode::UNEXPECTED );
        else
        if( $type === EntryType::BINARY )
            $json = [ 'key' => $key, 'type' => 'binary', 'value' => Base64String::fromBytes( Value::as( $value )->asString() )->toString() ];
        else
            $json = [ 'key' => $key, 'type' => DataEntry::typeToString( $type ), 'value' => $value ];
        return new DataEntry( Value::as( $json )->asJson() );
    }

    static function binary( string $key, string $value ): DataEntry
    {
        return DataEntry::build( $key, EntryType::BINARY, $value );
    }

    static function string( string $key, string $value ): DataEntry
    {
        return DataEntry::build( $key, EntryType::STRING, $value );
    }

    static function int( string $key, int $value ): DataEntry
    {
        return DataEntry::build( $key, EntryType::INTEGER, $value );
    }

    static function boolean( string $key, bool $value ): DataEntry
    {
        return DataEntry::build( $key, EntryType::BOOLEAN, $value );
    }

    static function delete( string $key ): DataEntry
    {
        return DataEntry::build( $key, EntryType::DELETE );
    }

    static function stringToType( string $stringType ): int
    {
        switch( $stringType )
        {
            case 'binary': return EntryType::BINARY;
            case 'boolean': return EntryType::BOOLEAN;
            case 'integer': return EntryType::INTEGER;
            case 'string': return EntryType::STRING;
            default: throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $stringType ) . '`', ExceptionCode::UNKNOWN_TYPE );
        }
    }

    static function typeToString( int $type ): string
    {
        switch( $type )
        {
            case EntryType::BINARY: return 'binary';
            case EntryType::BOOLEAN: return 'boolean';
            case EntryType::INTEGER: return 'integer';
            case EntryType::STRING: return 'string';
            default: throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $type ) . '`', ExceptionCode::UNKNOWN_TYPE );
        }
    }

    function key(): string { return $this->json->get( 'key' )->asString(); }

    function type(): int
    {
        if( !$this->json->exists( 'type' ) )
            return EntryType::DELETE;
        return $this->stringToType( $this->json->get( 'type' )->asString() );
    }

    /**
     * Returns value of native type
     *
     * @return bool|int|string|null
     */
    function value()
    {
        switch( $this->type() )
        {
            case EntryType::BINARY: return $this->json->get( 'value' )->asBase64Decoded();
            case EntryType::BOOLEAN: return $this->json->get( 'value' )->asBoolean();
            case EntryType::INTEGER: return $this->json->get( 'value' )->asInt();
            case EntryType::STRING: return $this->json->get( 'value' )->asString();
            case EntryType::DELETE: return null;
            default: throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $this->type() ) . '`', ExceptionCode::UNKNOWN_TYPE ); // @codeCoverageIgnore
        }
    }

    function stringValue(): string
    {
        return Value::as( $this->value() )->asString();
    }

    function intValue(): int
    {
        return Value::as( $this->value() )->asInt();
    }

    function booleanValue(): bool
    {
        return Value::as( $this->value() )->asBoolean();
    }

    function toProtobuf(): \Waves\Protobuf\DataTransactionData\DataEntry
    {
        $pb_DataEntry = new \Waves\Protobuf\DataTransactionData\DataEntry;
        $pb_DataEntry->setKey( $this->key() );
        switch( $this->type() )
        {
            case EntryType::BINARY: $pb_DataEntry->setBinaryValue( $this->json->get( 'value' )->asBase64Decoded() ); break;
            case EntryType::BOOLEAN: $pb_DataEntry->setBoolValue( $this->json->get( 'value' )->asBoolean() ); break;
            case EntryType::INTEGER: $pb_DataEntry->setIntValue( $this->json->get( 'value' )->asInt() ); break;
            case EntryType::STRING: $pb_DataEntry->setStringValue( $this->json->get( 'value' )->asString() ); break;
            case EntryType::DELETE: break;
            default: throw new Exception( __FUNCTION__ . ' failed to detect type `' . serialize( $this->type() ) . '`', ExceptionCode::UNKNOWN_TYPE ); // @codeCoverageIgnore
        }
        return $pb_DataEntry;
    }
}
