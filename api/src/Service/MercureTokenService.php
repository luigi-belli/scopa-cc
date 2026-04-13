<?php

declare(strict_types=1);

namespace App\Service;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

final readonly class MercureTokenService
{
    /** @var non-empty-string */
    private string $mercureSecret;

    public function __construct(string $mercureSecret)
    {
        if ($mercureSecret === '') {
            throw new \InvalidArgumentException('MERCURE_JWT_SECRET must not be empty.');
        }
        $this->mercureSecret = $mercureSecret;
    }

    public function generateSubscriberToken(string $gameId, int $playerIndex): string
    {
        $topic = "/games/{$gameId}/player/{$playerIndex}";

        $builder = new Builder(new JoseEncoder(), ChainedFormatter::default());
        $token = $builder
            ->withClaim('mercure', ['subscribe' => [$topic]])
            ->getToken(new Sha256(), InMemory::plainText($this->mercureSecret));

        return $token->toString();
    }
}
