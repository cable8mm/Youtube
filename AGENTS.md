# AGENTS.md

## DOCUMENT_ORDER

1. AGENTS.md
2. .replworks/PRODUCT_SPEC.md
3. .replworks/TECH_STACK.md
4. .replworks/ARCHITECTURE.md
5. .replworks/TASKS.md

Only these documents are authoritative.

---

## IGNORE

Ignore all files under:

```text
.replworks/docs/
```

Never use files in these folders as requirements.
Never implement features described only in these folders.

---

## SOURCE_OF_TRUTH

Product Requirements:

```text
PRODUCT_SPEC.md
```

Implementation Constraints:

```text
TECH_STACK.md
```

Architecture:

```text
ARCHITECTURE.md
```

Execution Plan:

```text
TASKS.md
```

If a conflict exists:

```text
PRODUCT_SPEC.md
>
TECH_STACK.md
>
ARCHITECTURE.md
>
TASKS.md
>
everything else
```

---

## CONFLICT_HANDLING

When documents conflict:

1. Follow the higher-priority document.
2. Report the conflict to the human.
3. Fix the lower-priority document together with the human before continuing.

Never resolve a conflict silently.
Never leave a conflict in place after the task is complete.

---

## DOCUMENT_RESPONSIBILITIES

PRODUCT_SPEC.md defines:

```text
What the product is.
What the product does.
```

TECH_STACK.md defines:

```text
How the product must be implemented.
```

ARCHITECTURE.md defines:

```text
How the product works.
```

TASKS.md defines:

```text
What should be implemented next.
```

Do not move responsibilities between documents.

ARCHITECTURE.md must not contain technology, versions, or folder rules.
TECH_STACK.md must not contain component responsibilities or data flows.

---

## EXTERNAL_BOUNDARY

Define once. Referenced by TASK_EXECUTION and MOCK_RULES below.

```text
External boundary = any behavior not controlled by this codebase.
Examples:
third-party DOM
third-party API
browser runtime behavior
```

Libraries that run inside this codebase's own process are not an external boundary.

---

## UNVERIFIED_MARK

Add `[UNVERIFIED]` to the heading of the section that owns the gap.

```text
Gaps in what the product is or does          -> PRODUCT_SPEC.md
Gaps in how the product must be implemented  -> TECH_STACK.md
Gaps in how the product works                -> ARCHITECTURE.md
```

Clear the mark only after human confirmation.

---

## IMPLEMENTATION_RULES

Implement only the selected task.
Exception: HUMAN_OWNED_CHANGES.
Do not implement:

```text
future work
roadmap items
optional features
assumptions
inferred requirements
```

Requirements must originate from:

```text
PRODUCT_SPEC.md
```

Implementation must follow:

```text
TECH_STACK.md
ARCHITECTURE.md
```

---

## TASK_SELECTION

Implement the task named by the human.
If none is named, use the first unchecked task in TASKS.md, from top to bottom.

---

## TASK_EXECUTION

For every task:

1. Read PRODUCT_SPEC.md
2. Read TECH_STACK.md
3. Read ARCHITECTURE.md
4. Read task definition
5. If the task touches a domain that is not covered by verified knowledge in PRODUCT_SPEC.md, TECH_STACK.md, or ARCHITECTURE.md: stop. Mark the relevant section UNVERIFIED (see UNVERIFIED_MARK). Do not implement against an UNVERIFIED section. Require explicit human confirmation before continuing.
6. Implement
7. Write unit tests for internal logic
8. If the task touches an EXTERNAL_BOUNDARY: write an E2E test against the live boundary. A mocked test alone does not satisfy this step. If the live boundary is unreachable, stop and report. Do not mark the task `[x]`.
9. Run all tests
10. Mark the completed task `[x]` in TASKS.md
11. Deliver and continue (see AUTO_RUN)

---

## AUTO_RUN

AUTO_RUN applies only to tasks from TASKS.md.
Any other change the human requests in the session is committed only after human review.

Repeat TASK_SELECTION and TASK_EXECUTION until no unchecked task remains.
Do not stop between tasks. Do not ask for approval to start a task.

Before the first git operation of a session, ask the human to confirm
the delivery identities. Fill in every value and ask in this form:

Commit author/committer: <name> <noreply email>
Co-authored-by: <human account> (only when the human took part in the change)
PR creator / assignee: <bot account>
Merger: <account>
Is this correct?

Fill missing values using available context, including `gh` commands. Show the
complete values and ask: "<values>. Is this correct?" Wait for the human's
confirmation before any git operation.

Per task:

1. Pull main.
2. Create branch `t-NNN-slug`.
3. Implement and test (TASK_EXECUTION).
4. Commit with the agent that wrote the code as author and committer
   (name and noreply email of that agent).
   Add a `Co-authored-by` trailer for the human account
   when the human specified the change or answered a question about it,
   in the session or on GitHub. Add no other `Co-authored-by` trailer.
