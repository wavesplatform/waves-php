<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\PrivateKey;
use Waves\Common\Base58String;
use Waves\Account\PublicKey;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Model\AssetId;
use Waves\Model\ChainId;

use Waves\Transactions\UpdateAssetInfoTransaction as CurrentTransaction;

class UpdateAssetInfoTransaction extends Transaction
{
    const TYPE = 17;
    const LATEST_VERSION = 1;
    const MIN_FEE = 100_000;

    private AssetId $assetId;
    private string $name;
    private string $description;

    static function build( PublicKey $sender, AssetId $assetId, string $name, string $description ): CurrentTransaction
    {
        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, CurrentTransaction::MIN_FEE );

        // RENAME TRANSACTION
        {
            $tx->setAssetId( $assetId );
            $tx->setName( $name );
            $tx->setDescription( $description );
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

        // RENAME TRANSACTION
        {
            $pb_TransactionData = new \Waves\Protobuf\UpdateAssetInfoTransactionData;
            // ASSET
            {
                $pb_TransactionData->setAssetId( $this->assetId()->bytes() );
            }
            // NAME
            {
                $pb_TransactionData->setName( $this->name() );
            }
            // DESCRIPTION
            {
                $pb_TransactionData->setDescription( $this->description() );
            }
        }

        // RENAME TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setUpdateAssetInfo( $pb_TransactionData )->serializeToString() );
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

    function name(): string
    {
        if( !isset( $this->name ) )
            $this->name = $this->json->get( 'name' )->asString();
        return $this->name;
    }

    function setName( string $name ): CurrentTransaction
    {
        $this->name = $name;
        $this->json->put( 'name', $name );
        return $this;
    }

    function description(): string
    {
        if( !isset( $this->description ) )
            $this->description = $this->json->get( 'description' )->asString();
        return $this->description;
    }

    function setDescription( string $description ): CurrentTransaction
    {
        $this->description = $description;
        $this->json->put( 'description', $description );
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
