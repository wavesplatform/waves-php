<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\PrivateKey;
use Waves\Common\Base58String;
use Waves\Account\PublicKey;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Model\ChainId;

use Waves\Transactions\LeaseTransaction as CurrentTransaction;

class LeaseTransaction extends Transaction
{
    const TYPE = 8;
    const LATEST_VERSION = 3;
    const MIN_FEE = 100_000;

    private Recipient $recipient;
    private int $amount;

    static function build( PublicKey $sender, Recipient $recipient, int $amount ): CurrentTransaction
    {
        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, CurrentTransaction::MIN_FEE );

        // LEASE TRANSACTION
        {
            $tx->setRecipient( $recipient );
            $tx->setAmount( $amount );
        }

        return $tx;
    }

    function getUnsigned(): CurrentTransaction
    {
        // VERSION
        if( $this->version() !== CurrentTransaction::LATEST_VERSION )
            throw new Exception( __FUNCTION__ . ' unexpected version = ' . $this->version(), ExceptionCode::UNEXPECTED );

        // BASE
        $pb_Transaction = $this->getProtobufTransactionBase();

        // LEASE TRANSACTION
        {
            $pb_TransactionData = new \Waves\Protobuf\LeaseTransactionData;
            // RECIPIENT
            {
                $pb_TransactionData->setRecipient( $this->recipient()->toProtobuf() );
            }
            // AMOUNT
            {
                $pb_TransactionData->setAmount( $this->amount() );
            }
        }

        // LEASE TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setLease( $pb_TransactionData )->serializeToString() );
        return $this;
    }

    function recipient(): Recipient
    {
        if( !isset( $this->recipient ) )
            $this->recipient = $this->json->get( 'recipient' )->asRecipient();
        return $this->recipient;
    }

    function setRecipient( Recipient $recipient ): CurrentTransaction
    {
        $this->recipient = $recipient;
        $this->json->put( 'recipient', $recipient->toString() );
        return $this;
    }

    function amount(): int
    {
        if( !isset( $this->amount ) )
            $this->amount = $this->json->get( 'amount' )->asInt();
        return $this->amount;
    }

    function setAmount( int $amount ): CurrentTransaction
    {
        $this->amount = $amount;
        $this->json->put( 'amount', $amount );
        return $this;
    }

    // COMMON

    function __construct( Json $json = null )
    {
        parent::__construct( $json );
    }

    function addProof( PrivateKey $privateKey, int $index = null ): CurrentTransaction
    {
        $proof = (new \deemru\WavesKit)->sign( $this->bodyBytes(), $privateKey->bytes() );
        if( $proof === false )
            throw new Exception( __FUNCTION__ . ' unexpected sign() error', ExceptionCode::UNEXPECTED );
        $proof = Base58String::fromBytes( $proof )->encoded();

        $proofs = $this->proofs();
        if( !isset( $index ) )
            $proofs[] = $proof;
        else
            $proofs[$index] = $proof;
        return $this->setProofs( $proofs );
    }

    /**
     * @return CurrentTransaction
     */
    function setType( int $type )
    {
        parent::setType( $type );
        return $this;
    }

    /**
     * @return CurrentTransaction
     */
    function setSender( PublicKey $sender )
    {
        parent::setSender( $sender );
        return $this;
    }

    /**
     * @return CurrentTransaction
     */
    function setVersion( int $version )
    {
        parent::setVersion( $version );
        return $this;
    }

    /**
     * @return CurrentTransaction
     */
    function setFee( Amount $fee )
    {
        parent::setFee( $fee );
        return $this;
    }

    /**
     * @return CurrentTransaction
     */
    function setChainId( ChainId $chainId = null )
    {
        parent::setChainId( $chainId );
        return $this;
    }

    /**
     * @return CurrentTransaction
     */
    function setTimestamp( int $timestamp = null )
    {
        parent::setTimestamp( $timestamp );
        return $this;
    }

    /**
     * @param array<int, string> $proofs
     * @return CurrentTransaction
     */
    function setProofs( array $proofs = null )
    {
        parent::setProofs( $proofs );
        return $this;
    }

    function bodyBytes(): string
    {
        if( !isset( $this->bodyBytes ) )
            $this->getUnsigned();
        return parent::bodyBytes();
    }
}
