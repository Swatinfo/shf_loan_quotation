# Workflow Rules

## Task Management
1. **Plan first**: Write plan to `tasks/todo.md` with checkable items BEFORE starting implementation
2. **Track progress live**: Mark items complete as EACH step completes — not all at once after
3. **Capture lessons**: Update `tasks/lessons.md` after ANY user correction or discovered pattern
4. **Verify before done**: Test before marking a task complete
5. **Keep docs in sync**: Update reference files as part of the same change

## Planning
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately
- Write detailed specs upfront to reduce ambiguity

## Subagents
- Use subagents for research, exploration, and parallel analysis
- One task per subagent for focused execution
- Keep main context window clean

## Quality
- Ask: "Would a staff engineer approve this?"
- For simple, obvious fixes, skip over-engineering
- Run tests, check logs, demonstrate correctness

## Bug Fixing
- Given a bug report: just fix it
- Point at logs, errors, failing tests — then resolve
- Zero context switching required from the user

## Documentation Sync Checklist
After any code change, update if affected:
- `.claude/database-schema.md` — table schemas, columns, permissions
- `.claude/routes-reference.md` — route definitions, methods, permissions
- `.claude/services-reference.md` — service methods, validation, business logic
- `.docs/database.md` + `.docs/models.md` — if schema or models changed
- `.docs/permissions.md` — if permissions or roles changed
- `.docs/workflow-guide.md` + `.docs/workflow-developer.md` — if loan stages changed
- `.docs/general-tasks.md` — if task system changed
- `.docs/dvr.md` — if DVR changed
- `.docs/dashboard.md` — if dashboard tabs or stats changed
- `.docs/loans.md` — if loan CRUD changed
- `.docs/roles.md` — if roles changed
- `.docs/activity-log.md` — if activity logging changed
- Relevant `.docs/` file — feature behavior, UI patterns, validation rules
- `tasks/lessons.md` — new patterns or corrections
- `tasks/todo.md` — task progress
