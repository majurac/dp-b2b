# Docs Refactor — Documentation Zones & PHP 8.3 Normalization

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganize docs into explicit Active/Frozen/Historical/Operational zones, normalize PHP version references to 8.3+, and create a current-phase tracking file to reduce AI context drift across sessions.

**Architecture:** Introduce 4 new subdirectories under `docs/`. Move existing files to appropriate zones. Retire the `docs/tasks/` folder by redistributing its contents. Archive executed superpowers plans. Update `docs/index.md` as the single navigation source of truth.

**Tech Stack:** Markdown files, PowerShell file operations, git.

---

## File Map — Before → After

| Current Path | New Path | Zone |
|---|---|---|
| `docs/tasks/checkout-logic.md` | `docs/frozen/checkout-logic.md` | Frozen |
| `docs/tasks/quick-order-sync-architecture.md` | `docs/frozen/quick-order-sync-architecture.md` | Frozen |
| `docs/tasks/synthetic-b2b-catalog.md` | `docs/historical/synthetic-b2b-catalog.md` | Historical |
| `docs/handoff.md` | `docs/historical/handoff-2026-04.md` | Historical |
| `docs/staging-quick-order-checklist.md` | `docs/operational/staging-quick-order-checklist.md` | Operational |
| `docs/status.md` | `docs/active/status.md` | Active |
| `docs/superpowers/plans/2026-04-28-stability-performance.md` | `docs/superpowers/plans/historical/2026-04-28-stability-performance.md` | Plan archive |
| `docs/superpowers/plans/2026-05-11-cart-sync-robustness.md` | `docs/superpowers/plans/historical/2026-05-11-cart-sync-robustness.md` | Plan archive |
| `docs/superpowers/plans/2026-05-11-quick-order-ux-validation.md` | `docs/superpowers/plans/historical/2026-05-11-quick-order-ux-validation.md` | Plan archive |
| `docs/superpowers/plans/2026-05-13-quick-order-ugly-dataset.md` | `docs/superpowers/plans/historical/2026-05-13-quick-order-ugly-dataset.md` | Plan archive |
| `docs/superpowers/plans/2026-05-13-woof-filter-compat.md` | `docs/superpowers/plans/historical/2026-05-13-woof-filter-compat.md` | Plan archive |
| `docs/superpowers/specs/2026-05-13-quick-order-ugly-dataset-design.md` | `docs/superpowers/specs/historical/2026-05-13-quick-order-ugly-dataset-design.md` | Spec archive |

**Files staying in place:**
- `docs/index.md` — root nav, will be rewritten
- `docs/dev-context.md` — canonical technical reference, stays
- `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md` — PENDING execution, stays active

**New files to create:**
- `docs/active/current-phase.md`

**Note on CLAUDE.md density:** The project-level `CLAUDE.md` is already lean and well-structured. This plan does NOT restructure it — only Task 9 Step 3 updates stale path references caused by file moves.

---

### Task 1: Create new directory structure

**Files:**
- Create directories: `docs/active/`, `docs/frozen/`, `docs/historical/`, `docs/operational/`, `docs/superpowers/plans/historical/`, `docs/superpowers/specs/historical/`

- [ ] **Step 1: Create all new directories**

Run in PowerShell (from theme root `C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b`):

```powershell
New-Item -ItemType Directory -Force "docs\active"
New-Item -ItemType Directory -Force "docs\frozen"
New-Item -ItemType Directory -Force "docs\historical"
New-Item -ItemType Directory -Force "docs\operational"
New-Item -ItemType Directory -Force "docs\superpowers\plans\historical"
New-Item -ItemType Directory -Force "docs\superpowers\specs\historical"
```

- [ ] **Step 2: Verify directories exist**

```powershell
Get-ChildItem docs -Directory -Recurse | Select-Object -ExpandProperty FullName
```

