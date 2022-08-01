<?php declare( strict_types = 1 );

namespace Waves\Transactions;

use Waves\Common\Json;
use Waves\Model\AssetId;

class Amount
{
    private int $amount;
    private AssetId $assetId;

    function __construct( int $amount, AssetId $assetId = null )
    {
        $this->amount = $amount;
        $this->assetId = $assetId ?? AssetId::WAVES();
    }

    static function of( int $amount, AssetId $assetId = null ): Amount
    {
        return new Amount( $amount, $assetId );
    }

    static function fromJson( Json $json, string $amountKey = 'amount', string $assetIdKey = ' assetId' ): Amount
    {
        return Amount::of( $json->get( $amountKey )->asInt(), $json->getOr( $assetIdKey, AssetId::WAVES_STRING )->asAssetId() );
    }

    function value(): int
    {
        return $this->amount;
    }

    function assetId(): AssetId
    {
        return $this->assetId;
    }

    function toString(): string
    {
        return serialize( $this );
    }

    function toProtobuf(): \Waves\Protobuf\Amount
    {
        $pb_Amount = new \Waves\Protobuf\Amount;
        $pb_Amount->setAmount( $this->value() );
        if( !$this->assetId()->isWaves() )
            $pb_Amount->setAssetId( $this->assetId()->bytes() );
        return $pb_Amount;
    }
}
