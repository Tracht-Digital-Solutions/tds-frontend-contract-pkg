<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * A runtime-editable `app_setting` key a module declares. Drives the admin
 * Einstellungen / Einrichtungsassistent. Secret values are AES-256-GCM
 * encrypted at rest (per-service SETTINGS_ENCRYPTION_KEY); the admin API only
 * ever returns masked state (configured / last4 / source), never a raw secret.
 */
final class SettingDef
{
    /**
     * @param string      $key     `app_setting` key, e.g. "STRIPE_SECRET_KEY".
     * @param string      $label   German label shown in the settings UI.
     * @param bool        $secret  Encrypt at rest + mask in the API. Default true.
     * @param string|null $group   Optional grouping (e.g. "Stripe").
     * @param string|null $default Coded default when neither DB nor .env sets it.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $secret = true,
        public readonly ?string $group = null,
        public readonly ?string $default = null,
    ) {
    }
}