Expected: `docs\active`, `docs\frozen`, `docs\historical`, `docs\operational`, `docs\superpowers\plans\historical`, `docs\superpowers\specs\historical` all present.

---

### Task 2: Move frozen docs and add FROZEN headers

**Files:**
- Move: `docs/tasks/checkout-logic.md` → `docs/frozen/checkout-logic.md`
- Move: `docs/tasks/quick-order-sync-architecture.md` → `docs/frozen/quick-order-sync-architecture.md`
- Edit: both moved files — add FROZEN status block

- [ ] **Step 1: Move checkout-logic.md**

```powershell
Move-Item "docs\tasks\checkout-logic.md" "docs\frozen\checkout-logic.md"
```

- [ ] **Step 2: Add FROZEN header to docs/frozen/checkout-logic.md**

Read the file first. Insert these lines immediately after the first `# Checkout Logic` heading line:

```markdown

> **Status: FROZEN — DO NOT REFACTOR WITHOUT EXPLICIT APPROVAL**
> System is production-stable. Touches checkout flow, billing data protection, and WooCommerce Blocks compatibility.
> Read this doc fully before making ANY change to `inc/checkout-logic.php`.

```

- [ ] **Step 3: Move quick-order-sync-architecture.md**

```powershell
Move-Item "docs\tasks\quick-order-sync-architecture.md" "docs\frozen\quick-order-sync-architecture.md"
```

- [ ] **Step 4: Add FROZEN header to docs/frozen/quick-order-sync-architecture.md**

Read the file first. Insert these lines after the first `# Quick Order — Cart Sync Architecture` heading:

```markdown

> **Status: FROZEN — DO NOT REFACTOR WITHOUT EXPLICIT APPROVAL**
> CartSync engine architecture is locked. Debounce strategy, token model, and variation replace flow are intentional V1 decisions.
> Future changes require a new plan and explicit approval. See `docs/active/current-phase.md` for current work scope.

```

- [ ] **Step 5: Verify docs/tasks/ contains only synthetic-b2b-catalog.md**

```powershell
Get-ChildItem "docs\tasks"
```

Expected: only `synthetic-b2b-catalog.md` remains (moves in Task 3).

---

### Task 3: Move historical docs and retire docs/tasks/

**Files:**
- Move: `docs/tasks/synthetic-b2b-catalog.md` → `docs/historical/synthetic-b2b-catalog.md`
- Move: `docs/handoff.md` → `docs/historical/handoff-2026-04.md`
- Delete: `docs/tasks/` directory (now empty)

- [ ] **Step 1: Move synthetic-b2b-catalog.md**

```powershell
Move-Item "docs\tasks\synthetic-b2b-catalog.md" "docs\historical\synthetic-b2b-catalog.md"
```

- [ ] **Step 2: Move handoff.md**

```powershell
Move-Item "docs\handoff.md" "docs\historical\handoff-2026-04.md"
```

- [ ] **Step 3: Remove the now-empty docs/tasks/ directory**

```powershell
Remove-Item "docs\tasks" -Recurse -Force
```

- [ ] **Step 4: Verify tasks/ is gone**

```powershell
Test-Path "docs\tasks"
```

Expected: `False`

---

### Task 4: Archive historical superpowers plans and specs

**Files:**
- Move 5 executed plans: `docs/superpowers/plans/*.md` (all except v1-1) → `docs/superpowers/plans/historical/`
- Move 1 spec: `docs/superpowers/specs/*.md` → `docs/superpowers/specs/historical/`
- Keep: `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md` (still pending)

