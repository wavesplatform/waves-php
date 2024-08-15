<?php declare( strict_types = 1 );

namespace Waves\API;

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Common\Json;
use Waves\Account\Address;
use Waves\Model\AssetId;
use Waves\Model\AssetDistribution;
use Waves\Model\AssetBalance;
use Waves\Model\AssetDetails;
use Waves\Model\Alias;
use Waves\Model\Balance;
use Waves\Model\BalanceDetails;
use Waves\Model\Block;
use Waves\Model\Id;
use Waves\Model\LeaseInfo;
use Waves\Model\BlockHeaders;
use Waves\Model\BlockchainRewards;
use Waves\Model\ChainId;
use Waves\Model\DataEntry;
use Waves\Model\HistoryBalance;
use Waves\Model\ScriptInfo;
use Waves\Model\ScriptMeta;
use Waves\Model\Status;
use Waves\Model\TransactionInfo;
use Waves\Model\TransactionStatus;
use Waves\Model\Validation;
use Waves\Transactions\Amount;
use Waves\Transactions\Transaction;

class Node
{
    const MAINNET = "https://nodes.wavesnodes.com";
    const TESTNET = "https://nodes-testnet.wavesnodes.com";
    const STAGENET = "https://nodes-stagenet.wavesnodes.com";
    const LOCAL = "http://127.0.0.1:6869";

    private \deemru\WavesKit $wk;
    private ChainId $chainId;
    private string $uri;

    private string $wklevel = '';
    private string $wkmessage = '';

    /**
     * Creates Node instance
     *
     * @param string $uri Node REST API address
     * @param ChainId $chainId Chain ID or "?" to set automatically (Default: "")
     */
    function __construct( string $uri, ChainId $chainId = null )
    {
        $this->uri = $uri;
        $this->wk = new \deemru\WavesKit( isset( $chainId ) ? $chainId->asString() : '?', function( string $wklevel, string $wkmessage )
        {
            $this->wklevel = $wklevel;
            $this->wkmessage = $wkmessage;
        } );
        $this->wk->setNodeAddress( $uri, 0 );

        if( !isset( $chainId ) )
        {
            if( $uri === Node::MAINNET )
                $this->chainId = ChainId::MAINNET();
            else
            if( $uri === Node::TESTNET )
                $this->chainId = ChainId::TESTNET();
            else
            if( $uri === Node::STAGENET )
                $this->chainId = ChainId::STAGENET();
            else
                $this->chainId = $this->getAddresses()[0]->chainId();
        }
        else
        {
            $this->chainId = $chainId;
        }

        if( $this->wk->getChainId() !== $this->chainId->asString() )
        {
            $this->wk = new \deemru\WavesKit( $this->chainId->asString(), function( string $wklevel, string $wkmessage )
            {
                $this->wklevel = $wklevel;
                $this->wkmessage = $wkmessage;
            } );
            $this->wk->setNodeAddress( $uri, 0 );
        }
    }

    static function MAINNET(): Node
    {
        return new Node( Node::MAINNET );
    }

    static function TESTNET(): Node
    {
        return new Node( Node::TESTNET );
    }

    static function STAGENET(): Node
    {
        return new Node( Node::STAGENET );
    }

    static function LOCAL(): Node
    {
        return new Node( Node::LOCAL );
    }

    function chainId(): ChainId
    {
        return $this->chainId;
    }

    function uri(): string
    {
        return $this->uri;
    }

    /**
     * Fetches a custom REST API request
     *
     * @param string $uri
     * @param Json|string|null $data
     * @return Json
     */
    private function fetch( string $uri, $data = null )
    {
        if( isset( $data ) )
        {
            if( is_string( $data ) )
                $fetch = $this->wk->fetch( $uri, true, $data, null, [ 'Content-Type: text/plain', 'Accept: application/json' ] );
            else
                $fetch = $this->wk->fetch( $uri, true, $data->toString() );
        }
        else
            $fetch = $this->wk->fetch( $uri );
        if( $fetch === false )
        {
            $message = __FUNCTION__ . ' failed at `' . $uri . '`';
            if( $this->wklevel === 'e' )
                $message .= ' (' . $this->wkmessage . ')';
            throw new Exception( $message, ExceptionCode::FETCH_URI );
        }
        $fetch = $this->wk->json_decode( $fetch );
        if( $fetch === false )
            throw new Exception( __FUNCTION__ . ' failed to decode `' . $uri . '`', ExceptionCode::JSON_DECODE );
        return Json::as( $fetch );
    }

