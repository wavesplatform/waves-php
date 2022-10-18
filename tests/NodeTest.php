<?php

namespace Waves;

require_once 'common.php';

use Exception;
use Waves\Common\ExceptionCode;
use Waves\Account\Address;
use Waves\API\Node;
use Waves\Model\AssetId;
use Waves\Model\ChainId;
use Waves\Model\LeaseStatus;
use Waves\Model\Id;
use Waves\Transactions\Recipient;
use Waves\Util\Functions;

class NodeTest extends \PHPUnit\Framework\TestCase
{
    private function catchExceptionOrFail( int $code, callable $block ): void
    {
        try
        {
            $block();
            $this->fail( 'Failed to catch exception with code:' . $code );
        }
        catch( Exception $e )
        {
            $this->assertEquals( $code, $e->getCode(), $e->getMessage() );
        }
    }

    function testNode(): void
    {
        $nodeW = Node::MAINNET();
        $nodeT = Node::TESTNET();
        $nodeS = Node::STAGENET();

        $version = $nodeS->getVersion();
        $nodeS->waitBlocks( 0 );

        $ethAsset = $nodeS->ethToWavesAsset( '0x7a087b3384447a48393eda243e630b07db443597' );
        $this->assertEquals( '9DNEvLFSSnSSaNCb5WEYMz64hsadDjx1THZw3z2hiyJe', $ethAsset );

        $someScript = file_get_contents( 'https://raw.githubusercontent.com/waves-exchange/neutrino-defo-contract/df334ea97952692983d1038a4818626ee01bfea6/factory.ride' );
        if( $someScript === false )
        {
            $this->assertNotEquals( $someScript, false );
            return;
        }

        $scriptInfo = $nodeW->compileScript( $someScript );
        $script1 = $scriptInfo->script();
        $scriptInfo = $nodeW->compileScript( $someScript, true );
        $script2 = $scriptInfo->script();
        $this->assertNotEquals( $script1, $script2 );
        $this->assertLessThan( strlen( $script1->bytes() ), strlen( $script2->bytes() ) );

        $address = Address::fromString( '3P5dg6PtSAQmdH1qCGKJWu7bkzRG27mny5i' );
        $historyBalances = $nodeW->getBalanceHistory( $address );
        foreach( $historyBalances as $historyBalance )
        {
            $historyBalance->height();
            $historyBalance->balance();
        }

        $txs = $nodeW->getTransactionsByAddress( $address, 2 );
        foreach( $txs as $tx )
        {
            $status = $nodeW->getTransactionStatus( $tx->id() );
            $status->status();
            $status->confirmations();
            $this->assertSame( $status->id()->toString(), $tx->id()->toString() );
            $this->assertSame( $status->applicationStatus(), $tx->applicationStatus() );
            $this->assertSame( $status->height(), $tx->height() );
        }

        if( isset( $txs[1] ) )
        {
            $tx = $txs[1];
            $txs2 = $nodeW->getTransactionsByAddress( $address, 2, $tx->id() );
            foreach( $txs2 as $tx2 )
                $this->assertNotEquals( $tx->id()->toString(), $tx2->id()->toString() );

            $statuses = $nodeW->getTransactionsStatus( [ $tx->id() ] );
            foreach( $statuses as $status )
                $this->assertSame( $status->id()->toString(), $tx->id()->toString() );
        }

        $doUtx = 0;
        for( ; $doUtx; )
        {
            $utxSize = $nodeW->getUtxSize();
            if( $utxSize > 0 )
            {
                $txs = $nodeW->getUnconfirmedTransactions();
                if( isset( $txs[0] ) )
                {
                    $tx = $txs[0];
                    try
                    {
                        $txUnconfirmed = $nodeW->getUnconfirmedTransaction( $tx->id() );
                        $this->assertSame( $tx->id()->toString(), $txUnconfirmed->id()->toString() );
                    }
                    catch( Exception $e )
                    {
                        $this->assertEquals( ExceptionCode::FETCH_URI, $e->getCode(), $e->getMessage() );
                    }
                }

                break;
            }

            sleep( 1 );
        }


        $leases = $nodeW->getActiveLeases( $address );
        foreach( $leases as $lease )
        {
            $lease->amount();
            $lease->height();
            $lease->id();
            $lease->originTransactionId();
            $lease->recipient();
            $lease->sender();
            if( $lease->status() == LeaseStatus::CANCELED )
            {
                $lease->cancelHeight();
                $lease->cancelTransactionId();
            }
        }

        if( isset( $lease ) )
        {
            $this->assertSame( $lease->toString(), $nodeW->getLeaseInfo( $lease->id() )->toString() );
            $this->assertSame( $lease->toString(), $nodeW->getLeasesInfo( [ $lease->id() ] )[0]->toString() );
        }

        $leaseId = Id::fromString( '45uZvPeDva4CyXXTsTkh7fhzqTJCe2eqnz1HFt4aYNdZ' );
        $lease = $nodeW->getLeaseInfo( $leaseId );
        if( $lease->status() == LeaseStatus::CANCELED )
        {
            $lease->cancelHeight();
            $lease->cancelTransactionId();
            $transactionInfo = $nodeW->getTransactionInfo( $lease->cancelTransactionId() );
            $this->assertSame( $lease->cancelHeight(), $transactionInfo->height() );
        }

        $heightT = $nodeT->getHeight();
        $heightW = $nodeW->getHeight();

        $block = $nodeW->getGenesisBlock();
        $block = $nodeW->getLastBlock();
        $blocks = $nodeW->getBlocksGeneratedBy( $block->generator(), $heightW - 10, $heightW );

        $blocks = $nodeW->getBlocks( $heightW - 4, $heightW );
        $this->assertSame( 5, count( $blocks ) );
        foreach( $blocks as $block )
            foreach( $block->transactions() as $tx )
            {
                $tx->applicationStatus();
                $tx->chainId();
                $tx->fee();
                $tx->id();
                $tx->proofs();
                $tx->sender();
                $tx->timestamp();
                $tx->type();
                $tx->version();
            }

        $block1 = $nodeT->getBlockByHeight( 2126170 );
        $block2 = $nodeT->getBlockById( $block1->id() );
        $this->assertSame( $block1->toString(), $block2->toString() );
        $block1->fee();
        $txs = $block1->transactions();
        foreach( $txs as $tx )
        {
            $tx->applicationStatus();
            $tx->chainId();
            $tx->fee();
            $tx->id();
            $tx->proofs();
            $tx->sender();
            $tx->timestamp();
            $tx->type();
            $tx->version();

            if( !isset( $validation ) )
            {
                $validation = $nodeT->validateTransaction( $tx );
                $validation->isValid();
                $validation->validationTime();
                $validation->error();
            }
        }

        $blockchainRewards1 = $nodeT->getBlockchainRewards();
        $blockchainRewards2 = $nodeT->getBlockchainRewards( 1600000 );
        $this->assertNotEquals( $blockchainRewards1->toString(), $blockchainRewards2->toString() );
        $blockchainRewards1->currentReward();
        $blockchainRewards1->height();
        $blockchainRewards1->minIncrement();
        $blockchainRewards1->nextCheck();
        $blockchainRewards1->term();
        $blockchainRewards1->totalWavesAmount();
        $blockchainRewards1->votes()->increase();
        $blockchainRewards1->votes()->decrease();
        $blockchainRewards1->votingInterval();
        $blockchainRewards1->votingIntervalStart();
        $blockchainRewards1->votingThreshold();

        $addressT = Address::fromString( '3N4q2D5bh5sAL3b4PighYyKw2WshKCiFD4F' );
        $nfts1 = $nodeT->getNft( $addressT, 10 );
        $nfts2 = $nodeT->getNft( $addressT, 10, $nfts1[0]->assetId() );
        $this->assertSame( $nfts1[1]->toString(), $nfts2[0]->toString() );

        $addressT = Address::fromString( '3N9WtaPoD1tMrDZRG26wA142Byd35tLhnLU' );
        $assetId = AssetId::WAVES();

        $assetIds = [];
        $balances = $nodeT->getAssetsBalance( $addressT );
        $max = 10;
        foreach( $balances as $balance )
        {
            $assetId = $balance->assetId();
            $assetIds[] = $assetId;
            $addressBalance = $nodeT->getAssetBalance( $addressT, $assetId );
            $this->assertSame( $addressBalance, $balance->balance() );
            $balance->isReissuable();
            $balance->quantity();
            $balance->minSponsoredAssetFee();
            $balance->sponsorBalance();
            $balance->issueTransaction();

            $distribution = $nodeT->getAssetDistribution( $assetId, $nodeT->getHeight() - 10, 10 );
            if( $distribution->hasNext() )
                $distribution = $nodeT->getAssetDistribution( $assetId, $nodeT->getHeight() - 10, 10, $distribution->lastItem() );

            $details = $nodeT->getAssetDetails( $assetId );
            $this->assertSame( $assetId->bytes(), $details->assetId()->bytes() );
            $details->decimals();
            $details->description();
            $details->isReissuable();
            $details->isScripted();
            $details->issueHeight();
            $details->issuer();
            $details->issuerPublicKey();
            $details->issueTimestamp();
            $details->minSponsoredAssetFee();
            $details->name();
            $details->originTransactionId();
            $details->quantity();
            $scriptDetails = $details->scriptDetails();
            $scriptDetails->complexity();
            $scriptDetails->script();
            if( --$max === 0 )
                break;
        }

        if( isset( $details ) )
        {
            $assetsDetails = $nodeT->getAssetsDetails( $assetIds );
            foreach( $assetsDetails as $assetDetails )
                if( $assetDetails->assetId()->toString() === $details->assetId()->toString() )
                    $this->assertSame( $assetDetails->toString(), $details->toString() );
        }

        $aliases = $nodeT->getAliasesByAddress( $addressT );
        foreach( $aliases as $alias )
        {
            $alias->name();
            $alias->toString();
            $this->assertSame( $addressT->encoded(), $nodeT->getAddressByAlias( $alias )->encoded() );
        }

        $addressT = Address::fromString( '3N7uoMNjqNt1jf9q9f9BSr7ASk1QtzJABEY' );

        $scriptInfo = $nodeT->getScriptInfo( $addressT );
        $scriptInfo->script();
        $scriptInfo->complexity();
        $scriptInfo->extraFee();
        $scriptInfo->verifierComplexity();
        $mapComplexities = $scriptInfo->callableComplexities();

        $scriptMeta = $nodeT->getScriptMeta( $addressT );
        $version = $scriptMeta->metaVersion();
        $funcs = $scriptMeta->callableFunctions();
        foreach( $funcs as $func => $args )
            foreach( $args as $arg )
            {
                $arg->name();
                $arg->type();
            }

        $addressT = Address::fromString( '3NAV8CuN5Zn6TT1gChFM2wXRtdhUBDUtCVt' );

        $dataEntries1 = $nodeT->getData( $addressT, 'key_\d' );
        $dataEntries2 = $nodeT->getData( $addressT );

        $this->assertLessThan( count( $dataEntries2 ), count( $dataEntries1 ) );

        $keys = [];
        foreach( $dataEntries1 as $dataEntry )
            $keys[] = $dataEntry->key();

        $dataEntries2 = $nodeT->getDataByKeys( $addressT, $keys );
        $this->assertSame( count( $dataEntries1 ), count( $dataEntries2 ) );

        $n = count( $dataEntries1 );
        for( $i = 0; $i < $n; ++$i )
            $this->assertSame( $dataEntries1[$i]->value(), $dataEntries2[$i]->value() );

        $this->assertSame( ChainId::MAINNET, $nodeW->chainId()->asString() );
        $this->assertSame( ChainId::TESTNET, $nodeT->chainId()->asString() );
        $this->assertSame( ChainId::STAGENET, $nodeS->chainId()->asString() );

        $this->assertSame( $nodeW->uri(), Node::MAINNET );
        $this->assertSame( $nodeT->uri(), Node::TESTNET );
        $this->assertSame( $nodeS->uri(), Node::STAGENET );

        $heightW = $nodeW->getHeight();
        $heightT = $nodeT->getHeight();
        $heightS = $nodeS->getHeight();

        $this->assertLessThan( $heightW, $heightT );
        $this->assertLessThan( $heightT, $heightS );
        $this->assertLessThan( $heightS, 1 );

        $addresses = $nodeW->getAddresses();

        $address1 = $addresses[0];
        $address2 = $nodeW->getAddressesByIndexes( 0, 1 )[0];

        $this->assertSame( $address1->encoded(), Functions::base58Encode( $address2->bytes() ) );

        $balance1 = $nodeW->getBalance( $address1 );
        $balance2 = $nodeW->getBalance( $address2, 0 );

        $this->assertSame( $balance1, $balance2 );

        $balances = $nodeW->getBalances( $addresses );

        $balance1 = $balances[0];
        $balance2 = $nodeW->getBalances( $addresses, $heightW )[0];

        $this->assertSame( $balance1->getAddress(), $balance2->getAddress() );
        $this->assertSame( $balance1->getBalance(), $balance2->getBalance() );

        $balanceDetails = $nodeW->getBalanceDetails( $address1 );

        $this->assertSame( $balanceDetails->address(), $address1->toString() );
        $this->assertSame( $balanceDetails->available(), $balance1->getBalance() );
        $balanceDetails->effective();
        $balanceDetails->generating();
        $balanceDetails->regular();

        $headers = $nodeW->getLastBlockHeaders();
        $headers = $nodeW->getBlockHeadersByHeight( $headers->height() - 10 );

        $headers->baseTarget();
        $headers->desiredReward();
        $headers->features();
        $headers->generationSignature();
        $headers->generator();
        $headers->height();
        $headers->id();
        $headers->reference();
        $headers->reward();
        $headers->signature();
        $headers->size();
        $headers->timestamp();
        $headers->totalFee();
        $headers->transactionsCount();
        $headers->transactionsRoot();
        $headers->version();
        $headers->vrf();

        $height1 = $nodeW->getBlockHeightById( $headers->id()->encoded() );
        $height2 = $nodeW->getBlockHeightByTimestamp( $headers->timestamp() );

        $this->assertSame( $headers->height(), $height1 );
        $this->assertSame( $headers->height(), $height2 );

        $headers1 = $nodeW->getBlockHeadersByHeight( $headers->height() );
        $headers2 = $nodeW->getBlockHeadersById( $headers->id()->encoded() );
        $headers3 = $nodeW->getBlocksHeaders( $headers->height() - 1, $headers->height() )[1];
        $this->assertSame( $headers->toString(), $headers1->toString() );
        $this->assertSame( $headers->toString(), $headers2->toString() );
        $this->assertSame( $headers->toString(), $headers3->toString() );

        $delay = $nodeW->getBlocksDelay( $nodeW->getBlockHeadersByHeight( $headers->height() - 200 )->id()->encoded(), 100 );
        $this->assertLessThan( 70 * 1000, $delay );
        $this->assertLessThan( $delay, 50 * 1000 );
    }

