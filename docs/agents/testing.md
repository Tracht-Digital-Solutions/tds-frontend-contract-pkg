# Testing

```bash
npm run test:run   # vitest
composer test      # phpunit
```

This SDK is depended on by every extension and both products, so a weak test here is a bug
everywhere at once. Mutation check: 47 breakages, 47 caught.

| Suite | Covers |
|---|---|
| `src/__tests__/registry.test.ts` | Happy paths |
| `src/__tests__/registry.collisions.test.ts` | **Each** contribution kind throws on a duplicate id (asserted separately; one shared `Set` would pass a routes-only test); duplicate extension ids; cycles incl. self-dependency; diamonds; missing deps; stable `order` sorting (default 100); routes left unsorted; i18n merge precedence (**later wins**, by dependency order) |
| `src/__tests__/astro.test.ts` | Layout wrapping in both directions, and `<Page />` **nested inside** `<Layout>`; composition failures throw while constructing the integration; wrapper slugs; the three virtual modules; `resolveId` ignores foreign ids; static imports for widgets/settings; both virtual-module spellings resolve to one instance |

`node:fs` is mocked in the Astro tests; what matters is what would be written and which path is
injected.

## Toolchain floor: TypeScript 6, vitest 4, tsup 8.5

- `tsconfig.json` needs `"types": ["node"]` (TypeScript 6 stops resolving `node:*` on a fresh
  install without it) and `"ignoreDeprecations": "6.0"` (tsup's d.ts step sets a deprecated
  `baseUrl`; without it the DTS build dies while `tsc --noEmit` passes).
- TypeScript 7 throws in that tsup code path; it doesn't build.
- `vitest.config.ts` pins `include: ["src/**/*.test.ts"]`. The default glob sweeps
  `.claude/worktrees/*/src/**` into the run and reports another branch's tests as this package's.
