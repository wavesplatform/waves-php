<?php declare( strict_types = 1 );

namespace Waves\Common;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Model\ArgMeta;
use Waves\Account\Address;
use Waves\Account\PublicKey;
use Waves\Model\AssetId;
use Waves\Model\Id;
use Waves\Model\ChainId;
use Waves\Model\ApplicationStatus;
use Waves\Model\Status;
use Waves\Transactions\Recipient;

class Value
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @param mixed $value
     */
    private function __construct( $value )
    {
        $this->value = $value;
    }

    /**
    * Value function constructor
    *
    * @param mixed $value
    * @return Value
    */
    static function as( $value ): Value
    {
        return new Value( $value );
    }

    /**
    * Gets an boolean value
    *
    * @return bool
    */
    function asBoolean(): bool
    {
        if( !is_bool( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect boolean at `' . json_encode( $this->value ) . '`', ExceptionCode::BOOL_EXPECTED );
        return $this->value;
    }

    /**
    * Gets an integer value
    *
    * @return int
    */
    function asInt(): int
    {
        if( !is_int( $this->value ) )
        {
            if( is_string( $this->value ) )
            {
                $intval = intval( $this->value );
                if( strval( $intval ) === $this->value )
                    return $intval;
            }
            throw new Exception( __FUNCTION__ . ' failed to detect integer at `' . json_encode( $this->value ) . '`', ExceptionCode::INT_EXPECTED );
        }
        return $this->value;
    }

    /**
    * Gets a string value
    *
    * @return string
    */
    function asString(): string
    {
        if( !is_string( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect string at `' . json_encode( $this->value ) . '`', ExceptionCode::STRING_EXPECTED );
        return $this->value;
    }

    function asBase64String(): Base64String
    {
        if( !is_string( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect string at `' . json_encode( $this->value ) . '`', ExceptionCode::STRING_EXPECTED );
        return Base64String::fromString( $this->value );
    }

    function asChainId(): ChainId
    {
        if( is_int( $this->value ) )
            return ChainId::fromInt( $this->value );
        return ChainId::fromString( $this->asString() );
    }

    /**
    * Gets a base64 decoded string value
    *
    * @return string
    */
    function asBase64Decoded(): string
    {
        if( !is_string( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect string at `' . json_encode( $this->value ) . '`', ExceptionCode::STRING_EXPECTED );
        if( substr( $this->value, 0, 7 ) !== 'base64:' )
            throw new Exception( __FUNCTION__ . ' failed to detect base64 `' . $this->value . '`', ExceptionCode::BASE64_DECODE );
        $decoded = base64_decode( substr( $this->value, 7 ) );
        if( !is_string( $decoded ) )
            throw new Exception( __FUNCTION__ . ' failed to decode base64 `' . substr( $this->value, 7 ) . '`', ExceptionCode::BASE64_DECODE );
        return $decoded;
    }

    function asBase58String(): Base58String
    {
        return Base58String::fromString( $this->asString() );
    }

    /**
    * Gets a Json value
    *
    * @return Json
    */
    function asJson(): Json
    {
        if( !is_array( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect Json at `' . json_encode( $this->value ) . '`', ExceptionCode::ARRAY_EXPECTED );
        return Json::as( $this->value );
    }

    /**
    * Gets an array value
    *
    * @return array<mixed, mixed>
    */
    function asArray(): array
    {
        if( !is_array( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect array at `' . json_encode( $this->value ) . '`', ExceptionCode::ARRAY_EXPECTED );
        return $this->value;
    }

    /**
    * Gets an array of integers value
    *
    * @return array<int, int>
    */
    function asArrayInt(): array
    {
        if( !is_array( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect array at `' . json_encode( $this->value ) . '`', ExceptionCode::ARRAY_EXPECTED );
        $ints = [];
        foreach( $this->value as $value )
            $ints[] = Value::as( $value )->asInt();
        return $ints;
    }

    /**
    * @return array<int, string>
    */
    function asArrayString(): array
    {
        if( !is_array( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect array at `' . json_encode( $this->value ) . '`', ExceptionCode::ARRAY_EXPECTED );
        $strings = [];
        foreach( $this->value as $value )
            $strings[] = Value::as( $value )->asString();
        return $strings;
    }

    /**
    * Gets an array of string to integer map
    *
    * @return array<string, int>
    */
    function asMapStringInt(): array
    {
        if( !is_array( $this->value ) )
            throw new Exception( __FUNCTION__ . ' failed to detect array at `' . json_encode( $this->value ) . '`', ExceptionCode::ARRAY_EXPECTED );
        $ints = [];
        foreach( $this->value as $key => $value )
            $ints[Value::as( $key )->asString()] = Value::as( $value )->asInt();
        return $ints;
    }

    function asArgMeta(): ArgMeta
    {
        return new ArgMeta( $this->asJson() );
    }

    /**
    * Gets an Address value
    *
    * @return Address
    */
    function asAddress(): Address
    {
        return Address::fromString( $this->asString() );
    }

    /**
    * @return Recipient
    */
    function asRecipient(): Recipient
    {
        return Recipient::fromAddressOrAlias( $this->asString() );
    }

    function asPublicKey(): PublicKey
    {
        return PublicKey::fromString( $this->asString() );
    }

    /**
    * Gets an AssetId value
    *
    * @return AssetId
    */
    function asAssetId(): AssetId
    {
        return isset( $this->value ) ? AssetId::fromString( $this->asString() ) : AssetId::WAVES();
    }

    /**
    * Gets an Id value
    *
    * @return Id
    */
    function asId(): Id
    {
        return Id::fromString( $this->asString() );
    }

    function asApplicationStatus(): int
    {
        switch( $this->asString() )
        {
            case ApplicationStatus::SUCCEEDED_S: return ApplicationStatus::SUCCEEDED;
            case ApplicationStatus::SCRIPT_EXECUTION_FAILED_S: return ApplicationStatus::SCRIPT_EXECUTION_FAILED;
            default: return ApplicationStatus::UNKNOWN;
        }
    }

    function asStatus(): int
    {
        switch( $this->asString() )
        {
            case Status::CONFIRMED_S: return Status::CONFIRMED;
            case Status::UNCONFIRMED_S: return Status::UNCONFIRMED;
            case Status::NOT_FOUND_S: return Status::NOT_FOUND;
            default: return Status::UNKNOWN;
        }
    }
}
