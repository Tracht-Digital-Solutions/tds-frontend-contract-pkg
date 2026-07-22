<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * A fine-grained RBAC capability a module introduces. The PHP twin of the
 * TypeScript `PermissionDef` — the frontend catalog and the JWT-enforced
 * backend list must agree on `id`, exactly like the existing Zod ↔ PHP
 * validator duplication.
 */
final class PermissionDef
{
    /**
     * @param string      $id    `resource:action`, e.g. "time:read". Globally unique.
     * @param string      $label German label for the admin user editor.
     * @param string|null $group Optional grouping key (e.g. "time-tracker").
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $group = null,
    ) {
    }

    /** @return array{id: string, label: string, group: string|null} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'group' => $this->group];
    }
}
