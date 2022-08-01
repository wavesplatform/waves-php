<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Account\Address;
use Waves\Common\JsonBase;
use Waves\Transactions\Recipient;

class LeaseInfo extends JsonBase
{
    function id(): Id { return $this->json->get( 'id' )->asId(); }
    function originTransactionId(): Id { return $this->json->get( 'originTransactionId' )->asId(); }
    function sender(): Address { return $this->json->get( 'sender' )->asAddress(); }
    function recipient(): Recipient { return $this->json->get( 'recipient' )->asRecipient(); }
    function amount(): int { return $this->json->get( 'amount' )->asInt(); }
    function height(): int { return $this->json->get( 'height' )->asInt(); }
    function status(): int
    {
        $status = $this->json->getOr( 'status', LeaseStatus::UNKNOWN_S )->asString();
        switch( $status )
        {
            case LeaseStatus::ACTIVE_S: return LeaseStatus::ACTIVE;
            case LeaseStatus::CANCELED_S: return LeaseStatus::CANCELED;
            default: return LeaseStatus::UNKNOWN;
        }
    }
    function cancelHeight(): int { return $this->json->get( 'cancelHeight' )->asInt(); }
    function cancelTransactionId(): Id { return $this->json->get( 'cancelTransactionId' )->asId(); }
}