    function testMoreCoverage(): void
    {
        $node1 = new Node( Node::MAINNET );
        $node2 = new Node( Node::MAINNET, ChainId::MAINNET() );
        $node3 = new Node( str_replace( 'https', 'http', Node::MAINNET ) );
        $this->assertSame( $node1->chainId()->asString(), $node2->chainId()->asString() );
        $this->assertSame( $node2->chainId()->asString(), $node3->chainId()->asString() );
        $this->assertNotSame( Functions::getRandomSeedPhrase(), Functions::getRandomSeedPhrase() );
    }

    function testExceptions(): void
    {
        $node = new Node( Node::MAINNET );
        $json = $node->get( '/blocks/headers/last' );

        $this->catchExceptionOrFail( ExceptionCode::FETCH_URI, function() use ( $node ){ $node->get( '/test' ); } );
        $this->catchExceptionOrFail( ExceptionCode::JSON_DECODE, function() use ( $node ){ $node->get( '/api-docs/favicon-16x16.png' ); } );
        $this->catchExceptionOrFail( ExceptionCode::KEY_MISSING, function() use ( $json ){ $json->get( 'x' ); } );
        $this->catchExceptionOrFail( ExceptionCode::INT_EXPECTED, function() use ( $json ){ $json->get( 'signature' )->asInt(); } );
        $this->catchExceptionOrFail( ExceptionCode::STRING_EXPECTED, function() use ( $json ){ $json->get( 'height' )->asString(); } );
        $this->catchExceptionOrFail( ExceptionCode::ARRAY_EXPECTED, function() use ( $json ){ $json->get( 'height' )->asArrayInt(); } );

        $this->catchExceptionOrFail( ExceptionCode::BAD_ALIAS, function(){ Recipient::fromAddressOrAlias( '123' ); } );
        $this->catchExceptionOrFail( ExceptionCode::BASE58_DECODE, function(){ Functions::base58Decode( 'ill' ); } );

        $this->catchExceptionOrFail( ExceptionCode::UNEXPECTED, function(){ Functions::getRandomSeedPhrase( 0 ); } );
    }
}

if( !defined( 'PHPUNIT_RUNNING' ) )
{
    $test = new NodeTest;
    $test->testNode();
    $test->testMoreCoverage();
    $test->testExceptions();
}
