# Release process — dynamic/silverstripe-elemental-templates

This project follows the **Dynamic Release Framework**. The process itself —
lifecycle, labels, milestones, runbook — lives there and is not restated here.
This file holds only this repository's parameters and overrides. If a section
would restate a framework default, it says `framework default` instead.

| | |
| --- | --- |
| **Framework** | [dynamic/release-framework](https://github.com/dynamic/release-framework) `1.x` |
| **Tier** | active |
| **Platform** | GitHub |
| **Deploy** | none |

## Cadence

| Parameter | Value |
| --- | --- |
| Profile | on-demand |
| Freeze | set per Essentials platform release: 2026-11-10 (Essentials 3.4), 2027-01-12 (Essentials 4.0). Every platform member's milestone carries the same freeze date and a `Platform:` line |
| Review window | freeze + 7 days |
| Production | freeze + 9 days |

## Branches

| Parameter | Value |
| --- | --- |
| Release line | `3` |
| Work branches | framework default |

`2` is the SS5 line (security and critical fixes only).

## Deploy

None. Libraries and modules are distributed by tag; consumers install the tag with Composer.
A release is the tag and the GitHub Release (framework `docs/deploy/none.md`).

| Check | Command |
| --- | --- |
| Tag is on the remote | `git ls-remote --tags origin <tag>` |
| Release is published, not a draft | `gh release view <tag> --json tagName,isDraft` |

A bad release is corrected forward with a patch release; never delete or move a published tag.

## Review

| Parameter | Value |
| --- | --- |
| Tool | Claude Code `/review-pr` (pr-review-toolkit) after the PR is opened; the author runs a local review before pushing |
| Approvals | framework default |

Label deviation: these repositories keep the autobuild priority labels `priority/critical`, `priority/high`,
`priority/medium`, `priority/low` and `effort/*` instead of the framework's `priority/1` to `priority/3`
(proposed upstream in dynamic/release-framework#4). `check-adoption.sh` will report the numeric labels as missing.

## Post-deploy

None.

## Hazards

- Before tagging, read `git log <last tag>..HEAD`. A `feat` commit or a DB schema change makes the release a minor; a 3.0.7 patch tag was withdrawn and re-released as 3.1.0 for this reason.
- SilverStripe shares one extension instance per process: never store per-record state on an extension (`BaseElementDataExtension`); test with a write of another record afterwards in the same process.
- This repository is public: no client names or live domains in issues, PRs or commits.
- GitHub Actions is disabled on dynamic/* repositories. Run the module suite standalone (`vendor/bin/phpunit`) and `phpcs` before every push.
