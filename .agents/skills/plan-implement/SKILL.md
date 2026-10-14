---
name: plan-implement
description: Use when the user includes the `plan-implement` flag in a task or explicitly invokes this skill. Investigate the project, trace relevant dependencies and data flows using available graph skills or equivalent tools, clarify material conflicts or ambiguities with the user, write a complete plan and ongoing progress log in a task-named Markdown record under docs/ai, then implement and validate the task unless planning-only was requested.
---

# Plan-Implement

## Purpose

Treat `plan-implement` as a request to use practical judgment, engineering experience, and creativity to deliver the specified outcome—not merely to describe how it could be done. Review the actual project before deciding how to implement the task. Ask the user to resolve material contradictions or ambiguities rather than silently choosing an interpretation. Once the intent is clear, write the implementation plan to disk, carry it out, and keep that same record updated with progress and validation results.

Creativity should improve the solution while remaining faithful to the user's intent, established project conventions, and the requested scope. The flag does not authorize unrelated refactors, unrequested feature expansion, data loss, destructive operations, or significant external side effects.

## When to use

Use this skill whenever the user:

- Includes `plan-implement` as a flag in a task; or
- Explicitly asks to use the Plan-Implement workflow.

If the user explicitly requests **planning only**, perform the review, clarification, and documentation steps, then stop before implementing. Do not impose this workflow on unrelated tasks when the flag or explicit invocation is absent.

## Mandatory workflow

### 1. Understand the task and protect existing work

1. Extract the requested outcome, constraints, acceptance criteria, non-goals, and any explicit implementation preferences.
2. Read project-level and relevant directory instructions first, including `AGENTS.md`, contributor guidance, architecture notes, and task-specific instructions.
3. Inspect the repository state and existing changes before editing. Preserve unrelated or pre-existing user changes; never overwrite or revert them without authorization.
4. Identify relevant languages, frameworks, dependencies, build/test commands, configuration, and conventions.
5. If the task is outside a software repository, adapt this workflow to the real project context where meaningful rather than pretending a codebase exists.

### 2. Review the project in depth

Review the relevant project areas before settling on a design. Inspect as appropriate:

- Repository structure and the modules/components/services involved.
- Existing behavior, nearby implementations, call sites, public interfaces, schemas, and data contracts.
- Dependency manifests, configuration, environment assumptions, build/lint/type-check/test commands, and CI workflows.
- Error handling, logging, security and permission boundaries, persistence, state transitions, edge cases, and relevant tests.
- Documentation and design decisions that constrain the implementation.
- Git/worktree status and changes that could be affected by the task.

In this repository, remember that frontend and backend are separate concerns joined by a REST API, that Axios/Laravel double-wrap responses at `response.data.data`, that API contracts live under `resources/ts/api`, and that the static router under `resources/ts/plugins/1.router` is authoritative. Run `npm run typecheck` rather than bare `vue-tsc`/`tsc`, and never hand-edit generated icon CSS.

#### Graph and impact analysis

Use available graph-oriented skills and tools for fine-grained review. Prefer the `graphify` skill (and any `graphify-out/` artifacts) when present to trace dependencies, call/reference graphs, data flows, schema/entity relationships, and impact. Follow dependencies far enough to identify callers, shared abstractions, integration points, downstream effects, and tests likely to need updates. Use graph findings to inform the implementation plan; do not simply claim that a graph review occurred.

If no relevant graph skill/tool is available, use the strongest available fallback—such as repository search, symbol/reference lookup, static analysis, tests, or manually traced call/data flows. Clearly record which approach was actually used and any limitations. Never claim to have run a tool or examined a relationship that was not examined.

### 3. Apply the clarification gate

Compare the task requirements with one another and with actual project behavior and constraints. Look for:

- Contradictions between requirements or acceptance criteria.
- Ambiguous behavior with materially different plausible interpretations.
- Missing decisions that cannot safely be inferred from code, documentation, or established conventions.
- Incompatibilities with architecture, public interfaces, backward compatibility, security, privacy, or existing user-visible behavior.
- Scope conflicts or requirements that cannot all be satisfied as written.

First resolve discoverable questions through project review; do not ask the user something the repository can answer reliably. If a material conflict or ambiguity remains, **pause before finalizing the plan and before implementing the affected behavior**. Use the `ask_user` tool, asking one focused question at a time where practical, and explain the competing interpretations and what decision is needed. Do not silently guess, disguise uncertainty as a decision, or continue with a consequential assumption.

While waiting, document known findings and the open question in the applicable plan record with status `WAITING FOR CLARIFICATION`. This is a discovery note, not a finalized plan. Resume after the user answers, record the decision, and then complete the plan. Repeat the gate if new material ambiguity appears during implementation.

