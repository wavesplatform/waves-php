<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Waves\Account\PublicKey;

class Transaction extends TransactionOrOrder
{
    private int $type;

    function type(): int
    {
        if( !isset( $this->type ) )
            $this->type = $this->json->get( 'type' )->asInt();
        return $this->type;
    }

    /**
     * @return mixed
     */
    function setType( int $type )
    {
        $this->type = $type;
        $this->json->put( 'type', $type );
        return $this;
    }

    protected function setBase( PublicKey $sender, int $type, int $version, int $minFee ): void
    {
        $this->setSender( $sender );
        $this->setType( $type );
        $this->setVersion( $version );
        $this->setFee( Amount::of( $minFee ) );

        $this->setChainId();
        $this->setTimestamp();
        $this->setProofs();
    }

    function getProtobufTransactionBase(): \Waves\Protobuf\Transaction
    {
        $pb_Transaction = new \Waves\Protobuf\Transaction();
        $pb_Transaction->setSenderPublicKey( $this->sender()->bytes() );
        $pb_Transaction->setVersion( $this->version() );
        $pb_Transaction->setFee( $this->fee()->toProtobuf() );
        $pb_Transaction->setChainId( $this->chainId()->asInt() );
        $pb_Transaction->setTimestamp( $this->timestamp() );

        return $pb_Transaction;
    }
}
