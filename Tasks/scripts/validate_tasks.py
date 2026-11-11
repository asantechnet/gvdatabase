#!/usr/bin/env python3
"""Validate ERP Taskifier task folders. Uses only the Python standard library."""
from __future__ import annotations
import argparse
import re
import sys
from pathlib import Path
from collections import defaultdict

TASK_DIR_RE = re.compile(r"^(Task-(\d{3,}))(?:-[a-z0-9]+(?:-[a-z0-9]+)*)?$")
TASK_REF_RE = re.compile(r"Task-\d{3,}")
STATUS_SET = {"Backlog", "Ready", "In Progress", "Blocked", "Review", "Done", "Cancelled"}
TYPE_SET = {"Discovery", "Contract", "Data", "Backend", "API", "UI", "Frontend", "Full-stack", "Security", "Performance", "Integration", "Migration", "Test", "Documentation", "Operations", "DevOps", "Release", "Observability"}
PRIORITY_SET = {"P0", "P1", "P2", "P3"}
PLACEHOLDER_PATTERNS = [
    r"\[Task-ID\]", r"\[Outcome-oriented title\]", r"\[short action/outcome\]", r"\[one implementer/agent role\]",
    r"\[S \| M \| L; split L unless coordination makes it worse\]", r"\[path, section, plan hash/commit if available\]",
    r"\[REQ/BEH/RULE IDs\]", r"\[One independently reviewable outcome and its observable value\.?\]",
    r"\[Concrete in-scope behaviors\]", r"\[Explicit non-goals and neighboring work owned by other tasks\]",
    r"\[Why this task exists, current state, relevant system behavior and domain constraints\.?\]",
    r"\[owner \+ needed decision\]", r"\[Constraints and boundaries, not an over-prescribed implementation unless chosen by Architecture/Behaviors\.?\]",
    r"\[Approved prerequisite decisions, fixtures, environment, feature flags\]", r"\[Every assumption marked and assigned for validation\]",
    r"\[precondition\]", r"\[action\]", r"\[observable outcome\]", r"\[\.\.\.\]",
    r"\[first concrete action\]", r"\[YYYY-MM-DD(?: HH:MM timezone)?\]", r"\[reason\]", r"\[outcome\]",
    r"\[one action\]", r"\[exact artifact/decision needed\]", r"\[actual file/path\]",
    r"path/owned/by/this/task", r"\[reason\]"
]
TASK_HEADINGS = [
    "Metadata", "Objective", "Context and problem", "Scope", "Preconditions and assumptions",
    "Dependencies", "Contracts and boundaries", "Exclusive file ownership", "Acceptance criteria",
    "Verification plan", "Implementation notes", "Risks and rollback", "Handoff checklist",
]
PROGRESS_HEADINGS = [
    "Status snapshot", "Acceptance criteria progress", "Work completed with evidence",
    "Files changed and why", "Checks run and results", "Decisions and deviations",
    "Blockers and open questions", "Handoff notes", "Activity log (append-only)",
]

def section(text: str, heading: str) -> str:
    pat = re.compile(rf"(?ms)^##\s+{re.escape(heading)}\s*\n(.*?)(?=^##\s+|\Z)", re.IGNORECASE)
    m = pat.search(text)
    return m.group(1).strip() if m else ""

def value(text: str, key: str) -> str:
    m = re.search(rf"(?im)^\s*(?:[-*]\s*)?(?:\*\*)?{re.escape(key)}:(?:\*\*)?\s*(.*?)\s*$", text)
    return m.group(1).strip().strip('`') if m else ""

def markdown_files(root: Path):
    for p in root.rglob("*.md"):
        if any(part in {".git", "node_modules", ".venv"} for part in p.parts):
            continue
        yield p

