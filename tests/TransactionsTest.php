<?php

namespace Waves;

require_once 'common.php';

use Exception;
use Waves\Account\Address;
use Waves\Account\PrivateKey;
use Waves\Account\PublicKey;
use Waves\API\Node;
use Waves\Common\Base58String;
use Waves\Common\Base64String;
use Waves\Common\ExceptionCode;
use Waves\Common\Value;
use Waves\Model\Alias;
use Waves\Model\ApplicationStatus;
use Waves\Model\AssetId;
use Waves\Model\ChainId;
use Waves\Model\DataEntry;
use Waves\Model\EntryType;
use Waves\Model\ScriptInfo;
use Waves\Model\WavesConfig;
use Waves\Transactions\Amount;
use Waves\Transactions\BurnTransaction;
use Waves\Transactions\CreateAliasTransaction;
use Waves\Transactions\DataTransaction;
use Waves\Transactions\Invocation\Arg;
use Waves\Transactions\Invocation\FunctionCall;
use Waves\Transactions\InvokeScriptTransaction;
use Waves\Transactions\IssueTransaction;
use Waves\Transactions\LeaseCancelTransaction;
use Waves\Transactions\LeaseTransaction;
use Waves\Transactions\Mass\Transfer;
use Waves\Transactions\MassTransferTransaction;
use Waves\Transactions\Recipient;
use Waves\Transactions\ReissueTransaction;
use Waves\Transactions\SetAssetScriptTransaction;
use Waves\Transactions\SetScriptTransaction;
use Waves\Transactions\SponsorFeeTransaction;
use Waves\Transactions\TransferTransaction;
use Waves\Transactions\UpdateAssetInfoTransaction;

class TransactionsTest extends \PHPUnit\Framework\TestCase
{
    const WAVES_FOR_TEST = 500000000;
    const SPONSOR_ID = 'SPONSOR_ID';
    const TOKEN_ID = 'TOKEN_ID';

    private ChainId $chainId;
    private Node $node;
    private PrivateKey $faucet;
    private PrivateKey $account;
    private AssetId $sponsorId;
    private AssetId $tokenId;

    private function prepare(): void
    {
        if( isset( $this->chainId ) )
            return;

        if( !defined( 'WAVES_NODE' ) || !defined( 'WAVES_FAUCET' ) )
            throw new Exception( 'Missing WAVES_NODE and WAVES_FAUCET definitions', ExceptionCode::UNEXPECTED );

        $WAVES_NODE = constant( 'WAVES_NODE' );
        $WAVES_FAUCET = constant( 'WAVES_FAUCET' );

        if( !is_string( $WAVES_NODE ) || !is_string( $WAVES_FAUCET ) )
            throw new Exception( '$WAVES_NODE and $WAVES_FAUCET should be strings', ExceptionCode::UNEXPECTED );

        $account = getenv( 'WAVES_ACCOUNT' );
        if( is_string( $account ) )
            $account = PrivateKey::fromString( $account );
        else
        {
            $account = PrivateKey::fromBytes( random_bytes( 32 ) );
            putenv( 'WAVES_ACCOUNT=' . $account->toString() );
        }

        $node = new Node( $WAVES_NODE );
        $chainId = $node->chainId();

        WavesConfig::chainId( $chainId );
        $faucet = PrivateKey::fromSeed( $WAVES_FAUCET );

        $publicKey = PublicKey::fromPrivateKey( $account );
        $address = Address::fromPublicKey( $publicKey );

        $this->assertSame( $account->publicKey()->toString(), $publicKey->toString() );
        $this->assertSame( $account->publicKey()->address()->toString(), $address->toString() );

        $this->node = $node;
        $this->faucet = $faucet;
        $this->account = $account;
        $this->chainId = $chainId;

        $this->prepareRoot();
        $this->prepareFunds();
        $this->prepareSponsor();
        $this->prepareToken();
    }

