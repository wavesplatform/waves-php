<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\PrivateKey;
use Waves\Common\Base58String;
use Waves\Account\PublicKey;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Model\ChainId;
use Waves\Model\Id;

use Waves\Transactions\LeaseCancelTransaction as CurrentTransaction;

class LeaseCancelTransaction extends Transaction
{
    const TYPE = 9;
    const LATEST_VERSION = 3;
    const MIN_FEE = 100_000;

    private Id $leaseId;

    static function build( PublicKey $sender, Id $leaseId ): CurrentTransaction
    {
        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, CurrentTransaction::MIN_FEE );

        // LEASE_CANCEL TRANSACTION
        {
            $tx->setLeaseId( $leaseId );
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

        // LEASE_CANCEL TRANSACTION
        {
            $pb_TransactionData = new \Waves\Protobuf\LeaseCancelTransactionData;
            // ID
            {
                $pb_TransactionData->setLeaseId( $this->leaseId()->bytes() );
            }
        }

        // LEASE_CANCEL TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setLeaseCancel( $pb_TransactionData )->serializeToString() );
        return $this;
    }

    function leaseId(): Id
    {
        if( !isset( $this->leaseId ) )
            $this->leaseId = $this->json->get( 'leaseId' )->asId();
        return $this->leaseId;
    }

    function setLeaseId( Id $leaseId ): CurrentTransaction
    {
        $this->leaseId = $leaseId;
        $this->json->put( 'leaseId', $leaseId->toString() );
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