Low-risk details may be inferred from clear repository conventions, but record material assumptions and their rationale. Do not interrupt the user for trivial implementation details that can be safely decided from established conventions.

### 4. Create the plan and progress record

Do this **after the material questions have been resolved and before implementation starts**. Ensure `docs/ai/` exists, creating it if needed and appropriate for the repository.

#### Storage rule: one task-named Markdown file per task

Name the plan file after the specific task, never after the skill:

`docs/ai/<task-slug>.md`

- Derive `<task-slug>` from the actual task: a concise, descriptive kebab-case name (for example `add-customer-list-pagination.md` or `merge-applications-nav-per-service-stages.md`). Reuse the same slug as the task identifier where practical.
- Create one file for the task and keep its plan, progress log, decisions, blockers, and validation results together inside it.
- When resuming a task, reopen and update its existing task-named record instead of creating a duplicate.
- Do not name the file with a generic title such as `plan-implement.md`; a reader must be able to tell what the task was from the filename alone.
- Do not create a separate Markdown file for the plan and another for progress.
- Preserve other tasks' records; do not overwrite or erase them.

Use a separate folder for a plan **only if the user explicitly requests it or a clear existing project convention requires isolated plan folders**. In that case, use:

`docs/ai/<task-slug>/plan.md`

Keep both the plan and its progress log inside that single `plan.md`. Supporting files may be placed in the same task folder only when actually needed. Do not silently switch to per-task folders merely because it feels tidier.

Before writing, inspect the existing `docs/ai/` records and follow the storage rule above. Name the file after the specific task with a concise, descriptive kebab-case slug, and avoid duplicating an existing task record.

#### Required content for each task record

Tailor the following sections to the task. Make them concrete and useful; omit irrelevant details rather than filling them with generic boilerplate.

1. **Task and status** — title, task identifier/slug, created/updated dates, status, and intended outcome.
2. **Requirements and acceptance criteria** — observable conditions that define success.
3. **Scope and non-goals** — planned changes and explicitly excluded work.
4. **Project findings** — current behavior, architecture, conventions, constraints, and relevant paths/files.
5. **Graph and impact review** — relationships/tools actually investigated, affected callers and integrations, likely downstream impact, and limitations.
6. **Clarifications, decisions, and assumptions** — the user's answers, decisions made, alternatives considered when useful, and rationale for any low-risk assumptions.
7. **Implementation plan** — ordered, actionable checkboxes with likely files/components, purpose, dependencies, and non-obvious design rationale.
8. **Validation plan** — targeted tests, regression checks, lint/type/build commands, security/compatibility considerations, and manual checks where appropriate.
9. **Risks and mitigations** — edge cases, migration or compatibility concerns, failure modes, and rollback considerations when relevant.
10. **Progress log** — dated entries for findings, decisions, completed steps, tests, blockers, deviations, and remaining work.

Use the following built-in template directly in this skill as the starting shape for a new task record. Copy and adapt it into the task's record file; do not create a separate template file just to use this skill.

```markdown
## Task: <clear task title>

- **Task ID:** <stable-id-or-slug>
- **Created:** YYYY-MM-DD
- **Last updated:** YYYY-MM-DD
- **Status:** DISCOVERY | WAITING FOR CLARIFICATION | PLANNED | IN PROGRESS | BLOCKED | PARTIALLY COMPLETED | COMPLETED
- **Outcome:** <what this task is expected to achieve>

### 1. Requirements and acceptance criteria
- [ ] <observable acceptance criterion>

### 2. Scope and non-goals
- In scope: <changes included>
- Out of scope: <changes excluded>

### 3. Project findings
- Current behavior:
- Relevant architecture and conventions:
- Relevant paths/files:
- Constraints:

### 4. Graph and impact review
- Graph skills/tools or fallback methods actually used:
- Dependencies, callers, data flows, schemas, and integrations examined:
- Potential downstream effects and affected tests:
- Tool/review limitations:

### 5. Clarifications, decisions, and assumptions
- Open questions:
- User decisions:
- Assumptions and rationale:
- Alternatives considered (where useful):

### 6. Implementation plan
- [ ] Step 1 — <action, affected area, and purpose>
- [ ] Step 2 — <action, affected area, and purpose>
- [ ] Step 3 — <action, affected area, and purpose>

### 7. Validation plan and results
- [ ] <test/check> — planned command or method:
- Results:
- Checks not run and why:

### 8. Risks and mitigations
- <risk> — mitigation: <action>

### 9. Progress log
- YYYY-MM-DD — DISCOVERY: <findings and review performed>
- YYYY-MM-DD — PLAN: <plan finalized and saved before implementation>
- YYYY-MM-DD — PROGRESS: <completed work, decisions, or changed approach>
- YYYY-MM-DD — VALIDATION: <exact command/check and actual outcome>

### 10. Completion summary
- Delivered:
- Acceptance criteria satisfied/not satisfied:
- Tests/checks passed:
- Remaining limitations or follow-up work:
```

