# Verified system behavior

This directory is the maintained reference for implemented behavior. Read the relevant facts document before changing a feature, then inspect its linked source files. Historical plans describe decisions and work performed at a particular time; they do not override these facts.

| Feature | Reference | Coverage |
| --- | --- | --- |
| Testing workflow and performance | [testing.md](testing.md) | Test database configuration, bulk authorization seeding, measured setup cost, commands, parallel/isolated runs, guardrails and troubleshooting |
| PWA and release updates | [pwa.md](pwa.md) | Installation, branding, release identity, update checks, consent, caches, offline behavior, manual deployment, troubleshooting and regression checks |
| Background polling and error feedback | [background-polling.md](background-polling.md) | Active-tab scheduling, quiet API reads, auth retries, deferred heartbeat notices and regression procedure |
| RTL direction and the always-LTR header | [rtl-direction.md](rtl-direction.md) | Locale-driven document direction, header direction override, scoped-style `:global()` mirroring pitfall, browser diagnosis and prevention checks |

PWA, background polling, RTL direction and testing behavior have been consolidated here so far. The absence of a facts file for another feature does not make its older plan current.

## Maintenance rules

1. Read [project instructions](../../AGENTS.md), this index and the relevant facts file before implementation.
2. Trace the implementation and add the task's plan/progress to [docs/ai](../ai/README.md).
3. Update code, meaningful regression tests and the facts file together when behavior changes. Record validation limitations explicitly.
4. Keep deployment instructions consistent with the actual manual deployment process. Never hand-edit generated build/worker/version/icon output.
5. If code and facts disagree, identify the mismatch explicitly and reconcile it; do not silently restore behavior from an old plan.

The source code is executable evidence. These documents are the human-readable behavior contract, not a substitute for testing. Each facts file must link its implementation owners and describe known limits.
