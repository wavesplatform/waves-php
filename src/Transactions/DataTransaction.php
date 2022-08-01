<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\PrivateKey;
use Waves\Common\Base58String;
use Waves\Account\PublicKey;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Model\ChainId;
use Waves\Model\DataEntry;

use Waves\Transactions\DataTransaction as CurrentTransaction;

class DataTransaction extends Transaction
{
    const TYPE = 12;
    const LATEST_VERSION = 2;
    const MIN_FEE = 100_000;

    /**
     * @var array<int, DataEntry>
     */
    private array $data;

    /**
     * @param PublicKey $sender
     * @param array<int, DataEntry> $data
     * @return CurrentTransaction
     */
    static function build( PublicKey $sender, array $data ): CurrentTransaction
    {
        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, CurrentTransaction::MIN_FEE );

        // DATA TRANSACTION
        {
            $tx->setData( $data );
        }

        // ADDITIONAL FEE CALCULATION
        $tx->setFee( Amount::of( CurrentTransaction::calculateFee( strlen( $tx->bodyBytes() ) ) ) );

        return $tx;
    }

    static function calculateFee( int $bodyBytesLen ): int
    {
        return 100_000 * ( 1 + intdiv( $bodyBytesLen - 1, 1024 ) );
    }

    function getUnsigned(): CurrentTransaction
    {
        // VERSION
        if( $this->version() !== CurrentTransaction::LATEST_VERSION )
            throw new Exception( __FUNCTION__ . ' unexpected version = ' . $this->version(), ExceptionCode::UNEXPECTED );

        // BASE
        $pb_Transaction = $this->getProtobufTransactionBase();

        // DATA TRANSACTION
        {
            $pb_TransactionData = new \Waves\Protobuf\DataTransactionData;
            // DATA
            {
                $pb_Data = [];
                foreach( $this->data() as $dataEntry )
                    $pb_Data[] = $dataEntry->toProtobuf();
                $pb_TransactionData->setData( $pb_Data );
            }
        }

        // DATA TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setDataTransaction( $pb_TransactionData )->serializeToString() );
        return $this;
    }

    /**
     * @return array<int, DataEntry>
     */
    function data(): array
    {
        if( !isset( $this->data ) )
            $this->data = $this->json->get( 'data' )->asJson()->asArrayDataEntry();
        return $this->data;
    }

    /**
     * @param array<int, DataEntry> $data
     * @return CurrentTransaction
     */
    function setData( array $data ): CurrentTransaction
    {
        $this->data = $data;

        $data = [];
        foreach( $this->data as $dataEntry )
            $data[] = $dataEntry->json()->data();
        $this->json->put( 'data', $data );
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