    /**
     * GETs a custom REST API request
     *
     * @param string $uri
     * @return Json
     */
    function get( string $uri ): Json
    {
        return $this->fetch( $uri );
    }

    /**
     * POSTs a custom REST API request
     *
     * @param string $uri
     * @param Json|string $data
     * @return Json
     */
    function post( string $uri, $data ): Json
    {
        return $this->fetch( $uri, $data );
    }

    //===============
    // ADDRESSES
    //===============

    /**
     * Return addresses of the node
     *
     * @return array<int, Address>
     */
    function getAddresses(): array
    {
        return $this->get( '/addresses' )->asArrayAddress();
    }

    /**
     * Return addresses of the node by indexes
     *
     * @return array<int, Address>
     */
    function getAddressesByIndexes( int $fromIndex, int $toIndex ): array
    {
        return $this->get( '/addresses/seq/' . $fromIndex . '/' . $toIndex )->asArrayAddress();
    }

    function getBalance( Address $address, int $confirmations = null ): int
    {
        $uri = '/addresses/balance/' . $address->toString();
        if( isset( $confirmations ) )
            $uri .= '/' . $confirmations;
        return $this->get( $uri )->get( 'balance' )->asInt();
    }

    /**
     * Gets addresses balances
     *
     * @param array<int, Address> $addresses
     * @param int|null $height (default: null)
     * @return array<int, Balance>
     */
    function getBalances( array $addresses, int $height = null ): array
    {
        $json = Json::emptyJson();

        $array = [];
        foreach( $addresses as $address )
            $array[] = $address->toString();
        $json->put( 'addresses', $array );

        if( isset( $height ) )
            $json->put( 'height', $height );

        return $this->post( '/addresses/balance', $json )->asArrayBalance();
    }

    function getBalanceDetails( Address $address ): BalanceDetails
    {
        return $this->get( '/addresses/balance/details/' . $address->toString() )->asBalanceDetails();
    }

    /**
     * Gets DataEntry array of address
     *
     * @param Address $address
     * @param string|null $regex (default: null)
     * @return array<int, DataEntry>
     */
    function getData( Address $address, string $regex = null ): array
    {
        $uri = '/addresses/data/' . $address->toString();
        if( isset( $regex ) )
            $uri .= '?matches=' . urlencode( $regex );
        return $this->get( $uri )->asArrayDataEntry();
    }

    /**
     * Gets DataEntry array of address by keys
     *
     * @param Address $address
     * @param array<int, string> $keys
     * @return array<int, DataEntry>
     */
    function getDataByKeys( Address $address, array $keys ): array
    {
        $json = Json::emptyJson();

        $array = [];
        foreach( $keys as $key )
            $array[] = $key;
        $json->put( 'keys', $array );

        return $this->post( '/addresses/data/' . $address->toString(), $json )->asArrayDataEntry();
    }

    /**
     * Gets a single DataEntry of address by a key
     *
     * @param Address $address
     * @param string $key
     * @return DataEntry
     */
    function getDataByKey( Address $address, string $key ): DataEntry
    {
        return $this->get( '/addresses/data/' . $address->toString() . '/' . $key )->asDataEntry();
    }

    function getScriptInfo( Address $address ): ScriptInfo
    {
        return $this->get( '/addresses/scriptInfo/' . $address->toString() )->asScriptInfo();
    }

    function getScriptMeta( Address $address ): ScriptMeta
    {
        $json = $this->get( '/addresses/scriptInfo/' . $address->toString() . '/meta' );
        if( !$json->exists( 'meta' ) )
            $json->put( 'meta', [ 'version' => 0, 'callableFuncTypes' => [] ] );
        return $json->get( 'meta' )->asJson()->asScriptMeta();
    }

    //===============
    // ALIAS
    //===============

    /**
     * Gets an array of aliases by address
     *
     * @param Address $address
     * @return array<int, Alias>
     */
    function getAliasesByAddress( Address $address ): array
    {
        return $this->get( '/alias/by-address/' . $address->toString() )->asArrayAlias();
    }

    function getAddressByAlias( Alias $alias ): Address
    {
        return $this->get( '/alias/by-alias/' . $alias->name() )->get( 'address' )->asAddress();
    }

    //===============
    // ASSETS
    //===============

    function getAssetDistribution( AssetId $assetId, int $height, int $limit = 1000, string $after = null ): AssetDistribution
    {
        $uri = '/assets/' . $assetId->toString() . '/distribution/' . $height . '/limit/' . $limit;
        if( isset( $after ) )
            $uri .= '?after=' . $after;
        return $this->get( $uri )->asAssetDistribution();
    }

