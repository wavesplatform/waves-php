<?php declare( strict_types = 1 );

namespace Waves\Common;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Transactions\Transaction;
use Waves\Account\Address;
use Waves\Model\Alias;
use Waves\Model\BalanceDetails;
use Waves\Model\DataEntry;
use Waves\Model\ScriptInfo;
use Waves\Model\BlockHeaders;
use Waves\Model\Balance;
use Waves\Model\AssetBalance;
use Waves\Model\AssetDetails;
use Waves\Model\AssetDistribution;
use Waves\Model\BlockchainRewards;
use Waves\Model\Block;
use Waves\Model\HistoryBalance;
use Waves\Model\ScriptDetails;
use Waves\Model\ScriptMeta;
use Waves\Model\LeaseInfo;
use Waves\Model\TransactionInfo;
use Waves\Model\TransactionWithStatus;
use Waves\Model\TransactionStatus;
use Waves\Model\Validation;
use Waves\Model\Votes;

class Json
{
    /**
     * @var array<mixed, mixed>
     */
    private array $data;

    /**
    * Json constructor
    *
    * @param array<mixed, mixed> $data
    */
    private function __construct( array $data = [] )
    {
        $this->data = $data;
    }

    /**
    * Json function constructor
    *
    * @param array<mixed, mixed> $data
    * @return Json
    */
    static function as( array $data ): Json
    {
        return new Json( $data );
    }

    static function emptyJson(): Json
    {
        return new Json;
    }

    /**
     * Gets native underlying data array
     *
     * @return array<mixed, mixed>
     */
    function data(): array
    {
        return $this->data;
    }

    function toString(): string
    {
        $string = json_encode( $this->data );
        if( $string === false )
            throw new Exception( __FUNCTION__ . ' failed to encode internal array `' . serialize( $this->data) . '`', ExceptionCode::JSON_ENCODE );
        return $string;
    }

    /**
     * Gets Value by key
     *
     * @param mixed $key
     * @return Value
     */
    function get( $key ): Value
    {
        if( !isset( $this->data[$key] ) )
            throw new Exception( __FUNCTION__ . ' failed to find key `' . $key . '`', ExceptionCode::KEY_MISSING );
        return Value::as( $this->data[$key] );
    }

    /**
     * Gets Value by key or returns fallback value
     *
     * @param mixed $key
     * @param mixed $value
     * @return Value
     */
    function getOr( $key, $value ): Value
    {
        return $this->exists( $key ) ? $this->get( $key ) : Value::as( $value );
    }

    /**
     * Checks key exists
     *
     * @param mixed $key
     * @return bool
     */
    function exists( $key ): bool
    {
        return isset( $this->data[$key] );
    }

    /**
     * Puts value by key
     *
     * @param mixed $key
     * @param mixed $value
     * @return Json
     */
    function put( $key, $value ): Json
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
    * Gets a BlockHeaders value
    *
    * @return BlockHeaders
    */
    function asBlockHeaders(): BlockHeaders
    {
        return new BlockHeaders( $this );
    }

    /**
    * Gets a Balance value
    *
    * @return Balance
    */
    function asBalance(): Balance
    {
        return new Balance( $this );
    }

    function asHistoryBalance(): HistoryBalance
    {
        return new HistoryBalance( $this );
    }

    /**
    * Gets a AssetBalance value
    *
    * @return AssetBalance
    */
    function asAssetBalance(): AssetBalance
    {
        return new AssetBalance( $this );
    }

    function asAssetDetails(): AssetDetails
    {
        return new AssetDetails( $this );
    }

    function asAssetDistribution(): AssetDistribution
    {
        return new AssetDistribution( $this );
    }

    /**
    * Gets a BalanceDetails value
    *
    * @return BalanceDetails
    */
    function asBalanceDetails(): BalanceDetails
    {
        return new BalanceDetails( $this );
    }

    function asBlockchainRewards(): BlockchainRewards
    {
        return new BlockchainRewards( $this );
    }

    function asBlock(): Block
    {
        return new Block( $this );
    }

    /**
    * Gets a DataEntry value
    *
    * @return DataEntry
    */
    function asDataEntry(): DataEntry
    {
        return new DataEntry( $this );
    }

    function asLeaseInfo(): LeaseInfo
    {
        return new LeaseInfo( $this );
    }

