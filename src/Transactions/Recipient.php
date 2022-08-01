<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\Address;
use Waves\Common\ExceptionCode;
use Waves\Model\Alias;

class Recipient
{
    private Address $address;
    private Alias $alias;

    private function __construct(){}

    static function fromAddress( Address $address ): Recipient
    {
        $recipient = new Recipient;
        $recipient->address = $address;
        return $recipient;
    }

    static function fromAlias( Alias $alias ): Recipient
    {
        $recipient = new Recipient;
        $recipient->alias = $alias;
        return $recipient;
    }

    static function fromAddressOrAlias( string $addressOrAlias ): Recipient
    {
        if( strlen( $addressOrAlias ) === Address::STRING_LENGTH )
            return Recipient::fromAddress( Address::fromString( $addressOrAlias ) );
        try
        {
            return Recipient::fromAlias( Alias::fromFullAlias( $addressOrAlias ) );
        }
        catch( Exception $e )
        {
            return Recipient::fromAlias( Alias::fromString( $addressOrAlias ) );
        }
    }

    function isAlias(): bool
    {
        return isset( $this->alias );
    }

    function toString(): string
    {
        if( $this->isAlias() )
            return $this->alias->toString();
        return $this->address->toString();
    }

    function address(): Address
    {
        return $this->address;
    }

    function alias(): Alias
    {
        return $this->alias;
    }

    function toProtobuf(): \Waves\Protobuf\Recipient
    {
        $pb_Recipient = new \Waves\Protobuf\Recipient;
        if( $this->isAlias() )
            $pb_Recipient->setAlias( $this->alias()->name() );
        else
            $pb_Recipient->setPublicKeyHash( $this->address()->publicKeyHash() );
        return $pb_Recipient;
    }
}
