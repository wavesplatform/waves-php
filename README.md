# Waves-PHP

PHP client library for interacting with Waves blockchain platform.

## Installation
```bash
composer require waves/client
```

## Usage
- Transfer:
```php
$account = PrivateKey::fromSeed( 'manage manual recall harvest series desert melt police rose hollow moral pledge kitten position add' );
$tx = TransferTransaction::build( $account->publicKey(), Recipient::fromAddressOrAlias( 'test' ), Amount::of( 1 ) );
$txId = Node::TESTNET()->broadcast( $tx->addProof( $account ) )->id();
$txOnChain = Node::TESTNET()->waitForTransaction( $txId );
```

## Requirements
- [PHP](http://php.net) >= 7.4
