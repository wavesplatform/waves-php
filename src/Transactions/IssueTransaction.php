<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\PrivateKey;
use Waves\Common\Base58String;
use Waves\Account\PublicKey;
use Waves\Common\Base64String;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Model\ChainId;

use Waves\Transactions\IssueTransaction as CurrentTransaction;

class IssueTransaction extends Transaction
{
    const TYPE = 3;
    const LATEST_VERSION = 3;
    const MIN_FEE = 100_000_000;
    const NFT_MIN_FEE = 100_000;

    private string $name;
    private string $description;
    private int $quantity;
    private int $decimals;
    private bool $isReissuable;
    private Base64String $script;

    static function build( PublicKey $sender, string $name, string $description, int $quantity, int $decimals,
                           bool $isReissuable, Base64String $script = null ): CurrentTransaction
    {
        $minFee = ( $quantity === 1 && $decimals === 0 && $isReissuable === false ) ? CurrentTransaction::NFT_MIN_FEE : CurrentTransaction::MIN_FEE;

        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, $minFee );

        // ISSUE TRANSACTION
        {
            $tx->setName( $name );
            $tx->setDescription( $description );
            $tx->setQuantity( $quantity );
            $tx->setDecimals( $decimals );
            $tx->setIsReissuable( $isReissuable );
            $tx->setScript( $script );
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

        // ISSUE TRANSACTION
        {
            $pb_TransactionData = new \Waves\Protobuf\IssueTransactionData;
            // NAME
            {
                $pb_TransactionData->setName( $this->name() );
            }
            // DESCRIPTION
            {
                $pb_TransactionData->setDescription( $this->description() );
            }
            // QUANTITY
            {
                $pb_TransactionData->setAmount( $this->quantity() );
            }
            // DECIMALS
            {
                $pb_TransactionData->setDecimals( $this->decimals() );
            }
            // REISSUABLE
            {
                $pb_TransactionData->setReissuable( $this->isReissuable() );
            }
            // SCRIPT
            {
                $pb_TransactionData->setScript( $this->script()->bytes() );
            }
        }

        // ISSUE TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setIssue( $pb_TransactionData )->serializeToString() );
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

    function quantity(): int
    {
        if( !isset( $this->quantity ) )
            $this->quantity = $this->json->get( 'quantity' )->asInt();
        return $this->quantity;
    }

    function setQuantity( int $quantity ): CurrentTransaction
    {
        $this->quantity = $quantity;
        $this->json->put( 'quantity', $quantity );
        return $this;
    }

    function decimals(): int
    {
        if( !isset( $this->decimals ) )
            $this->decimals = $this->json->get( 'decimals' )->asInt();
        return $this->decimals;
    }

    function setDecimals( int $decimals ): CurrentTransaction
    {
        $this->decimals = $decimals;
        $this->json->put( 'decimals', $decimals );
        return $this;
    }

    function isReissuable(): bool
    {
        if( !isset( $this->isReissuable ) )
            $this->isReissuable = $this->json->get( 'reissuable' )->asBoolean();
        return $this->isReissuable;
    }

    function setIsReissuable( bool $isReissuable ): CurrentTransaction
    {
        $this->isReissuable = $isReissuable;
        $this->json->put( 'reissuable', $isReissuable );
        return $this;
    }

    function script(): Base64String
    {
        if( !isset( $this->script ) )
            $this->script = $this->json->exists( 'script' ) ? $this->json->get( 'script' )->asBase64String() : Base64String::emptyString();
        return $this->script;
    }

    function setScript( Base64String $script = null ): CurrentTransaction
    {
        $script = $script ?? Base64String::emptyString();
        $this->script = $script;
        $this->json->put( 'script', $script->toJsonValue() );
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
