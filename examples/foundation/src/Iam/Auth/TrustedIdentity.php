<?php

declare(strict_types=1);

namespace Foundation\Iam\Auth;

/** The X-Identity header is `{payload}.{hmac}`, the payload being the claims as base64url JSON, `sub` and `exp` among them. */
final readonly class TrustedIdentity
{
    public function __construct(private string $secret) {}

    /** @param array<string, mixed> $claims with `sub`; `exp` is set to $expiresAt */
    public static function sign(array $claims, int $expiresAt, string $secret): string
    {
        $payload = rtrim(strtr(base64_encode((string) json_encode([...$claims, 'exp' => $expiresAt])), '+/', '-_'), '=');

        return $payload.'.'.hash_hmac('sha256', $payload, $secret);
    }

    /** @return int|null the user's id */
    public function validate(string $token): ?int
    {
        [$payload, $signature] = explode('.', $token, 2) + ['', ''];

        if (! hash_equals(hash_hmac('sha256', $payload, $this->secret), $signature)) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        if (! is_array($claims) || (int) ($claims['exp'] ?? 0) < time() || ! ctype_digit((string) ($claims['sub'] ?? ''))) {
            return null;
        }

        return (int) $claims['sub'];
    }
}
