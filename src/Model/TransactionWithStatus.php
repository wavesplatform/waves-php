<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Transactions\Transaction;

class TransactionWithStatus extends Transaction
{
    function applicationStatus(): int
    {
        return $this->json->getOr( 'applicationStatus', ApplicationStatus::SUCCEEDED_S )->asApplicationStatus();
    }
}
