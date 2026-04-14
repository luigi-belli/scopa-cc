<?php

declare(strict_types=1);

namespace App\Service;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

final readonly class MercureTokenService
{
    private Sha256 $signer;
    private Key $signingKey;
    private JoseEncoder $encoder;
    private ChainedFormatter $formatter;

    public function __construct(string $mercureSecret)
    {
        if ($mercureSecret === '') {
            throw new \InvalidArgumentException('MERCURE_JWT_SECRET must not be empty.');
        }
        $this->signer = new Sha256();
        $this->signingKey = InMemory::plainText($mercureSecret);
        $this->encoder = new JoseEncoder();
        $this->formatter = ChainedFormatter::default();
    }

    public function generateSubscriberToken(string $gameId, int $playerIndex): string
    {
        $topic = "/games/{$gameId}/player/{$playerIndex}";

        $token = (new Builder($this->encoder, $this->formatter))
            ->withClaim('mercure', ['subscribe' => [$topic]])
            ->getToken($this->signer, $this->signingKey);

        return $token->toString();
    }
}