- [ ] **Step 1: Move 5 executed plans to historical/**

```powershell
Move-Item "docs\superpowers\plans\2026-04-28-stability-performance.md" "docs\superpowers\plans\historical\"
Move-Item "docs\superpowers\plans\2026-05-11-cart-sync-robustness.md" "docs\superpowers\plans\historical\"
Move-Item "docs\superpowers\plans\2026-05-11-quick-order-ux-validation.md" "docs\superpowers\plans\historical\"
Move-Item "docs\superpowers\plans\2026-05-13-quick-order-ugly-dataset.md" "docs\superpowers\plans\historical\"
Move-Item "docs\superpowers\plans\2026-05-13-woof-filter-compat.md" "docs\superpowers\plans\historical\"
```

- [ ] **Step 2: Move spec to historical/**

```powershell
Move-Item "docs\superpowers\specs\2026-05-13-quick-order-ugly-dataset-design.md" "docs\superpowers\specs\historical\"
```

- [ ] **Step 3: Verify active plans folder contains only the pending plan**

```powershell
Get-ChildItem "docs\superpowers\plans" -File | Select-Object Name
```

Expected: only `2026-05-22-docs-refactor.md` (this plan) and `2026-05-12-quick-order-v1-1.md`.

- [ ] **Step 4: Verify historical plan archive has 5 files**

```powershell
Get-ChildItem "docs\superpowers\plans\historical" -File | Select-Object Name
```

Expected: 5 files listed.

---

### Task 5: Move operational docs

**Files:**
- Move: `docs/staging-quick-order-checklist.md` → `docs/operational/staging-quick-order-checklist.md`

- [ ] **Step 1: Move staging checklist**

```powershell
Move-Item "docs\staging-quick-order-checklist.md" "docs\operational\staging-quick-order-checklist.md"
```

- [ ] **Step 2: Verify**

```powershell
Test-Path "docs\operational\staging-quick-order-checklist.md"
```

Expected: `True`

---

### Task 6: Move status.md to active/ and create current-phase.md

**Files:**
- Move: `docs/status.md` → `docs/active/status.md`
- Create: `docs/active/current-phase.md`

- [ ] **Step 1: Move status.md**

```powershell
Move-Item "docs\status.md" "docs\active\status.md"
```

- [ ] **Step 2: Create docs/active/current-phase.md**

Write the following content to `docs/active/current-phase.md`:

```markdown
# Current Active Phase — Quick Order V1.1

Last updated: 2026-05-22

---

## Active Work

Quick Order V1.1 — usability and completeness pass.

Execution plan: `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md`
Status matrix: `docs/active/status.md`

## Priorities

- **Admin bypass** — `manage_woocommerce` users currently blocked by Quick Order access guard
- **Variable stock neutral state** — variable rows show incorrect stock badge before variation selection
- **Qty +/- buttons** — qty input only, no increment/decrement controls yet
- **Cart totals footer** — experimental; render not yet wired to sync response `data.totals`

## Frozen Systems — Do Not Touch

| System | Canonical doc |
|--------|--------------|
| CartSync (debounce, token, abort) | `docs/frozen/quick-order-sync-architecture.md` |
| Checkout logic (payment rules, billing) | `docs/frozen/checkout-logic.md` |
| Visibility engine (bucket rules) | Theme `inc/` — see `CLAUDE.md` |
| WOOF/WBW filter integration | Architecture locked, in production |

Any change to a frozen system requires explicit plan approval.

## Current Philosophy

Surgical fixes only. Solve the listed DEFERRED items from the V1 status doc. No new architecture. No new abstractions.

## PHP Version

PHP 8.3+ on both local (XAMPP) and production. Local/production parity is intentional.
```

- [ ] **Step 3: Verify both files exist**

```powershell
Test-Path "docs\active\status.md"
Test-Path "docs\active\current-phase.md"
```

Expected: both `True`

---

### Task 7: Normalize PHP version references across all docs

**Files:**
- Search: all `.md` files under `docs/`
- Edit: any file with `PHP 8.1` or `PHP 8.x` or `php 8.1` (to update to `PHP 8.3+`)

- [ ] **Step 1: Find all stale PHP version references**

Run (Bash):
```bash
grep -rni "php 8\.1\|php 8\.x\|php8\.1\|php8\.x" docs/ --include="*.md"
```

If no output: all references are already clean — skip Step 2.

- [ ] **Step 2: Update each stale reference found**

For every file reported in Step 1:
- Replace `PHP 8.1` → `PHP 8.3+`
- Replace `PHP 8.x` → `PHP 8.3+`
- Replace `php 8.1` → `PHP 8.3+` (case-insensitive match)
- Replace `php8.1` or `php8.x` → `PHP 8.3+`
- Any "tested on PHP 8.1" → `PHP 8.3+ (local and production parity)`

Edit each file with the Edit tool — do not use sed.

- [ ] **Step 3: Confirm no stale references remain**

```bash
grep -rni "php 8\.1\|php 8\.x\|php8\.1\|php8\.x" docs/ --include="*.md"
```

Expected: zero output (excluding historical/ directories, which are archival).

---

### Task 8: Rewrite docs/index.md

**Files:**
- Edit: `docs/index.md` — full content replacement to reflect new zone structure

- [ ] **Step 1: Rewrite docs/index.md**

Replace the entire file content with:

```markdown
# Docs Index — Dreampoint B2B

Navigation entrypoint for humans and AI. Canonical docs listed per zone.
If a doc is not listed here, treat it as implementation detail only.

---

## Documentation Zones

| Zone | Path | Purpose |
|------|------|---------|
| Active | `docs/active/` | Current work, live status, in-progress systems |
| Frozen | `docs/frozen/` | Architecture-locked systems — no changes without approval |
| Historical | `docs/historical/` | Implemented features, dev tools, past handoffs |
| Operational | `docs/operational/` | Checklists and deploy runbooks |
| Plans (active) | `docs/superpowers/plans/` | Pending execution plans |
| Plans (archive) | `docs/superpowers/plans/historical/` | Executed plans — reference only, do not re-execute |
| Specs (archive) | `docs/superpowers/specs/historical/` | Executed design specs — reference only |

---

## Active Docs

| Doc | Covers |
|-----|--------|
| `docs/active/current-phase.md` | What is being built now, frozen system boundaries, current philosophy |
| `docs/active/status.md` | Implementation status matrix per system |

---

## Frozen Systems

> These systems are production-stable and architecture-locked.
> Do not modify without reading the doc and getting explicit approval.

| Doc | System |
|-----|--------|
| `docs/frozen/checkout-logic.md` | Checkout — payment rules, billing prefill, WooCommerce Blocks billing data protection |
| `docs/frozen/quick-order-sync-architecture.md` | CartSync — debounce engine, stale token model, variation replace flow |

---

## Historical Docs

| Doc | Covers |
|-----|--------|
| `docs/historical/synthetic-b2b-catalog.md` | Dev-only WP-CLI catalog generator (stress testing tool) |
| `docs/historical/handoff-2026-04.md` | April 2026 session handoff — superseded by current state |

---

## Operational Docs

| Doc | Use when |
|-----|----------|
| `docs/operational/staging-quick-order-checklist.md` | Before any Quick Order staging deploy |

---

## Canonical Reference

| Doc | Role |
|-----|------|
| `CLAUDE.md` (theme root) | Project rules, visibility system scope, Quick Order architecture rules — highest authority |
| `docs/dev-context.md` | Full technical context — load only when explicitly needed for enqueue/build changes |
| `docs/index.md` | This file — navigation only |

---

## Active Execution Plans

| Plan | Status | Delivers |
|------|--------|----------|
| `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md` | **Pending** | Admin bypass, variable stock fix, qty +/- buttons, cart totals footer |

---

## Plan Archive

Executed plans: `docs/superpowers/plans/historical/`

| Plan | Delivered |
|------|-----------|
| `2026-04-28-stability-performance.md` | Stability/perf pass |
| `2026-05-11-cart-sync-robustness.md` | CartSync engine (debounce, token, abort) |
| `2026-05-11-quick-order-ux-validation.md` | ProductList, RowSync, UI polish |
| `2026-05-13-quick-order-ugly-dataset.md` | Edge-case catalog (ugly dataset) |
| `2026-05-13-woof-filter-compat.md` | WOOF/WBW filter integration |

Executed specs: `docs/superpowers/specs/historical/`

| Spec | Delivered |
|------|-----------|
| `2026-05-13-quick-order-ugly-dataset-design.md` | Ugly dataset generator design |

---

## Deferred / Future Work

- Cross-page cart hydration (V2 — documented as known limitation in frozen CartSync doc)
- Offline / network-failure queue persistence
- Playwright E2E test suite for Quick Order flows
- Matrix ordering, SKU search, saved order templates (documented in `CLAUDE.md` as future scope)
```

- [ ] **Step 2: Verify no dead links in the new index**

Run (Bash):
```bash
grep -n "docs/tasks/" docs/index.md
grep -n "docs/status\.md" docs/index.md
grep -n "docs/handoff\.md" docs/index.md
grep -n "docs/staging-quick-order-checklist\.md\"" docs/index.md
```

Any match = stale link. Fix before continuing.

---

### Task 9: Fix stale references in CLAUDE.md and commit

**Files:**
- Edit: `CLAUDE.md` (if stale paths found)
- Commit: all changes

- [ ] **Step 1: Check CLAUDE.md for stale paths caused by file moves**

Run (Bash):
```bash
grep -n "docs/tasks/\|docs/status\.md\|docs/staging-quick-order-checklist\|docs/handoff\.md" CLAUDE.md
```

- [ ] **Step 2: Update any stale references found in CLAUDE.md**

The known stale reference from the pre-refactor CLAUDE.md:

```
# Before (stale)
Before modifying checkout, payment, or visibility logic — read `docs/tasks/checkout-logic.md`.

# After (correct)
Before modifying checkout, payment, or visibility logic — read `docs/frozen/checkout-logic.md`.
```

Also in the `## Completed Tasks` section:
```
# Before
- `docs/tasks/checkout-logic.md` — payment rules, billing prefill, billing data protection

# After
- `docs/frozen/checkout-logic.md` — payment rules, billing prefill, billing data protection
```

Apply the Edit tool for each match. Do not rewrite CLAUDE.md sections that were not affected.

- [ ] **Step 3: Final structure check**

```powershell
Get-ChildItem docs -Recurse -File | Select-Object -ExpandProperty FullName | Sort-Object
```

Expected files (excluding `docs/superpowers/plans/historical/` entries which are already verified):
```
docs\active\current-phase.md
docs\active\status.md
docs\dev-context.md
docs\frozen\checkout-logic.md
docs\frozen\quick-order-sync-architecture.md
docs\historical\handoff-2026-04.md
docs\historical\synthetic-b2b-catalog.md
docs\index.md
docs\operational\staging-quick-order-checklist.md
docs\superpowers\plans\2026-05-12-quick-order-v1-1.md
docs\superpowers\plans\2026-05-22-docs-refactor.md
docs\superpowers\plans\historical\[5 files]
docs\superpowers\specs\historical\[1 file]
```

- [ ] **Step 4: Commit**

Run (Bash):
```bash
git add docs/ CLAUDE.md
git diff --cached --stat
```

Then commit:
```bash
git commit -m "chore(docs): reorganize into Active/Frozen/Historical/Operational zones

- docs/frozen/: checkout-logic.md and quick-order-sync-architecture.md (FROZEN headers added)
- docs/historical/: synthetic-b2b-catalog.md and handoff-2026-04.md
- docs/operational/: staging-quick-order-checklist.md
- docs/active/: status.md + new current-phase.md (V1.1 context)
- docs/superpowers/plans/historical/: 5 executed plans archived
- docs/superpowers/specs/historical/: 1 executed spec archived
- docs/index.md: rewritten with zone-based navigation
- CLAUDE.md: updated stale paths to docs/frozen/
- PHP version references normalized to PHP 8.3+ across all non-archival docs"
```