    /**
     * Gets an array of AssetBalance for an address
     *
     * @param Address $address
     * @return array<int, AssetBalance>
     */
    function getAssetsBalance( Address $address ): array
    {
        return $this->get( '/assets/balance/' . $address->toString() )->get( 'balances' )->asJson()->asArrayAssetBalance();
    }

    function getAssetBalance( Address $address, AssetId $assetId ): int
    {
        return $assetId->isWaves() ?
            $this->getBalance( $address ) :
            $this->get( '/assets/balance/' . $address->toString() . '/' . $assetId->toString() )->get( 'balance' )->asInt();
    }

    function getAssetDetails( AssetId $assetId ): AssetDetails
    {
        return $this->get( '/assets/details/' . $assetId->toString() . '?full=true' )->asAssetDetails();
    }

    /**
     * @param array<int, AssetId> $assetIds
     * @return array<int, AssetDetails>
     */
    function getAssetsDetails( array $assetIds ): array
    {
        $json = Json::emptyJson();

        $array = [];
        foreach( $assetIds as $assetId )
            $array[] = $assetId->toString();
        $json->put( 'ids', $array );

        return $this->post( '/assets/details?full=true', $json )->asArrayAssetDetails();
    }

    /**
     * @return array<int, AssetDetails>
     */
    function getNft( Address $address, int $limit = 1000, AssetId $after = null ): array
    {
        $uri = '/assets/nft/' . $address->toString() . '/limit/' . $limit;
        if( isset( $after ) )
            $uri .= '?after=' . $after->toString();
        return $this->get( $uri )->asArrayAssetDetails();
    }

    //===============
    // BLOCKCHAIN
    //===============

    function getBlockchainRewards( int $height = null ): BlockchainRewards
    {
        $uri = '/blockchain/rewards';
        if( isset( $height ) )
            $uri .= '/' . $height;
        return $this->get( $uri )->asBlockchainRewards();
    }

    //===============
    // BLOCKS
    //===============

    function getHeight(): int
    {
        return $this->get( '/blocks/height' )->get( 'height' )->asInt();
    }

    function getBlockHeightById( string $blockId ): int
    {
        return $this->get( '/blocks/height/' . $blockId )->get( 'height' )->asInt();
    }

    function getBlockHeightByTimestamp( int $timestamp ): int
    {
        return $this->get( "/blocks/heightByTimestamp/" . $timestamp )->get( "height" )->asInt();
    }

    function getBlocksDelay( string $startBlockId, int $blocksNum ): int
    {
        return $this->get( "/blocks/delay/" . $startBlockId . "/" . $blocksNum )->get( "delay" )->asInt();
    }

    function getBlockHeadersByHeight( int $height ): BlockHeaders
    {
        return $this->get( "/blocks/headers/at/" . $height )->asBlockHeaders();
    }

    function getBlockHeadersById( string $blockId ): BlockHeaders
    {
        return $this->get( "/blocks/headers/" . $blockId )->asBlockHeaders();
    }

    /**
     * Get an array of BlockHeaders from fromHeight to toHeight
     *
     * @param integer $fromHeight
     * @param integer $toHeight
     * @return array<int, BlockHeaders>
     */
    function getBlocksHeaders( int $fromHeight, int $toHeight ): array
    {
        return $this->get( "/blocks/headers/seq/" . $fromHeight . "/" . $toHeight )->asArrayBlockHeaders();
    }

    function getLastBlockHeaders(): BlockHeaders
    {
        return $this->get( "/blocks/headers/last" )->asBlockHeaders();
    }

    function getBlockByHeight( int $height ): Block
    {
        return $this->get( '/blocks/at/' . $height )->asBlock();
    }

    function getBlockById( Id $id ): Block
    {
        return $this->get( '/blocks/' . $id->toString() )->asBlock();
    }

    /**
     * @return array<int, Block>
     */
    function getBlocks( int $fromHeight, int $toHeight ): array
    {
        return $this->get( '/blocks/seq/' . $fromHeight . '/' . $toHeight )->asArrayBlock();
    }

    function getGenesisBlock(): Block
    {
        return $this->get( '/blocks/at/1' )->asBlock();
    }

    function getLastBlock(): Block
    {
        return $this->get( '/blocks/last' )->asBlock();
    }

    /**
     * @return array<int, Block>
     */
    function getBlocksGeneratedBy( Address $generator, int $fromHeight, int $toHeight ): array
    {
        return $this->get( '/blocks/address/' . $generator->toString() . '/' . $fromHeight . '/' . $toHeight )->asArrayBlock();
    }

