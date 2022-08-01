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

use Waves\Transactions\SetScriptTransaction as CurrentTransaction;

class SetScriptTransaction extends Transaction
{
    const TYPE = 13;
    const LATEST_VERSION = 2;
    const MIN_FEE = 100_000;

    private Base64String $script;

    static function build( PublicKey $sender, Base64String $script ): CurrentTransaction
    {
        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, CurrentTransaction::MIN_FEE );

        // SET_SCRIPT TRANSACTION
        {
            $tx->setScript( $script );
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

        // SET_SCRIPT TRANSACTION
        {
            $pb_TransactionData = new \Waves\Protobuf\SetScriptTransactionData;
            // SCRIPT
            {
                $pb_TransactionData->setScript( $this->script()->bytes() );
            }
        }

        // SET_SCRIPT TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setSetScript( $pb_TransactionData )->serializeToString() );
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
