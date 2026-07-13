<?php
declare(strict_types=1);

namespace Tds\Panel\Contract;

/**
 * The core's transactional email sender, exposed to modules through the Slim
 * app's DI container (`$app->getContainer()->get(Mailer::class)`). The BASE
 * binds a concrete implementation (SMTP transport + From identity + config);
 * extensions only build an {@see Email} and call {@see send()} — no extension
 * ever configures its own SMTP.
 *
 * When the core's SMTP is unconfigured, the binding is a no-op implementation
 * whose {@see isConfigured()} returns false, so a module can skip/annotate
 * notifications without failing the request (mirrors the existing services'
 * Null* trigger pattern).
 */
interface Mailer
{
    /** Send the message. A no-op mailer silently drops it. */
    public function send(Email $email): void;

    /** False when the core has no SMTP configured (the send is a no-op). */
    public function isConfigured(): bool;
}