Use dates in the project's/local working context. Update the task status and `Last updated` date whenever meaningful progress is made. Mark the status `IN PROGRESS` when implementation begins.

### 5. Implement the planned solution

1. Save the resolved plan first, then implement it unless planning-only was requested or another approval gate applies.
2. The flag requests planning **and** implementation. Do not ask for an extra approval merely to begin ordinary, in-scope work after the plan is saved.
3. Use practical judgment and creativity to choose an effective, maintainable solution consistent with project conventions and the user's intent.
4. Prefer the smallest complete and reliable solution over unrelated refactors, unnecessary abstractions, or new dependencies.
5. Apply graph/impact findings to relevant callers, shared contracts, schemas, tests, documentation, configuration, and integration points.
6. Update the same plan section/file at meaningful milestones: completed steps, new discoveries, decisions, plan changes, blockers, and test outcomes. Do not wait until the end to reconstruct progress from memory.
7. If new information exposes a material ambiguity or contradiction, pause the affected work, log the issue, and ask the user.
8. Obtain explicit approval before destructive or difficult-to-reverse actions, broad scope expansion, or significant external side effects not clearly authorized by the user. Follow all higher-priority safety and security requirements.
9. Preserve unrelated working-tree changes and avoid including them in the task's edits.

### 6. Validate and review

- Run relevant focused tests first, then broader checks where feasible.
- Run the project's applicable linting, type checks, builds, and regression tests when relevant and available (for example `npm run typecheck` here).
- Review the final diff for accidental edits, scope creep, secret exposure, compatibility issues, and consistency with the plan.
- Evaluate each acceptance criterion individually.
- Record exact commands and actual outcomes in the same task progress log. Distinguish passing, failing, skipped, and environment-blocked checks.
- If a test cannot run, explain why and describe the remaining risk. Use alternative verification only when appropriate, and label it accurately.
- Mark the task `COMPLETED` only when the implementation and required validation are complete. Otherwise use `BLOCKED` or `PARTIALLY COMPLETED` and list the remaining work.

### 7. Report back to the user

Provide a concise, useful completion report with:

- The implemented outcome and key design choices.
- The exact plan/progress record path.
- Tests and validation commands run, with their real outcomes.
- Material deviations from the plan, unrun checks, unresolved limitations, or follow-up decisions.

Never claim that code, relationships, tests, or tools were reviewed or executed when they were not. Be explicit about failures and incomplete work.

## Non-negotiable rules

- **Investigate before designing:** base the solution on the actual project.
- **Use graph analysis meaningfully:** use available graph skills/tools, or an honest documented fallback.
- **Clarify material ambiguity:** do not silently decide between materially different interpretations.
- **Save the full plan before implementation:** except while waiting for the user's clarification, where only discovery notes and the open question should be recorded.
- **One task-named record per task:** name the record after the specific task (`docs/ai/<task-slug>.md`), never a generic `plan-implement.md`; use a per-plan folder only when explicitly requested or required by an established project convention.
- **Keep plan and progress together:** never split a task's plan and progress log across Markdown files.
- **Maintain the record while working:** log material progress and validation as it happens.
- **Do not overreach:** creativity serves the requested outcome and is not permission for unrelated changes.
- **Preserve existing work:** inspect status and protect unrelated modifications.
- **Validate honestly:** report exact outcomes and limitations.
- **Respect user intent:** follow later clarifications and explicit constraints, subject to higher-priority instructions.

## Examples

### Clear request

User: `plan-implement: Add pagination to the customer list.`

Expected behavior: inspect the repository and relevant call/data-flow relationships; resolve the approach from project conventions if clear; create the task's record at a task-named path such as `docs/ai/add-customer-list-pagination.md`; save the full plan; implement; run relevant validation; and update the same file throughout.

### Conflicting request

User: `plan-implement: Store uploaded files permanently, but delete every uploaded file after each session.`

Expected behavior: inspect the current storage and session model; record useful findings and the unresolved conflict in the task's plan record with status `WAITING FOR CLARIFICATION`; ask which retention behavior is intended; do not finalize the plan or implement the conflicting behavior until the user answers.

### Planning only

User: `plan-implement: Design a migration to the new API. Planning only; do not change code.`

Expected behavior: inspect the project, resolve material questions, write the full plan and validation strategy in the task-named record, and stop before implementation.

### Graph tools unavailable

Expected behavior: trace dependencies and call sites using repository search, symbol references, static analysis, or tests; document the fallback and its limitations rather than implying specialized graph tools were used.