    //===============
    // NODE
    //===============

    function getVersion(): string
    {
        return $this->get( '/node/version')->get( 'version' )->asString();
    }

    //===============
    // DEBUG
    //===============

    /**
     * @param Address $address
     * @return array<int, HistoryBalance>
     */
    function getBalanceHistory( Address $address ): array
    {
        return $this->get( '/debug/balances/history/' . $address->toString() )->asArrayHistoryBalance();
    }

    function validateTransaction( Transaction $transaction ): Validation
    {
        return $this->post( '/debug/validate', $transaction->json() )->asValidation();
    }

    //===============
    // LEASING
    //===============

    /**
     * @return array<int, LeaseInfo>
     */
    function getActiveLeases( Address $address ): array
    {
        return $this->get( '/leasing/active/' . $address->toString() )->asArrayLeaseInfo();
    }

    function getLeaseInfo( Id $leaseId ): LeaseInfo
    {
        return $this->get( '/leasing/info/' . $leaseId->toString() )->asLeaseInfo();
    }

    /**
     * @param array<int, Id> $leaseIds
     * @return array<int, LeaseInfo>
     */
    function getLeasesInfo( array $leaseIds ): array
    {
        $json = Json::emptyJson();

        $array = [];
        foreach( $leaseIds as $leaseId )
            $array[] = $leaseId->toString();
        $json->put( 'ids', $array );

        return $this->post( '/leasing/info', $json )->asArrayLeaseInfo();
    }

    //===============
    // TRANSACTIONS
    //===============

    function calculateTransactionFee( Transaction $transaction ): Amount
    {
        $json = $this->post( '/transactions/calculateFee', $transaction->json() );
        return Amount::fromJson( $json, 'feeAmount', 'feeAssetId' );
    }

    function serializeTransaction( Transaction $transaction ): string
    {
        $json = $this->post( '/utils/transactionSerialize', $transaction->json() );
        $bytes = '';
        foreach( $json->get( 'bytes' )->asArrayInt() as $byte )
            $bytes .= chr( $byte );
        return $bytes;
    }

    function broadcast( Transaction $transaction ): Transaction
    {
        return $this->post( '/transactions/broadcast', $transaction->json() )->asTransaction();
    }

    function getTransactionInfo( Id $txId ): TransactionInfo
    {
        return $this->get( '/transactions/info/' . $txId->toString() )->asTransactionInfo();
    }

    /**
     * @return array<int, TransactionInfo>
     */
    function getTransactionsByAddress( Address $address, int $limit = 100, Id $afterTxId = null ): array
    {
        $uri = '/transactions/address/' . $address->toString() . '/limit/' . $limit;
        if( isset( $afterTxId ) )
            $uri .= '?after=' . $afterTxId->toString();
        return $this->get( $uri )->get( 0 )->asJson()->asArrayTransactionInfo();
    }

    function getTransactionStatus( Id $txId ): TransactionStatus
    {
        return $this->get( '/transactions/status?id=' . $txId->toString() )->get( 0 )->asJson()->asTransactionStatus();
    }

    /**
     * @param array<int, Id> $txIds
     * @return array<int, TransactionStatus>
     */
    function getTransactionsStatus( array $txIds ): array
    {
        $json = Json::emptyJson();

        $array = [];
        foreach( $txIds as $txId )
            $array[] = $txId->toString();
        $json->put( 'ids', $array );

        return $this->post( '/transactions/status', $json )->asArrayTransactionStatus();
    }

    function getUnconfirmedTransaction( Id $txId ): Transaction
    {
        return $this->get( '/transactions/unconfirmed/info/' . $txId->toString() )->asTransaction();
    }

    /**
     * @return array<int, Transaction>
     */
    function getUnconfirmedTransactions(): array
    {
        return $this->get( '/transactions/unconfirmed' )->asArrayTransaction();
    }

    function getUtxSize(): int
    {
        return $this->get( '/transactions/unconfirmed/size' )->get( 'size' )->asInt();
    }

    //===============
    // UTILS
    //===============

    function compileScript( string $source, bool $enableCompaction = null ): ScriptInfo
    {
        $uri = '/utils/script/compileCode';
        if( isset( $enableCompaction ) )
            $uri .= '?compact=' . ( $enableCompaction ? 'true' : 'false' );
        return $this->post( $uri, $source )->asScriptInfo();
    }

    function ethToWavesAsset( string $asset ): string
    {
        return $this->get( '/eth/assets?id=' . $asset )->get( 0 )->asJson()->asAssetDetails()->assetId()->encoded();
    }

