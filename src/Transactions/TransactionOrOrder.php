<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Waves\Common\JsonBase;
use Waves\Account\PublicKey;
use Waves\Model\ChainId;
use Waves\Model\Id;
use Waves\Model\WavesConfig;
use Waves\Util\Functions;

class TransactionOrOrder extends JsonBase
{
    private Id $id;
    private int $version;
    private ChainId $chainId;
    private PublicKey $sender;
    private int $timestamp;
    private Amount $fee;
    /**
     * @var array<int, string>
     */
    private array $proofs;
    private string $bodyBytes;

    function id(): Id
    {
        if( !isset( $this->id ) )
        {
            if( !$this->json()->exists( 'id' ) && isset( $this->bodyBytes ) )
                $this->setId( Functions::calculateTransactionId( $this->bodyBytes ) );
            else
                $this->id = $this->json->get( 'id' )->asId();
        }
        return $this->id;
    }

    function setId( Id $id ): void
    {
        $this->id = $id;
        $this->json->put( 'id', $id->toString() );
    }

    function version(): int
    {
        if( !isset( $this->version ) )
            $this->version = $this->json->get( 'version' )->asInt();
        return $this->version;
    }

    /**
     * @return mixed
     */
    function setVersion( int $version )
    {
        $this->version = $version;
        $this->json->put( 'version', $version );
        return $this;
    }

    function chainId(): ChainId
    {
        if( !isset( $this->chainId ) )
        {
            if( $this->json->exists( 'chainId' ) )
                $this->chainId = $this->json->get( 'chainId' )->asChainId();
            else if( $this->json->exists( 'sender' ) )
                $this->chainId = $this->json->get( 'sender' )->asAddress()->chainId();
            else
                $this->chainId = WavesConfig::chainId();
        }
        return $this->chainId;
    }

    /**
     * @return mixed
     */
    function setChainId( ChainId $chainId = null )
    {
        if( !isset( $chainId ) )
            $chainId = WavesConfig::chainId();
        $this->chainId = $chainId;
        $this->json->put( 'chainId', $chainId->asInt() );
        return $this;
    }

    function sender(): PublicKey
    {
        if( !isset( $this->sender ) )
        {
            $this->sender = $this->json->get( 'senderPublicKey' )->asPublicKey();
            if( $this->json->exists( 'sender' ) )
                $this->sender->attachAddress( $this->json->get( 'sender' )->asAddress() );
        }
        return $this->sender;
    }

    /**
     * @return mixed
     */
    function setSender( PublicKey $sender )
    {
        $this->sender = $sender;
        $this->json->put( 'senderPublicKey', $sender->toString() );
        $this->json->put( 'sender', $sender->address()->toString() );
        return $this;
    }

    function timestamp(): int
    {
        if( !isset( $this->timestamp ) )
            $this->timestamp = $this->json->get( 'timestamp' )->asInt();
        return $this->timestamp;
    }

    /**
     * @return mixed
     */
    function setTimestamp( int $timestamp = null )
    {
        if( !isset( $timestamp ) )
            $timestamp = intval( microtime( true ) * 1000 );
        $this->timestamp = $timestamp;
        $this->json->put( 'timestamp', $timestamp );
        return $this;
    }

    function fee(): Amount
    {
        if( !isset( $this->fee ) )
            $this->fee = Amount::fromJson( $this->json, 'fee', 'feeAssetId' );
        return $this->fee;
    }

    /**
     * @return mixed
     */
    function setFee( Amount $fee )
    {
        $this->fee = $fee;
        $this->json->put( 'fee', $fee->value() );
        $this->json->put( 'feeAssetId', $fee->assetId()->toJsonValue() );
        return $this;
    }

    /**
     * @return array<int, string>
     */
    function proofs(): array
    {
        if( !isset( $this->proofs ) )
            $this->proofs = $this->json->getOr( 'proofs', [] )->asArrayString();
        return $this->proofs;
    }

    /**
     * @param array<int, string> $proofs
     * @return mixed
     */
    function setProofs( array $proofs = null )
    {
        if( !isset( $proofs ) )
            $proofs = [];
        $this->proofs = $proofs;
        $this->json->put( 'proofs', $proofs );
        return $this;
    }

    function bodyBytes(): string
    {
        return $this->bodyBytes;
    }

    protected function setBodyBytes( string $bodyBytes ): void
    {
        $this->bodyBytes = $bodyBytes;
    }
}
