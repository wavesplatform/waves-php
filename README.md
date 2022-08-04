# Waves-PHP

[![packagist](https://img.shields.io/packagist/v/waves/client.svg)](https://packagist.org/packages/waves/client)
[![php-version](https://img.shields.io/packagist/php-v/waves/client.svg)](https://packagist.org/packages/waves/client)
[![codecov](https://img.shields.io/codecov/c/github/wavesplatform/waves-php)](https://app.codecov.io/gh/wavesplatform/waves-php)
[![phpstan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](https://github.com/wavesplatform/waves-php/blob/main/.github/workflows/phpstan.yml#L35)
[![codecov](https://img.shields.io/github/workflow/status/wavesplatform/waves-php/Codecov?label=Codecov)](https://github.com/wavesplatform/waves-php/actions/workflows/codecov.yml)
[![phpstan](https://img.shields.io/github/workflow/status/wavesplatform/waves-php/PHPStan?label=PHPStan)](https://github.com/wavesplatform/waves-php/actions/workflows/phpstan.yml)
[![phpstan](https://img.shields.io/github/workflow/status/wavesplatform/waves-php/PHPUnit?label=PHPUnit)](https://github.com/wavesplatform/waves-php/actions/workflows/phpunit.yml)

PHP client library for interacting with Waves blockchain platform.

## Installation
```bash
composer require waves/client
```

## Usage
See [`example.php`](example.php) for full code examples.
- New account:
```php
$account = PrivateKey::fromSeed( 'your mnemonic seed phrase' );
$sender = $account->publicKey();
echo 'address = ' . $sender->address()->toString() . PHP_EOL;
```
- Node basics:
```php
$nodeChainId = $node->chainId();
echo 'node chainId = ' . $nodeChainId->asString() . PHP_EOL;
$nodeVersion = $node->getVersion();
echo 'node version = ' . $nodeVersion . PHP_EOL;
$nodeHeight = $node->getHeight();
echo 'node height = ' . $nodeHeight . PHP_EOL;
```
- Transfer transaction:
```php
$amount = Amount::of( 1 ); // 0.00000001 Waves
$recipient = Recipient::fromAddressOrAlias( 'test' ); // from alias
$recipient = Recipient::fromAddressOrAlias( '3N9WtaPoD1tMrDZRG26wA142Byd35tLhnLU' ); // from address

$transferTx = TransferTransaction::build( $sender, $recipient, $amount );
$transferTxSigned = $transferTx->addProof( $account );
$transferTxBroadcasted = $node->broadcast( $transferTxSigned );
$transferTxConfirmed = $node->waitForTransaction( $transferTxBroadcasted->id() );
```
- DApp invocation transaction:
```php
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
```

## Requirements
- [PHP](http://php.net) >= 7.4
