<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Stripe;

/**
 * The platform's one Stripe connection.
 *
 * The composed backend binds this to the account configured centrally in the
 * admin panel (Einstellungen → Zahlungen). Modules resolve it from the
 * container and keep only their DOMAIN calls (an invoice, a checkout session);
 * the HTTP, the key and the error mapping live here. Billing, Tools and Shop
 * each used to carry their own curl client and their own key field.
 */
interface StripeApi
{
    /** False when no secret key is configured — callers answer 503. */
    public function isConfigured(): bool;

    /** `test` or `live` (from the key prefix), null when unconfigured. */
    public function mode(): ?string;

    /**
     * Form-encoded POST to `/v1{$path}`; nested arrays use Stripe's bracket
     * notation. An idempotency key makes a retried call return the first
     * result instead of creating a second object.
     *
     * @param  array<string,mixed> $params
     * @return array<string,mixed>
     * @throws StripeException
     */
    public function post(string $path, array $params = [], ?string $idempotencyKey = null): array;

    /**
     * @param  array<string,mixed> $query
     * @return array<string,mixed>
     * @throws StripeException
     */
    public function get(string $path, array $query = []): array;
}