    private function prepareRoot(): void
    {
        $node = $this->node;
        $faucet = $this->faucet;

        $address = $this->fetchOr( function(){ return $this->node->getAddressByAlias( Alias::fromString( 'root' ) ); }, false );
        if( $address === false )
        {
            $node->waitForTransaction(
                $node->broadcast(
                    CreateAliasTransaction::build( $faucet->publicKey(), Alias::fromString( 'root' ) )->addProof( $faucet )
                )->id()
            );
        }

        $scriptCode = file_get_contents( __DIR__ . '/retransmit.ride' );
        if( $scriptCode === false )
            throw new Exception( 'Missing `retransmit.ride` file', ExceptionCode::UNEXPECTED );
        $scriptCompiled = $node->compileScript( $scriptCode );

        $script = $this->fetchOr( function(){ return $this->node->getScriptInfo( $this->faucet->publicKey()->address() ); }, false );
        if( !( $script instanceof ScriptInfo ) || $script->script()->bytes() !== $scriptCompiled->script()->bytes() )
        {
            $node->waitForTransaction(
                $node->broadcast(
                    SetScriptTransaction::build( $faucet->publicKey(), $scriptCompiled->script() )->addProof( $faucet )
                )->id()
            );
        }

        $assets = $node->getAssetsBalance( $faucet->publicKey()->address() );
        foreach( $assets as $asset )
        {
            $amount = Amount::of( $asset->balance(), $asset->assetId() );
            $this->fetchOr( function() use ( $node, $faucet, $amount ){ $node->broadcast( BurnTransaction::build( $faucet->publicKey(), $amount )->addProof( $faucet ) ); }, false );
        }
    }

    private function prepareFunds(): void
    {
        $node = $this->node;
        $address = $this->account->publicKey()->address();
        $faucet = $this->faucet;

        $balance = $this->node->getBalance( $address );
        if( $balance < self::WAVES_FOR_TEST )
            $node->waitForTransaction(
                $node->broadcast(
                    TransferTransaction::build( $faucet->publicKey(), Recipient::fromAddress( $address ), Amount::of( self::WAVES_FOR_TEST * 2 ) )->addProof( $faucet )
                )->id()
            );

        $this->assertGreaterThan( self::WAVES_FOR_TEST, $this->node->getBalance( $address ) );
    }

    /**
     * @param callable $block
     * @param mixed $default
     * @return mixed
     */
    private function fetchOr( callable $block, $default )
    {
        try
        {
            return $block();
        }
        catch( Exception $e )
        {
            if( $e->getCode() & ExceptionCode::BASE )
                return $default;
            throw $e;
        }
    }

    private function prepareSponsor(): void
    {
        $sponsorId = $this->fetchOr( function(){ return $this->node->getDataByKey( $this->account->publicKey()->address(), self::SPONSOR_ID )->stringValue(); }, false );
        if( is_string( $sponsorId ) )
            $this->sponsorId = AssetId::fromString( $sponsorId );
        else
        {
            $node = $this->node;
            $account = $this->account;
            $sender = $account->publicKey();

            $tx = $node->waitForTransaction( $node->broadcast( IssueTransaction::build( $sender, 'SPONSOR', '', 1000, 0, false )->addProof( $account ) )->id() );
            $this->sponsorId = AssetId::fromString( $tx->id()->toString() );
            $tx = $node->waitForTransaction( $node->broadcast( SponsorFeeTransaction::build( $sender, $this->sponsorId, 1 )->addProof( $account ) )->id() );

            $node->waitForTransaction( $node->broadcast( DataTransaction::build( $sender, [ DataEntry::string( self::SPONSOR_ID, $this->sponsorId->toString() ) ] )->addProof( $account ) )->id() );
        }
    }

    private function prepareToken(): void
    {
        $tokenId = $this->fetchOr( function(){ return $this->node->getDataByKey( $this->account->publicKey()->address(), self::TOKEN_ID )->stringValue(); }, false );
        if( is_string( $tokenId ) )
            $this->tokenId = AssetId::fromString( $tokenId );
        else
        {
            $node = $this->node;
            $account = $this->account;
            $sender = $account->publicKey();

            $tx = $node->waitForTransaction( $node->broadcast( IssueTransaction::build( $sender, 'TOKEN', '', 1000000, 6, true )->addProof( $account ) )->id() );
            $this->tokenId = AssetId::fromString( $tx->id()->toString() );

            $node->waitForTransaction( $node->broadcast( DataTransaction::build( $sender, [ DataEntry::string( self::TOKEN_ID, $this->tokenId->toString() ) ] )->addProof( $account ) )->id() );
        }
    }