def owned_paths(task_text: str):
    body = section(task_text, "Exclusive file ownership")
    paths = []
    for line in body.splitlines():
        s = line.strip()
        if not s.startswith(("- ", "* ")):
            continue
        s = s[2:].strip()
        if re.search(r"\bNone\b|discovery/decision task|do not edit product files", s, re.I):
            continue
        # Canonical contract uses a path in backticks, optionally followed by an em dash description.
        m = re.search(r"`([^`]+)`", s)
        candidate = m.group(1).strip() if m else s.split(" — ", 1)[0].split(" - ", 1)[0].strip()
        candidate = candidate.rstrip(";")
        if candidate and candidate.lower() not in {"none", "tbd", "unknown", "to be discovered"}:
            paths.append(candidate)
    return paths

def path_overlap(a: str, b: str) -> bool:
    def norm(x):
        x = x.replace("\\", "/").strip()
        x = re.sub(r"/\*\*$", "/", x)
        x = x.rstrip("/")
        return x
    a, b = norm(a), norm(b)
    return a == b or a.startswith(b + "/") or b.startswith(a + "/")

def main() -> int:
    parser = argparse.ArgumentParser(description="Validate Taskifier task contracts, dependencies and ownership.")
    parser.add_argument("task_root", nargs="?", default="docs/Tasks", help="Task set directory, relative to the project root (default: docs/Tasks)")
    args = parser.parse_args()
    root = Path(args.task_root).expanduser()
    # Repository-root-relative paths in the skill docs are sometimes written as
    # /docs/Tasks. Accept that form when the filesystem-root path does not exist.
    if not root.exists() and root.is_absolute() and Path(str(root).lstrip("/")).exists():
        root = Path(str(root).lstrip("/"))
    root = root.resolve()
    errors, warnings = [], []
    if not root.exists() or not root.is_dir():
        print(f"FAIL: task root does not exist or is not a directory: {root}")
        return 2

    index_path = root / "INDEX.md"
    if not index_path.is_file():
        errors.append("Missing required INDEX.md task-set registry.")
        index_text = ""
    else:
        index_text = index_path.read_text(errors="replace")

    tasks = {}
    for folder in sorted(root.iterdir()):
        if not folder.is_dir():
            continue
        m = TASK_DIR_RE.match(folder.name)
        if not m:
            # Utility/support dirs such as scripts are allowed; unrecognized Task-* names are not.
            if folder.name.startswith("Task-"):
                errors.append(f"Unrecognized task folder naming convention: {folder.name}")
            continue
        tid = m.group(1)
        if tid in tasks:
            errors.append(f"Duplicate Task ID {tid}: {tasks[tid]['folder'].name} and {folder.name}")
            continue
        task_file, progress_file = folder / "Task.md", folder / "Progress.md"
        if not task_file.is_file():
            errors.append(f"{folder.name}: missing Task.md")
            continue
        if not progress_file.is_file():
            errors.append(f"{folder.name}: missing Progress.md")
            continue
        task_text = task_file.read_text(errors="replace")
        progress_text = progress_file.read_text(errors="replace")
        tasks[tid] = {"folder": folder, "task": task_text, "progress": progress_text, "deps": set(), "owned": []}
        if value(task_text, "Task ID") != tid:
            errors.append(f"{folder.name}/Task.md: Task ID metadata must equal {tid}.")
        title = value(task_text, "Title")
        if not title:
            errors.append(f"{folder.name}/Task.md: missing Title metadata.")
        metadata = section(task_text, "Metadata")
        task_type = value(metadata, "Type").strip('` ')
        if task_type not in TYPE_SET:
            errors.append(f"{folder.name}/Task.md: Type must be one of {', '.join(sorted(TYPE_SET))}.")
        priority = value(metadata, "Priority").strip('` ')
        if priority not in PRIORITY_SET:
            errors.append(f"{folder.name}/Task.md: Priority must be P0, P1, P2 or P3.")
        owner_role = value(metadata, "Owner role").strip('` ')
        if not owner_role or owner_role.lower() in {"tbd", "unknown", "unassigned", "[one implementer/agent role]"}:
            errors.append(f"{folder.name}/Task.md: specify an implementer/agent Owner role.")
        size = value(metadata, "Estimated size").strip('` ').upper()
        if size not in {"S", "M", "L"}:
            errors.append(f"{folder.name}/Task.md: Estimated size must be S, M or L.")
        source_plan = value(metadata, "Source plan/version")
        if not source_plan or source_plan.lower() in {"tbd", "unknown"}:
            errors.append(f"{folder.name}/Task.md: record Source plan/version (a supplied user plan is a valid source).")
        for pattern in PLACEHOLDER_PATTERNS:
            if re.search(pattern, task_text, re.I):
                errors.append(f"{folder.name}/Task.md: unresolved template placeholder matches {pattern}.")
                break
        for heading in TASK_HEADINGS:
            if not section(task_text, heading):
                errors.append(f"{folder.name}/Task.md: missing or empty required section '## {heading}'.")
        for heading in PROGRESS_HEADINGS:
            if not section(progress_text, heading):
                errors.append(f"{folder.name}/Progress.md: missing or empty required section '## {heading}'.")
        # A section containing only field labels is still incomplete. Empty values must be explicit.
        for heading in ["Preconditions and assumptions", "Contracts and boundaries", "Verification plan", "Risks and rollback"]:
            body = section(task_text, heading)
            for line in body.splitlines():
                stripped = line.strip()
                if stripped.startswith(("- ", "* ")) and stripped.endswith(":"):
                    errors.append(f"{folder.name}/Task.md: fill the empty field '{stripped[2:]}' under '## {heading}' or write an explicit 'None / not applicable' with reason.")
        if re.search(r"(?im)^\s*[-*]\s*Or:\s*", section(task_text, "Exclusive file ownership")):
            errors.append(f"{folder.name}/Task.md: remove unused alternative from Exclusive file ownership; keep actual paths or one explicit None statement.")
        criteria = section(task_text, "Acceptance criteria")
        task_boxes = re.findall(r"(?m)^\s*- \[[ xX]\]\s*(.*?)\s*$", criteria)
        if not task_boxes:
            errors.append(f"{folder.name}/Task.md: add at least one checkbox acceptance criterion.")
        progress_criteria = section(progress_text, "Acceptance criteria progress")
        progress_boxes = re.findall(r"(?m)^\s*- \[[ xX]\]\s*(.*?)\s*$", progress_criteria)
        if len(progress_boxes) != len(task_boxes):
            errors.append(f"{folder.name}/Progress.md: progress checklist has {len(progress_boxes)} criteria; Task.md defines {len(task_boxes)}. Mirror every criterion one-to-one.")
        else:
            for idx, criterion in enumerate(task_boxes):
                normalized = criterion.strip().strip("`")
                observed = progress_boxes[idx].strip()
                # Progress entries may append an evidence note after an em dash.
                observed_core = observed.split(" — Evidence:", 1)[0].strip().strip("`")
                if normalized != observed_core:
                    errors.append(f"{folder.name}/Progress.md: criterion {idx+1} does not match Task.md verbatim.")
        status = value(section(progress_text, "Status snapshot"), "Status")
        status = status.strip('` ')
        if status not in STATUS_SET:
            errors.append(f"{folder.name}/Progress.md: invalid or missing Status '{status}'. Allowed: {', '.join(sorted(STATUS_SET))}.")
        snapshot = section(progress_text, "Status snapshot")
        for field in ["Owner", "Last updated", "Current focus", "Blocker", "Next action"]:
            if not value(snapshot, field):
                errors.append(f"{folder.name}/Progress.md: missing {field} in Status snapshot.")
        last_updated = value(snapshot, "Last updated")
        if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", last_updated):
            errors.append(f"{folder.name}/Progress.md: Last updated must be a real YYYY-MM-DD date, not a placeholder.")
        for pattern in PLACEHOLDER_PATTERNS:
            if re.search(pattern, progress_text, re.I):
                errors.append(f"{folder.name}/Progress.md: unresolved template placeholder matches {pattern}.")
                break
        owner = value(snapshot, "Owner").lower().strip('` ')
        focus = value(snapshot, "Current focus").lower().strip('` ')
        next_action = value(snapshot, "Next action").lower().strip('` ')
        if status in {"In Progress", "Review", "Done"} and owner in {"unassigned", "tbd", "unknown", "none", ""}:
            errors.append(f"{folder.name}/Progress.md: status {status} requires a named owner or agent role.")
        if status == "In Progress" and focus in {"not started", "tbd", "unknown", "none", ""}:
            errors.append(f"{folder.name}/Progress.md: In Progress status requires a concrete Current focus.")
        if next_action in {"tbd", "unknown", "none", "n/a", ""}:
            errors.append(f"{folder.name}/Progress.md: Next action must be concrete, not empty/TBD/None.")
        if status == "Blocked":
            blocker = value(section(progress_text, "Status snapshot"), "Blocker").lower()
            if not blocker or blocker in {"none", "n/a", "-", "`none`"}:
                errors.append(f"{folder.name}: Status is Blocked but Blocker is empty/None.")
        if status == "Done":
            prog_criteria = section(progress_text, "Acceptance criteria progress")
            boxes = re.findall(r"(?m)^\s*- \[([ xX])\]", prog_criteria)
            if not boxes or any(mark.lower() != "x" for mark in boxes):
                errors.append(f"{folder.name}: Done status requires every progress acceptance criterion to be checked.")
            checks = section(progress_text, "Checks run and results")
            work = section(progress_text, "Work completed with evidence")
            criteria_text = section(progress_text, "Acceptance criteria progress")
            default_markers = ("not run yet", "not yet available", "none yet", "no evidence recorded")
            if any(marker in (checks + "\n" + work + "\n" + criteria_text).lower() for marker in default_markers):
                errors.append(f"{folder.name}: Done status still contains uncompleted template/evidence placeholders.")
        deps_text = section(task_text, "Dependencies")
        if not deps_text.strip() or (not TASK_REF_RE.search(deps_text) and not re.search(r"\bNone\b|external blocker", deps_text, re.I)):
            errors.append(f"{folder.name}/Task.md: Dependencies must list valid Task IDs or explicitly state None / an external blocker.")
        refs = set(TASK_REF_RE.findall(deps_text))
        refs.discard(tid)
        tasks[tid]["deps"] = refs
        owned = owned_paths(task_text)
        tasks[tid]["owned"] = owned
        for owned_path in owned:
            if owned_path.startswith("/") or ".." in Path(owned_path).parts:
                errors.append(f"{folder.name}: ownership path must be repository-relative and must not traverse: {owned_path}")
            if any(char in owned_path for char in "*?["):
                errors.append(f"{folder.name}: ownership path '{owned_path}' uses a wildcard; use concrete files or an explicit directory path so overlap checks stay reliable.")

    if not tasks:
        errors.append("No task folders found; create at least one task before validating an implementation task set.")

    # Dependencies must resolve to tasks in this set.
    for tid, info in tasks.items():
        for dep in info["deps"]:
            if dep not in tasks:
                errors.append(f"{tid}: dependency {dep} does not match an existing task folder.")
        if tid in info["deps"]:
            errors.append(f"{tid}: task cannot depend on itself.")

    # Detect cycles with a depth-first traversal.
    graph = {tid: {d for d in info["deps"] if d in tasks} for tid, info in tasks.items()}
    visiting, visited, stack = set(), set(), []
    def visit(node):
        if node in visiting:
            cycle_start = stack.index(node) if node in stack else 0
            cycle = stack[cycle_start:] + [node]
            errors.append("Dependency cycle: " + " -> ".join(cycle))
            return
        if node in visited:
            return
        visiting.add(node); stack.append(node)
        for dep in sorted(graph.get(node, set())):
            visit(dep)
        stack.pop(); visiting.remove(node); visited.add(node)
    for tid in sorted(graph):
        visit(tid)

    # Exclusive ownership: exact and parent/child path overlaps cannot cross task boundaries.
    claims = []
    for tid, info in tasks.items():
        for owned in info["owned"]:
            claims.append((owned, tid))
    for i, (path_a, id_a) in enumerate(claims):
        for path_b, id_b in claims[i+1:]:
            if id_a != id_b and path_overlap(path_a, path_b):
                errors.append(f"Exclusive ownership overlap: {id_a} owns '{path_a}' and {id_b} owns '{path_b}'. Assign one owner or split/change boundaries.")

    # Registry rows are read only from the Tasks table, so references in dependency layers
    # and validation history do not count as duplicate task entries.
    if index_path.is_file():
        table = section(index_text, "Tasks")
        rows = {}
        for line in table.splitlines():
            if not line.strip().startswith("|"):
                continue
            cells = [cell.strip().strip("`") for cell in line.strip().strip("|").split("|")]
            if not cells or not re.fullmatch(r"Task-\d{3,}", cells[0]):
                continue
            tid = cells[0]
            if tid in rows:
                errors.append(f"INDEX.md lists {tid} more than once in the Tasks table.")
            rows[tid] = cells
        for tid, info in tasks.items():
            if tid not in rows:
                errors.append(f"INDEX.md Tasks table does not list {tid}.")
                continue
            cells = rows[tid]
            expected_folder = info["folder"].name + "/"
            if len(cells) >= 2 and cells[1].rstrip("/") != info["folder"].name:
                errors.append(f"INDEX.md folder cell for {tid} does not match actual folder {expected_folder}.")
            if len(cells) >= 3 and (not cells[2] or re.search(r"\[.*\]", cells[2])):
                errors.append(f"INDEX.md outcome cell for {tid} is empty or still a placeholder.")
            if len(cells) >= 6:
                owner_cell = cells[5]
                owned_paths_for_task = info["owned"]
                if owned_paths_for_task and not all(path in owner_cell for path in owned_paths_for_task):
                    errors.append(f"INDEX.md owner/file-area cell for {tid} does not list all exclusive owned paths.")
                if not owned_paths_for_task and not re.search(r"\bNone\b|discovery|no product files", owner_cell, re.I):
                    errors.append(f"INDEX.md owner/file-area cell for {tid} must state the discovery/no-file ownership boundary.")
            if len(cells) >= 4:
                index_status = cells[3].strip().strip("`")
                progress_status = value(section(info["progress"], "Status snapshot"), "Status").strip("` ")
                if index_status and index_status != progress_status:
                    errors.append(f"INDEX.md status for {tid} is '{index_status}' but Progress.md says '{progress_status}'.")
            if len(cells) >= 5:
                index_deps = set(TASK_REF_RE.findall(cells[4]))
                task_deps = info["deps"]
                if index_deps != task_deps:
                    errors.append(f"INDEX.md dependency cell for {tid} does not match Task.md Dependencies section.")
        for tid in rows:
            if tid not in tasks:
                errors.append(f"INDEX.md Tasks table references missing task {tid}.")

    if warnings:
        for w in warnings:
            print("WARN:", w)
    if errors:
        print(f"FAIL: {len(errors)} error(s), {len(tasks)} task(s) checked.")
        for i, e in enumerate(errors, 1):
            print(f"{i}. {e}")
        return 1
    print(f"PASS: {len(tasks)} task(s); required files/sections, statuses, dependencies, cycles, exclusive ownership and INDEX.md are valid.")
    print("Note: this structural validator cannot prove that business logic or acceptance criteria are correct; a human/agent review is still required.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