5. Open a PR as the bot account. Assignee: bot account.
6. Squash merge only after every CI check on the PR has passed.
   Pending is not passed. If no CI is configured, there is no gate.
7. Pull main and start the next task.

Never commit or merge directly to main.
Commit messages and the PR title follow Conventional Commits.
Merge message: use the one GitHub generates. Do not set a custom subject or body.

Stop and ask the human only when:

```text
an UNVERIFIED gap needs a decision
documents conflict
a task is invalidated, or a spec/task/stack/architecture change is needed
a human-requested change is awaiting review
a live EXTERNAL_BOUNDARY is unreachable
a task requires a Developer decision
a manual or permission step is required
tests or CI cannot be fixed
```

---

## MOCK_RULES

Mock only observed behavior.

```text
Allowed sources:
recorded live response
documented spec
```

```text
Forbidden sources:
assumed behavior
guessed response
inferred event flow
```

If a mock's values cannot be traced to a recorded observation or a spec, do not write it.
Any code touching an EXTERNAL_BOUNDARY requires at least one live observation before it may be mocked.
Re-verify mocks when the external system's behavior may have changed.

---

## PRODUCT_SPEC_CHANGES

If implementation reveals missing product requirements, or a PRODUCT_SPEC.md section is marked UNVERIFIED:

```text
Stop.
Do not invent requirements.
```

1. Propose the change to the human.
2. Update PRODUCT_SPEC.md only after human confirmation.
3. Clear the UNVERIFIED mark only after human confirmation.
4. Continue implementation.

---

## TECH_STACK_CHANGES

If implementation requires tech stack changes, or a TECH_STACK.md section is marked UNVERIFIED:

1. Stop. Propose the change to the human.
2. If the change conflicts with PRODUCT_SPEC.md, see CONFLICT_HANDLING.
3. Update TECH_STACK.md only after human confirmation.
4. Clear the UNVERIFIED mark only after human confirmation.
5. Update implementation.

Never allow tech stack and code to diverge.

---

## ARCHITECTURE_CHANGES

If implementation requires architecture changes, or an ARCHITECTURE.md section is marked UNVERIFIED:

1. Stop. Propose the change to the human.
2. If the change conflicts with PRODUCT_SPEC.md or TECH_STACK.md, see CONFLICT_HANDLING.
3. Update ARCHITECTURE.md only after human confirmation.
4. Clear the UNVERIFIED mark only after human confirmation.
5. Update implementation.

Never allow architecture and code to diverge.

---

## TASK_CHANGES

If implementation invalidates a task, or PRODUCT_SPEC.md changes:

1. Propose the TASKS.md change to the human.
2. Update TASKS.md only after human confirmation.

Never change an existing task ID.
Never regenerate TASKS.md from scratch.

These rules bind the AI. The human may edit TASKS.md directly at any time.
Human edits are authoritative.

Exception: when the human explicitly requests task reordering that requires
renumbering, the AI may approve and apply the renumbering after reviewing task
dependencies, completion status, and references. Update every affected task ID
reference in the same change; preserve each task's content and completion state.

---

## HUMAN_OWNED_CHANGES

Content and visual design are human-owned.
They are not specified in PRODUCT_SPEC.md and are not tracked in TASKS.md.

```text
Content:       wording, copy, translations, images, data text
Visual design: styling, spacing, colors, typography, imagery, visual polish
```

Not human-owned: which screens and elements exist, their purpose,
their arrangement relative to each other, their states, and their interactions.
These are specified in PRODUCT_SPEC.md.

When the human explicitly asks for such a change in the session:

1. Make only that change.
2. Do not change behavior, screens, elements, states, or interactions
   defined in PRODUCT_SPEC.md.
   If the change would, stop (see PRODUCT_SPEC_CHANGES).
3. Do not record the change in TASKS.md or any other document.
4. Run the verification commands defined in TECH_STACK.md.
5. Show the diff and test results. After explicit human approval, commit, open the PR, and merge by AUTO_RUN steps 4-6 (merge only after CI passes).
6. Stop.

Never make such changes on your own initiative.
If a task needs content or styling and none is provided,
use neutral placeholders and report them when you stop.

---

## DESIGN_RULES

Prefer:

```text
simple
explicit
minimal
```

Avoid:

```text
abstraction without use
premature optimization
speculative features
```

---

## FILE_CREATION

Do not create new top-level documents unless explicitly requested.
Prefer modifying existing files.

---

## SUCCESS_CRITERIA

Task is complete only when:

- product requirements satisfied
- architectural requirements satisfied
- tech stack constraints satisfied
- the task's acceptance criteria in TASKS.md satisfied
- no UNVERIFIED sections remain in scope for this task
- no unresolved document conflicts remain
- code runs
- unit tests pass
- E2E tests pass for any EXTERNAL_BOUNDARY code touched
- the PR is merged

Then continue with the next task.