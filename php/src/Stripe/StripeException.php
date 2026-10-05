<?php

declare(strict_types=1);

namespace Tds\Frontend\Contract\Stripe;

/**
 * A Stripe call failed. `getCode()` is the HTTP status (0 = never reached
 * Stripe). The message is Stripe's own `error.message` — safe to show an admin,
 * never a stack trace or the key.
 */
final class StripeException extends \RuntimeException
{
}
