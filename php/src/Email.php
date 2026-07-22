<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * A transactional email a module hands to the core {@see Mailer}. The core owns
 * the actual SMTP transport + the From identity; a module only describes the
 * message (recipient + content), so email config lives in ONE place (the base),
 * never per-extension.
 */
final class Email
{
    /**
     * @param string      $toEmail  recipient address
     * @param string      $toName   recipient display name ('' if none)
     * @param string      $subject  subject line
     * @param string      $htmlBody HTML body
     * @param string|null $textBody optional plain-text alternative
     * @param string|null $replyTo  optional Reply-To (e.g. a ticket inbox)
     */
    public function __construct(
        public readonly string $toEmail,
        public readonly string $toName,
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly ?string $textBody = null,
        public readonly ?string $replyTo = null,
    ) {
    }
}
