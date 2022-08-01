<?php declare( strict_types = 1 );

namespace Waves\Model;

use Waves\Common\JsonBase;

class BlockchainRewards extends JsonBase
{
    function height(): int { return $this->json->get( 'height' )->asInt(); }
    function currentReward(): int { return $this->json->get( 'currentReward' )->asInt(); }
    function totalWavesAmount(): int { return $this->json->get( 'totalWavesAmount' )->asInt(); }
    function minIncrement(): int { return $this->json->get( 'minIncrement' )->asInt(); }
    function term(): int { return $this->json->get( 'term' )->asInt(); }
    function nextCheck(): int { return $this->json->get( 'nextCheck' )->asInt(); }
    function votingIntervalStart(): int { return $this->json->get( 'votingIntervalStart' )->asInt(); }
    function votingInterval(): int { return $this->json->get( 'votingInterval' )->asInt(); }
    function votingThreshold(): int { return $this->json->get( 'votingThreshold' )->asInt(); }
    function votes(): Votes { return $this->json->get( 'votes' )->asJson()->asVotes(); }
}
