<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Exception;
use Waves\Account\PrivateKey;
use Waves\Common\Base58String;
use Waves\Account\PublicKey;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Common\Value;
use Waves\Model\ChainId;
use Waves\Transactions\Invocation\FunctionCall;

use Waves\Transactions\InvokeScriptTransaction as CurrentTransaction;

class InvokeScriptTransaction extends Transaction
{
    const TYPE = 16;
    const LATEST_VERSION = 2;
    const MIN_FEE = 500_000;

    private Recipient $dApp;
    private FunctionCall $function;
    /**
     * @var array<int, Amount>
     */
    private array $payments;

    /**
     * @param PublicKey $sender
     * @param Recipient $dApp
     * @param FunctionCall|null $function
     * @param array<int, Amount>|null $payments
     * @return CurrentTransaction
     */
    static function build( PublicKey $sender, Recipient $dApp, FunctionCall $function = null, array $payments = null ): CurrentTransaction
    {
        $tx = new CurrentTransaction;
        $tx->setBase( $sender, CurrentTransaction::TYPE, CurrentTransaction::LATEST_VERSION, CurrentTransaction::MIN_FEE );

        // INVOKE TRANSACTION
        {
            $tx->setDApp( $dApp );
            $tx->setFunction( $function );
            $tx->setPayments( $payments );
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

        // INVOKE TRANSACTION
        {

            $pb_TransactionData = new \Waves\Protobuf\InvokeScriptTransactionData;
            // DAPP
            {
                $pb_TransactionData->setDApp( $this->dApp()->toProtobuf() );
            }
            // FUNCTION
            {
                $pb_TransactionData->setFunctionCall( $this->function()->toBodyBytes() );
            }
            // PAYMENTS
            {
                $pb_Payments = [];
                foreach( $this->payments() as $payment )
                    $pb_Payments[] = $payment->toProtobuf();
                $pb_TransactionData->setPayments( $pb_Payments );
            }
        }

        // INVOKE TRANSACTION
        $this->setBodyBytes( $pb_Transaction->setInvokeScript( $pb_TransactionData )->serializeToString() );
        return $this;
    }

    function dApp(): Recipient
    {
        if( !isset( $this->dApp ) )
            $this->dApp = $this->json->get( 'dApp' )->asRecipient();
        return $this->dApp;
    }

    function setDApp( Recipient $dApp ): CurrentTransaction
    {
        $this->dApp = $dApp;
        $this->json->put( 'dApp', $dApp->toString() );
        return $this;
    }

    function function(): FunctionCall
    {
        if( !isset( $this->function ) )
            $this->function = FunctionCall::fromJson( $this->json->get( 'call' )->asJson() );
        return $this->function;
    }

    function setFunction( FunctionCall $function = null ): CurrentTransaction
    {
        $function = $function ?? FunctionCall::as();
        $this->function = $function;
        $this->json->put( 'call', $function->toJsonValue() );
        return $this;
    }

    /**
     * @return array<int, Amount>
     */
    function payments(): array
    {
        if( !isset( $this->payments ) )
        {
            $payments = [];
            foreach( $this->json->get( 'payment' )->asArray() as $value )
                $payments[] = Amount::fromJson( Value::as( $value )->asJson() );
            $this->payments = $payments;
        }
        return $this->payments;
    }

    /**
     * @param array<int, Amount>|null $payments
     * @return CurrentTransaction
     */
    function setPayments( array $payments = null ): CurrentTransaction
    {
        $this->payments = $payments ?? [];

        $payments = [];
        foreach( $this->payments as $payment )
            $payments[] = [ 'amount' => $payment->value(), 'assetId' => $payment->assetId()->toJsonValue() ];
        $this->json->put( 'payment', $payments );
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
