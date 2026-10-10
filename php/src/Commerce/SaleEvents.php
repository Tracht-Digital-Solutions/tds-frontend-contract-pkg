<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract\Commerce;

/**
 * The one service a selling module calls: "this sale is paid", "this sale is
 * reversed", "whose code is this". The base builds it from
 * {@see \Tds\Frontend\Contract\ModuleRegistry::saleListeners()} and
 * {@see \Tds\Frontend\Contract\ModuleRegistry::referralResolvers()} and binds it
 * in the container.
 *
 * Every listener call is guarded on its own: one broken listener neither stops
 * the others nor reaches the seller's webhook (a provider retries everything
 * that is not a 2xx). An empty instance — which is also what an autowiring
 * container builds when the base binds nothing — is a valid no-op.
 *
 * Resolve null-safely:
 *
 *     $events = $c->has(SaleEvents::class) ? $c->get(SaleEvents::class) : null;
 *     if ($events instanceof SaleEvents) { $events->paid($sale); }
 */
final class SaleEvents
{
    /** @var list<\Throwable> failures swallowed by the last call, for tests and logging */
    private array $failures = [];

    /** @var (callable(\Throwable): void)|null */
    private $onFailure;

    /**
     * @param list<SaleListener>     $listeners dependency-ordered
     * @param list<ReferralResolver> $resolvers first match wins
     * @param (callable(\Throwable): void)|null $onFailure e.g. error_log, called per swallowed failure
     */
    public function __construct(
        private readonly array $listeners = [],
        private readonly array $resolvers = [],
        ?callable $onFailure = null,
    ) {
        $this->onFailure = $onFailure;
    }

    public function paid(SaleEvent $sale): void
    {
        $this->each(static fn (SaleListener $l) => $l->onSalePaid($sale));
    }

    public function reversed(string $source, string $sourceId): void
    {
        $this->each(static fn (SaleListener $l) => $l->onSaleReversed($source, $sourceId));
    }

    /** Null for an empty, unknown or inactive code — never an exception. */
    public function resolveReferral(?string $code): ?ReferralMatch
    {
        $this->failures = [];
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }
        foreach ($this->resolvers as $resolver) {
            try {
                $match = $resolver->resolveReferral($code);
            } catch (\Throwable $e) {
                $this->fail($e);
                continue;
            }
            if ($match !== null) {
                return $match;
            }
        }
        return null;
    }

    /** @return list<\Throwable> */
    public function failures(): array
    {
        return $this->failures;
    }

    /** @param callable(SaleListener): void $call */
    private function each(callable $call): void
    {
        $this->failures = [];
        foreach ($this->listeners as $listener) {
            try {
                $call($listener);
            } catch (\Throwable $e) {
                $this->fail($e);
            }
        }
    }

    private function fail(\Throwable $e): void
    {
        $this->failures[] = $e;
        if ($this->onFailure !== null) {
            try {
                ($this->onFailure)($e);
            } catch (\Throwable) {
                // A failing logger must not undo the guard it reports for.
            }
        }
    }
}
