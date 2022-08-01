<?php

require_once __DIR__ . '/vendor/autoload.php';

use Waves\Account\PrivateKey;
use Waves\API\Node;
use Waves\Model\ChainId;
use Waves\Model\WavesConfig;
use Waves\Transactions\Amount;
use Waves\Transactions\Recipient;
use Waves\Transactions\TransferTransaction;

WavesConfig::chainId( ChainId::TESTNET() );

$account = PrivateKey::fromSeed( 'manage manual recall harvest series desert melt police rose hollow moral pledge kitten position add' );
$tx = TransferTransaction::build( $account->publicKey(), Recipient::fromAddressOrAlias( 'test' ), Amount::of( 1 ) );
$txId = Node::TESTNET()->broadcast( $tx->addProof( $account ) )->id();
$txOnChain = Node::TESTNET()->waitForTransaction( $txId );
