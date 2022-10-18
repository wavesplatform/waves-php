<?php

require_once __DIR__ . '/vendor/autoload.php';

use Waves\Account\PrivateKey;
use Waves\API\Node;
use Waves\Common\Value;
use Waves\Model\ChainId;
use Waves\Model\WavesConfig;
use Waves\Transactions\Amount;
use Waves\Transactions\Invocation\Arg;
use Waves\Transactions\Invocation\FunctionCall;
use Waves\Transactions\InvokeScriptTransaction;
use Waves\Transactions\Recipient;
use Waves\Transactions\TransferTransaction;
use Waves\Util\Functions;

## MAIN CONFIG

# working on the TESTNET
WavesConfig::chainId( ChainId::TESTNET() );
$node = Node::TESTNET();

## CREATE NEW ACCOUNT

# from a seed
$seed = 'your mnemonic seed phrase';
$account = PrivateKey::fromSeed( $seed );

# or from a random seed
//$seed = Functions::getRandomSeedPhrase();
//$account = PrivateKey::fromSeed( $seed );

# or from a raw 32 bytes private key (32 bytes)
//$privateKeyBytes = random_bytes( 32 );
//$account = PrivateKey::fromBytes( $privateKeyBytes );

# or from a base58 encoded private key
$account = PrivateKey::fromString( $account->toString() );

# a public key is used as a sender
$sender = $account->publicKey();

# address from the public key
echo 'address = ' . $sender->address()->toString() . PHP_EOL;

## BASIC BLOCKCHAIN INFO

$nodeChainId = $node->chainId();
echo 'node chainId = ' . $nodeChainId->asString() . PHP_EOL;
$nodeVersion = $node->getVersion();
echo 'node version = ' . $nodeVersion . PHP_EOL;
$nodeHeight = $node->getHeight();
echo 'node height = ' . $nodeHeight . PHP_EOL;

$addressBalance = $node->getBalance( $sender->address() );
echo $sender->address()->toString() . ' balance = ' . $addressBalance . ' Waves' . PHP_EOL;

$addressTransactions = $node->getTransactionsByAddress( $sender->address(), 5 );
if( count( $addressTransactions ) > 0 )
{
    echo $sender->address()->toString() . ' latest transaction ids:' . PHP_EOL;
    foreach( $addressTransactions as $transaction )
        echo '- ' . $transaction->id()->toString() . PHP_EOL;
}

# IMPORTANT: it is assumed below that we have some Waves on the address

## TRANSFER EXAMPLE

$amount = Amount::of( 1 ); // 0.00000001 Waves
$recipient = Recipient::fromAddressOrAlias( 'test' ); // from alias
$recipient = Recipient::fromAddressOrAlias( '3N9WtaPoD1tMrDZRG26wA142Byd35tLhnLU' ); // from address

$transferTx = TransferTransaction::build( $sender, $recipient, $amount );
$transferTxSigned = $transferTx->addProof( $account );
$transferTxBroadcasted = $node->broadcast( $transferTxSigned );
$transferTxConfirmed = $node->waitForTransaction( $transferTxBroadcasted->id() );

echo 'transfer transaction ' . $transferTxConfirmed->id()->toString() . ' is confirmed' . PHP_EOL;

## DAPP FUNCTION INVOCATION EXAMPLE

$dApp = Recipient::fromAddressOrAlias( '3N7uoMNjqNt1jf9q9f9BSr7ASk1QtzJABEY' );
$functionCall = FunctionCall::as( 'retransmit', [
    Arg::as( Arg::STRING, Value::as( $sender->address()->toString() ) ),
    Arg::as( Arg::INTEGER, Value::as( 1 ) ),
    Arg::as( Arg::BINARY, Value::as( hash( 'sha256', $sender->address()->toString(), true ) ) ),
    Arg::as( Arg::BOOLEAN, Value::as( true ) ),
] );
$payments = [
    Amount::of( 1000 ),
];

$invokeTx = InvokeScriptTransaction::build( $sender, $dApp, $functionCall, $payments );
$invokeTxSigned = $invokeTx->addProof( $account );
$invokeTxBroadcasted = $node->broadcast( $invokeTxSigned );
$invokeTxConfirmed = $node->waitForTransaction( $invokeTxBroadcasted->id() );

echo 'invocation transaction ' . $invokeTxConfirmed->id()->toString() . ' is confirmed' . PHP_EOL;
