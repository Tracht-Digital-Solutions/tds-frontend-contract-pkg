# Release and versioning

Dual package: npm (GitHub Packages) and Composer (resolved by Git tag).

| Workflow | Trigger | Result |
|---|---|---|
| `ci.yml` | pull request | build and test |
| `dev.yml` | push to `main` | prerelease under the `dev` dist-tag |
| `release.yml` | manual button | `npm version <bump>`, writes `composer.json` to match, commits, annotated tag, publish |

- **Don't hand-bump.** The workflow computes from what is committed; a hand bump skips a version.
  Choose the bump on the button.
- Hand edits are only for reconciling drift, checked against `npm view … versions`. If the field
  falls **behind** the registry, a patch release is a guaranteed 409.
- `package.json` and `composer.json` versions are one release and move together.
- **Stable at 1.x, additive minors only.** Consumers pin `^1.x`. An optional capability is a minor;
  a new method on an existing interface is not.
- After a contract minor, consumers that need it must repin; a site can be briefly red if a
  dependent release is dispatched before its own commit lands.
- CI installs with `npm install --no-package-lock`.