    function asScriptMeta(): ScriptMeta
    {
        return new ScriptMeta( $this );
    }

    function asScriptInfo(): ScriptInfo
    {
        return new ScriptInfo( $this );
    }

    function asScriptDetails(): ScriptDetails
    {
        return new ScriptDetails( $this );
    }

    function asTransactionInfo(): TransactionInfo
    {
        return new TransactionInfo( $this );
    }

    function asTransactionWithStatus(): TransactionWithStatus
    {
        return new TransactionWithStatus( $this );
    }

    function asTransactionStatus(): TransactionStatus
    {
        return new TransactionStatus( $this );
    }

    function asTransaction(): Transaction
    {
        return new Transaction( $this );
    }

    function asValidation(): Validation
    {
        return new Validation( $this );
    }

    function asVotes(): Votes
    {
        return new Votes( $this );
    }

    /**
    * Gets an array of BlockHeaders value
    *
    * @return array<int, BlockHeaders>
    */
    function asArrayBlockHeaders(): array
    {
        $array = [];
        foreach( $this->data as $headers )
            $array[] = Value::as( $headers )->asJson()->asBlockHeaders();
        return $array;
    }

    /**
    * Gets an array of Block value
    *
    * @return array<int, Block>
    */
    function asArrayBlock(): array
    {
        $array = [];
        foreach( $this->data as $headers )
            $array[] = Value::as( $headers )->asJson()->asBlock();
        return $array;
    }

    /**
    * Gets an array of LeaseInfo value
    *
    * @return array<int, LeaseInfo>
    */
    function asArrayLeaseInfo(): array
    {
        $array = [];
        foreach( $this->data as $info )
            $array[] = Value::as( $info )->asJson()->asLeaseInfo();
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, Address>
    */
    function asArrayAddress(): array
    {
        $array = [];
        foreach( $this->data as $address )
            $array[] = Address::fromString( Value::as( $address )->asString() );
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, Alias>
    */
    function asArrayAlias(): array
    {
        $array = [];
        foreach( $this->data as $alias )
            $array[] = Alias::fromFullAlias( Value::as( $alias )->asString() );
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, Balance>
    */
    function asArrayBalance(): array
    {
        $array = [];
        foreach( $this->data as $balance )
            $array[] = Value::as( $balance )->asJson()->asBalance();
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, HistoryBalance>
    */
    function asArrayHistoryBalance(): array
    {
        $array = [];
        foreach( $this->data as $balance )
            $array[] = Value::as( $balance )->asJson()->asHistoryBalance();
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, AssetBalance>
    */
    function asArrayAssetBalance(): array
    {
        $array = [];
        foreach( $this->data as $assetBalance )
            $array[] = Value::as( $assetBalance )->asJson()->asAssetBalance();
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, AssetDetails>
    */
    function asArrayAssetDetails(): array
    {
        $array = [];
        foreach( $this->data as $assetDetails )
            $array[] = Value::as( $assetDetails )->asJson()->asAssetDetails();
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, DataEntry>
    */
    function asArrayDataEntry(): array
    {
        $array = [];
        foreach( $this->data as $data )
            $array[] = Value::as( $data )->asJson()->asDataEntry();
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, TransactionWithStatus>
    */
    function asArrayTransactionWithStatus(): array
    {
        $array = [];
        foreach( $this->data as $tx )
            $array[] = Value::as( $tx )->asJson()->asTransactionWithStatus();
        return $array;
    }

    /**
    * Gets an array value
    *
    * @return array<int, TransactionInfo>
    */
    function asArrayTransactionInfo(): array
    {
        $array = [];
        foreach( $this->data as $tx )
            $array[] = Value::as( $tx )->asJson()->asTransactionInfo();
        return $array;
    }

    /**
    * @return array<int, TransactionStatus>
    */
    function asArrayTransactionStatus(): array
    {
        $array = [];
        foreach( $this->data as $tx )
            $array[] = Value::as( $tx )->asJson()->asTransactionStatus();
        return $array;
    }

    /**
    * @return array<int, Transaction>
    */
    function asArrayTransaction(): array
    {
        $array = [];
        foreach( $this->data as $tx )
            $array[] = Value::as( $tx )->asJson()->asTransaction();
        return $array;
    }
}