    function testAlias(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $tx = CreateAliasTransaction::build(
            $sender,
            Alias::fromString( 'name-' . mt_rand( 10000000000, 99999999999 ) )
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->alias();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new CreateAliasTransaction)
                ->setAlias( Alias::fromString( 'name-' . mt_rand( 10000000000, 99999999999 ) ) )

                ->setSender( $sender )
                ->setType( CreateAliasTransaction::TYPE )
                ->setVersion( CreateAliasTransaction::LATEST_VERSION )
                ->setFee( Amount::of( CreateAliasTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testLeaseAndLeaseCancel(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $recipient = Recipient::fromAddressOrAlias( 'root' );

        // LEASE

        $tx = LeaseTransaction::build(
            $sender,
            $recipient,
            10_000_000
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->recipient();
        $tx->amount();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new LeaseTransaction)
                ->setRecipient( Recipient::fromAddress( $node->getAddressByAlias( $recipient->alias() ) ) )
                ->setAmount( 20_000_000 )

                ->setSender( $sender )
                ->setType( LeaseTransaction::TYPE )
                ->setVersion( LeaseTransaction::LATEST_VERSION )
                ->setFee( Amount::of( LeaseTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );

        // LEASE_CANCEL

        $tx = LeaseCancelTransaction::build(
            $sender,
            $tx1->id()
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->leaseId();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new LeaseCancelTransaction)
                ->setLeaseId( $tx2->id() )

                ->setSender( $sender )
                ->setType( LeaseCancelTransaction::TYPE )
                ->setVersion( LeaseCancelTransaction::LATEST_VERSION )
                ->setFee( Amount::of( LeaseCancelTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testSetScript(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $script = Base64String::fromString( 'AAIFAAAAAAAAAAcIAhIDCgEfAAAAAAAAAAEAAAABaQEAAAAEY2FsbAAAAAEAAAAEbGlzdAUAAAADbmlsAAAAAQAAAAJ0eAEAAAAGdmVyaWZ5AAAAAAkACcgAAAADCAUAAAACdHgAAAAJYm9keUJ5dGVzCQABkQAAAAIIBQAAAAJ0eAAAAAZwcm9vZnMAAAAAAAAAAAAIBQAAAAJ0eAAAAA9zZW5kZXJQdWJsaWNLZXmQFHRt' );

        $tx = SetScriptTransaction::build(
            $sender,
            $script
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->script();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new SetScriptTransaction)
                ->setScript() // remove script

                ->setSender( $sender )
                ->setType( SetScriptTransaction::TYPE )
                ->setVersion( SetScriptTransaction::LATEST_VERSION )
                ->setFee( Amount::of( SetScriptTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testSetAssetScript(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $script = Base64String::fromString( 'BQbtKNoM' );

        $tx = $node->waitForTransaction( $node->broadcast( IssueTransaction::build( $sender, 'SCRIPTED', '', 1, 0, false, $script )->addProof( $account ) )->id() );
        $scriptedId = AssetId::fromString( $tx->id()->toString() );

        $tx = SetAssetScriptTransaction::build(
            $sender,
            $scriptedId,
            $script
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->assetId();
        $tx->script();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new SetAssetScriptTransaction)
                ->setAssetId( $scriptedId )
                ->setScript( $script )

                ->setSender( $sender )
                ->setType( SetAssetScriptTransaction::TYPE )
                ->setVersion( SetAssetScriptTransaction::LATEST_VERSION )
                ->setFee( Amount::of( SetAssetScriptTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testReissue(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $tx = ReissueTransaction::build(
            $sender,
            Amount::of( 1000_000_000, $this->tokenId ),
            true
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->amount();
        $tx->isReissuable();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new ReissueTransaction)
                ->setAmount( Amount::of( 2000_000_000, $this->tokenId ) )
                ->setIsReissuable( true )

                ->setSender( $sender )
                ->setType( ReissueTransaction::TYPE )
                ->setVersion( ReissueTransaction::LATEST_VERSION )
                ->setFee( Amount::of( ReissueTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testBurn(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $tx = BurnTransaction::build(
            $sender,
            Amount::of( 100_000_000, $this->tokenId )
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->amount();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new BurnTransaction)
                ->setAmount( Amount::of( 10_000_000, $this->tokenId ) )

                ->setSender( $sender )
                ->setType( BurnTransaction::TYPE )
                ->setVersion( BurnTransaction::LATEST_VERSION )
                ->setFee( Amount::of( BurnTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testIssue(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $tx = IssueTransaction::build(
            $sender,
            'NFT-' . mt_rand( 100000, 999999 ),
            'test description',
            1,
            0,
            false
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->name();
        $tx->description();
        $tx->quantity();
        $tx->decimals();
        $tx->isReissuable();
        $tx->script();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new IssueTransaction)
                ->setName( 'NFT-' . mt_rand( 100000, 999999 ) )
                ->setDescription( 'test description' )
                ->setQuantity( 1 )
                ->setDecimals( 0 )
                ->setIsReissuable( false )

                ->setSender( $sender )
                ->setType( IssueTransaction::TYPE )
                ->setVersion( IssueTransaction::LATEST_VERSION )
                ->setFee( Amount::of( IssueTransaction::NFT_MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testRename(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $tokenId = $this->tokenId;

        $assetInfo = $node->getAssetDetails( $tokenId );
        $node->waitForHeight( $assetInfo->issueHeight() + 2 );

        $tx = UpdateAssetInfoTransaction::build(
            $sender,
            $tokenId,
            'TOKEN-RENAMED',
            'renamed description'
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->assetId();
        $tx->name();
        $tx->description();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $node->waitBlocks( 2 );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new UpdateAssetInfoTransaction)
                ->setAssetId( $this->sponsorId )
                ->setName( 'SPONSOR-RENAMED' )
                ->setDescription( 'renamed description' )

                ->setSender( $sender )
                ->setType( UpdateAssetInfoTransaction::TYPE )
                ->setVersion( UpdateAssetInfoTransaction::LATEST_VERSION )
                ->setFee( Amount::of( UpdateAssetInfoTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testSponsorship(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $sponsorId = $this->sponsorId;

        $tx = SponsorFeeTransaction::build(
            $sender,
            $sponsorId,
            1
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->assetId();
        $tx->minSponsoredFee();

        $tx1 = $node->broadcast( $tx->addProof( $account ) );
        $node->waitForTransactions( [ $tx1->id() ] );

        $tx1 = $node->waitForTransaction( $tx1->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new SponsorFeeTransaction())
                ->setAssetId( $sponsorId )
                ->setMinSponsoredFee( 1 )

                ->setSender( $sender )
                ->setType( SponsorFeeTransaction::TYPE )
                ->setVersion( SponsorFeeTransaction::LATEST_VERSION )
                ->setFee( Amount::of( SponsorFeeTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testData(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $data = [];
        $data[] = DataEntry::build( 'key_string', EntryType::STRING, '123' );
        $data[] = DataEntry::build( 'key_binary', EntryType::BINARY, '123' );
        $data[] = DataEntry::build( 'key_boolean', EntryType::BOOLEAN, true );
        $data[] = DataEntry::build( 'key_integer', EntryType::INTEGER, 123 );
        $data[] = DataEntry::build( 'key_delete', EntryType::DELETE );

        $tx = DataTransaction::build(
            $sender,
            $data
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->data();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new DataTransaction)
                ->setData( $data )

                ->setSender( $sender )
                ->setType( DataTransaction::TYPE )
                ->setVersion( DataTransaction::LATEST_VERSION )
                ->setFee( Amount::of( DataTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testMassTransfer(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $transfers = [];
        $transfers[] = new Transfer( Recipient::fromAlias( Alias::fromString( 'root' ) ), 1 );
        $transfers[] = new Transfer( Recipient::fromAddress( $sender->address() ), 2 );

        $attachment = Base58String::fromBytes( 'root' );

        $tx = MassTransferTransaction::build(
            $sender,
            $this->tokenId,
            $transfers,
            $attachment,
        );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->assetId();
        $tx->transfers();
        $tx->attachment();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new MassTransferTransaction)
                ->setAssetId( AssetId::WAVES() )
                ->setTransfers( $transfers )
                ->setAttachment( $attachment )

                ->setSender( $sender )
                ->setType( MassTransferTransaction::TYPE )
                ->setVersion( MassTransferTransaction::LATEST_VERSION )
                ->setFee( Amount::of( MassTransferTransaction::calculateFee( count( $transfers ) ) ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testTransfer(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $recipient = Recipient::fromAlias( Alias::fromString( 'root' ) );
        $amount = new Amount( 1, AssetId::WAVES() );
        $attachment = Base58String::fromBytes( 'root' );

        $tx = TransferTransaction::build(
            $sender,
            $recipient,
            $amount,
        )->setFee( Amount::of( 1, $this->sponsorId ) );

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->recipient();
        $tx->amount();
        $tx->attachment();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new TransferTransaction)
                ->setRecipient( Recipient::fromAddress( $node->getAddressByAlias( $recipient->alias() ) ) )
                ->setAmount( Amount::of( 1000, $this->tokenId ) )
                ->setAttachment( $attachment )

                ->setSender( $sender )
                ->setType( TransferTransaction::TYPE )
                ->setVersion( TransferTransaction::LATEST_VERSION )
                ->setFee( Amount::of( TransferTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }

    function testInvoke(): void
    {
        $this->prepare();
        $chainId = $this->chainId;
        $node = $this->node;
        $account = $this->account;
        $sender = $account->publicKey();

        $dApp = Recipient::fromAddressOrAlias( 'root' );
        $args = [];
        $args[] = Arg::as( Arg::STRING, Value::as( $sender->address()->toString() ) );
        $args[] = Arg::as( Arg::INTEGER, Value::as( 1000 ) );
        $args[] = Arg::as( Arg::BINARY, Value::as( '' ) );
        $args[] = Arg::as( Arg::BOOLEAN, Value::as( true ) );
        $list = [];
        $list[] = Arg::as( Arg::STRING, Value::as( '0' ) );
        $list[] = Arg::as( Arg::STRING, Value::as( '1' ) );
        $list[] = Arg::as( Arg::STRING, Value::as( '2' ) );
        $args[] = Arg::as( Arg::LIST, Value::as( $list ) );
        $function = FunctionCall::as( 'retransmit', $args );
        $payments = [];
        $payments[] = Amount::of( 1000 );

        $tx = InvokeScriptTransaction::build(
            $sender,
            $dApp,
            $function,
            $payments
        )->setFee( Amount::of( 5, $this->sponsorId ) );;

        $tx->bodyBytes();

        $id = $tx->id();
        $tx->version();
        $tx->chainId();
        $tx->sender();
        $tx->timestamp();
        $tx->fee();
        $tx->proofs();

        $tx->dApp();
        $tx->function();
        $tx->payments();

        $tx1 = $node->waitForTransaction( $node->broadcast( $tx->addProof( $account ) )->id() );

        $this->assertSame( $id->toString(), $tx1->id()->toString() );
        $this->assertSame( $tx1->applicationStatus(), ApplicationStatus::SUCCEEDED );

        $txFromJson = new InvokeScriptTransaction( $tx1->json() );
        $this->assertSame( $tx->dApp()->toString(), $txFromJson->dApp()->toString() );
        $this->assertSame( serialize( $tx->payments() ), serialize( $txFromJson->payments() ) );
        $this->assertSame( serialize( $tx->function() ), serialize( $txFromJson->function() ) );

        $tx2 = $node->waitForTransaction(
            $node->broadcast(
                (new InvokeScriptTransaction)
                ->setDApp( $dApp )
                ->setFunction( $function )
                ->setPayments( $payments )

                ->setSender( $sender )
                ->setType( InvokeScriptTransaction::TYPE )
                ->setVersion( InvokeScriptTransaction::LATEST_VERSION )
                ->setFee( Amount::of( InvokeScriptTransaction::MIN_FEE ) )
                ->setChainId( $chainId )
                ->setTimestamp()

                ->addProof( $account )
            )->id()
        );

        $this->assertNotSame( $tx1->id(), $tx2->id() );
        $this->assertSame( $tx2->applicationStatus(), ApplicationStatus::SUCCEEDED );
    }
}

if( !defined( 'PHPUNIT_RUNNING' ) )
{
    $test = new TransactionsTest;
    $test->testInvoke();
    $test->testSponsorship();
    $test->testRename();
    $test->testSetScript();
    $test->testData();
    $test->testMassTransfer();
    $test->testTransfer();
    $test->testAlias();
    $test->testLeaseAndLeaseCancel();
    $test->testIssue();
    $test->testReissue();
    $test->testBurn();
    $test->testSetAssetScript();
}
