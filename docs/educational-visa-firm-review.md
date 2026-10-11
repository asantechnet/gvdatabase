# Educational visa firm implementation review

Date: 2026-10-09

## Graph and architecture
Graphify first rebuilt the allowed code corpus before review: 12,058 nodes, 27,191 edges and 417 communities. The post-implementation refresh produced **12,149 nodes, 27,299 edges and 439 communities**. The interactive graph uses an aggregated community view because the full graph exceeds 5,000 nodes. See `graphify-out/graph.html` and `graphify-out/GRAPH_REPORT.md` for generated relationships and raw cohesion scores.

Structural extraction used no semantic LLM calls (input/output extraction tokens: 0/0). Existing semantic graph information was retained; no claim is made that all documentation was semantically re-extracted. Denied directories, vendor/build output and sensitive configuration were excluded. Graphify's initial multigraph diagnostic found no dangling endpoints or missing IDs; 116 self-loops were reported and remain a graph-analysis caveat.

| Concern | Reviewed implementation boundaries | Result |
| --- | --- | --- |
| Applicant identity and CRM | Applicant/Visitor controllers and models, typed applicant/visitor APIs, CRM relationship components | Family contacts and optional specialist assignments accompany shared applicant identities. Case ownership remains independent. |
| Service contracts | ContractController service options, StoreServiceContractRequest, ContractGenerationService, service-contracts.vue | Route determines service/template; exact country/language active current version determines issuance. Existing snapshots remain immutable. |
| Authorization and routing | Permission/role seeders, API middleware, static router, horizontal navigation | Management overview/report permissions apply at the UI and backend. Home opens overview; old pipeline URLs open the merged applicant list. |
| Printing | printDocument.ts, PrintableListReport.vue, contract/cover/payslip/timesheet templates | Branded scope-specific body-level print hosts avoid layout shell contamination; per-job page rules and cleanup isolate A4/A5 jobs. |
| Templates and clauses | ContractPolicy model/controller/resource/API, policies.vue, policy_library.vue, ContractContentEditor.vue | Shared scope filters, version management before content, corrected toolbar stacking and logical list/quote spacing. |
| Placeholder vocabulary | PlaceholderRegistry, PlaceholderResolver, editor/API groups, locale dictionaries | Current applicant/service vocabulary is offered; old applicant aliases remain resolvable for legacy drafts. |
| Marketer retirement | Forward migration, controllers/resources/models, navigation/API helpers, seeders | Operational marketer concepts and schema are removed; employee identities and issued snapshots remain. |

## Verification and rollout
See `educational-visa-firm-coherence-plan.md` for the complete validation results, environment limitations and migration rollout notes. The two migrations have been tested with SQLite; they have not been applied to the live database. Visual print quality and production compilation still require verification in an environment with a functioning browser and Linux build dependencies.
