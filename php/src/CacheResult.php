<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * Truthful result of a public-site cache refresh.
 *
 * Content persistence and cache refresh are deliberately separate outcomes:
 * callers may save successfully while returning this result as a warning.
 */
final class CacheResult
{
    public const REFRESHED = 'refreshed';
    public const NOT_CONFIGURED = 'not_configured';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    /** @var list<string> */
    public readonly array $rebuilt;
    /** @var list<string> */
    public readonly array $skipped;
    /** @var list<mixed> */
    public readonly array $failed;
    /** @var list<mixed> */
    public readonly array $unknownEvents;

    /**
     * @param list<string> $rebuilt
     * @param list<string> $skipped
     * @param list<mixed>  $failed
     * @param list<mixed>  $unknownEvents
     */
    public function __construct(
        public readonly string $status,
        array $rebuilt = [],
        array $skipped = [],
        array $failed = [],
        array $unknownEvents = [],
    ) {
        if (!in_array($status, [self::REFRESHED, self::NOT_CONFIGURED, self::FAILED, self::SKIPPED], true)) {
            throw new \InvalidArgumentException("Unknown cache status \"{$status}\"");
        }
        $this->rebuilt = array_values($rebuilt);
        $this->skipped = array_values($skipped);
        $this->failed = array_values($failed);
        $this->unknownEvents = array_values($unknownEvents);
    }

    public function cached(): bool
    {
        return $this->status === self::REFRESHED
            && $this->failed === []
            && $this->unknownEvents === []
            && $this->skipped === [];
    }

    /** @return array{cache_status:string,cached:bool,rebuilt:list<string>,skipped:list<string>,failed:list<mixed>,unknownEvents:list<mixed>} */
    public function toArray(): array
    {
        return [
            'cache_status' => $this->status,
            'cached' => $this->cached(),
            'rebuilt' => $this->rebuilt,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'unknownEvents' => $this->unknownEvents,
        ];
    }
}