    //===============
    // WAITINGS
    //===============

    const blockIntervalInSeconds = 60;
    const pollingIntervalInMillis = 1000;

    function waitForTransaction( Id $id, int $waitingInSeconds = Node::blockIntervalInSeconds, int $pollingIntervalInMillis = Node::pollingIntervalInMillis ): TransactionInfo
    {
        if( $waitingInSeconds < 1 )
            $waitingInSeconds = 1;

        if( $pollingIntervalInMillis < 1 )
            $pollingIntervalInMillis = 1;

        $pollingIntervalInMicros = $pollingIntervalInMillis * 1000;
        $waitingInMillis = $waitingInSeconds * 1000;

        for( $spentMillis = 0; $spentMillis < $waitingInMillis; $spentMillis += $pollingIntervalInMillis )
        {
            try
            {
                return $this->getTransactionInfo( $id );
            }
            catch( Exception $e )
            {
                if( $e->getCode() !== ExceptionCode::FETCH_URI )
                    throw new Exception( __FUNCTION__ . ' unexpected exception `' . $e->getCode() . '`:`' . $e->getMessage() . '`', ExceptionCode::UNEXPECTED );

                usleep( $pollingIntervalInMicros );
            }
        }

        throw new Exception( __FUNCTION__ . ' could not wait for transaction `' . $id->toString() . '` in ' . $waitingInSeconds . ' seconds', ExceptionCode::TIMEOUT );
    }

    /**
     * @param array<int, Id> $ids
     * @param int $waitingInSeconds
     * @return void
     */
    function waitForTransactions( array $ids, int $waitingInSeconds = Node::blockIntervalInSeconds, int $pollingIntervalInMillis = Node::pollingIntervalInMillis ): void
    {
        if( $waitingInSeconds < 1 )
            $waitingInSeconds = 1;

        if( $pollingIntervalInMillis < 1 )
            $pollingIntervalInMillis = 1;

        $pollingIntervalInMicros = $pollingIntervalInMillis * 1000;
        $waitingInMillis = $waitingInSeconds * 1000;

        for( $spentMillis = 0; $spentMillis < $waitingInMillis; $spentMillis += $pollingIntervalInMillis )
        {
            try
            {
                $isOK = true;
                $statuses = $this->getTransactionsStatus( $ids );
                foreach( $statuses as $status )
                    if( $status->status() !== Status::CONFIRMED )
                    {
                        $isOK = false;
                        break;
                    }

                if( $isOK )
                    return;

                usleep( $pollingIntervalInMicros );
            }
            catch( Exception $e )
            {
                if( $e->getCode() !== ExceptionCode::FETCH_URI )
                    throw new Exception( __FUNCTION__ . ' unexpected exception `' . $e->getCode() . '`:`' . $e->getMessage() . '`', ExceptionCode::UNEXPECTED );

                usleep( $pollingIntervalInMicros );
            }
        }

        throw new Exception( __FUNCTION__ . ' could not wait for transactions', ExceptionCode::TIMEOUT );
    }

    function waitForHeight( int $target, int $waitingInSeconds = Node::blockIntervalInSeconds * 3, int $pollingIntervalInMillis = Node::pollingIntervalInMillis ): int
    {
        $start = $this->getHeight();
        $prev = $start;

        if( $waitingInSeconds < 1 )
            $waitingInSeconds = 1;

        if( $pollingIntervalInMillis < 1 )
            $pollingIntervalInMillis = 1;

        $pollingIntervalInMicros = $pollingIntervalInMillis * 1000;
        $waitingInMillis = $waitingInSeconds * 1000;

        $current = $start;
        for( $spentMillis = 0; $spentMillis < $waitingInMillis; $spentMillis += $pollingIntervalInMillis )
        {
            if( $current >= $target )
                return $current;
            else if( $current > $prev )
            {
                $prev = $current;
                $spentMillis = 0;
            }

            usleep( $pollingIntervalInMicros );
            $current = $this->getHeight();
        }

        throw new Exception( __FUNCTION__ . ' could not wait for height `' . $target . '` in ' . $waitingInSeconds . ' seconds', ExceptionCode::TIMEOUT );
    }

    function waitBlocks( int $blocksCount, int $waitingInSeconds = Node::blockIntervalInSeconds * 3, int $pollingIntervalInMillis = Node::pollingIntervalInMillis ): int
    {
        return $this->waitForHeight( $this->getHeight() + $blocksCount, $waitingInSeconds, $pollingIntervalInMillis );
    }
}