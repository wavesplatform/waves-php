<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\PrivateKey;
use Waves\Common\Base58String;
use Waves\Account\PublicKey;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Common\Value;
use Waves\Model\AssetId;
use Waves\Model\ChainId;
use Waves\Transactions\Mass\Transfer;

use Waves\Transactions\MassTransferTransaction as CurrentTransaction;

class MassTransferTransaction extends Transaction
{
    const TYPE = 11;
    const LATEST_VERSION = 2;
    const MIN_FEE = 100_000;

    /**
     * @var array<int, Transfer>
     */
    private array $transfers;
    private AssetId $assetId;
    private Base58String $attachment;

    /**
     * @param PublicKey $sender
     * @param AssetId $assetId
     * @param array<int, Transfer> $transfers
     * @param Base58String $attachment
     * @return CurrentTransaction
     */
    static function build( PublicKey $sender, AssetId $assetId, array $transfers, Base58String $attachment = null ): CurrentTransaction
    {
        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, CurrentTransaction::calculateFee( count( $transfers ) ) );

        // MASS_TRANSFER TRANSACTION
        {
            $tx->setAssetId( $assetId );
            $tx->setTransfers( $transfers );
            $tx->setAttachment( $attachment );
        }

        return $tx;
    }

    static function calculateFee( int $transfersCount ): int
    {
        return 100_000 + ( $transfersCount + ( $transfersCount & 1 ) ) * 50_000;
    }

    function getUnsigned(): CurrentTransaction
    {
        // VERSION
        if( $this->version() !== CurrentTransaction::LATEST_VERSION )
            throw new Exception( __FUNCTION__ . ' unexpected version = ' . $this->version(), ExceptionCode::UNEXPECTED );

        // BASE
        $pb_Transaction = $this->getProtobufTransactionBase();

        // MASS_TRANSFER TRANSACTION
        {
            $pb_TransactionData = new \Waves\Protobuf\MassTransferTransactionData;
            // TRANSFERS
            {
                $pb_Transfers = [];
                foreach( $this->transfers() as $transfer )
                {
                    $pb_Transfer = new \Waves\Protobuf\MassTransferTransactionData\Transfer;
                    $pb_Transfer->setRecipient( $transfer->recipient()->toProtobuf() );
                    $pb_Transfer->setAmount( $transfer->amount() );
                    $pb_Transfers[] = $pb_Transfer;
                }

                $pb_TransactionData->setTransfers( $pb_Transfers );
            }
            // ASSET
            {
                $pb_TransactionData->setAssetId( $this->assetId()->bytes() );
            }
            // ATTACHMENT
            {
                $pb_TransactionData->setAttachment( $this->attachment()->bytes() );
            }
        }

        // MASS_TRANSFER TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setMassTransfer( $pb_TransactionData )->serializeToString() );
        return $this;
    }

    function assetId(): AssetId
    {
        if( !isset( $this->assetId ) )
            $this->assetId = $this->json->get( 'assetId' )->asAssetId();
        return $this->assetId;
    }

    function setAssetId( AssetId $assetId ): CurrentTransaction
    {
        $this->assetId = $assetId;
        $this->json->put( 'assetId', $assetId->toJsonValue() );
        return $this;
    }

    /**
     * @return array<int, Transfer>
     */
    function transfers(): array
    {
        if( !isset( $this->transfers ) )
        {
            $transfers = [];
            foreach( $this->json->get( 'amount' )->asArray() as $value )
            {
                $json = Value::as( $value )->asJson();
                $recipient = $json->get( 'recipient' )->asRecipient();
                $amount = $json->get( 'amount' )->asInt();
                $transfers[] = new Transfer( $recipient, $amount );
            }
            $this->transfers = $transfers;
        }
        return $this->transfers;
    }

    /**
     * @param array<int, Transfer> $transfers
     * @return CurrentTransaction
     */
    function setTransfers( array $transfers ): CurrentTransaction
    {
        $this->transfers = $transfers;

        $transfers = [];
        foreach( $this->transfers as $transfer )
            $transfers[] = [ 'recipient' => $transfer->recipient()->toString(), 'amount' => $transfer->amount() ];
        $this->json->put( 'transfers', $transfers );
        return $this;
    }

    function attachment(): Base58String
    {
        if( !isset( $this->attachment ) )
            $this->attachment = $this->json->get( 'attachment' )->asBase58String();
        return $this->attachment;
    }

    function setAttachment( Base58String $attachment = null ): CurrentTransaction
    {
        $attachment = $attachment ?? Base58String::emptyString();
        $this->attachment = $attachment;
        $this->json->put( 'attachment', $attachment->toString() );
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
