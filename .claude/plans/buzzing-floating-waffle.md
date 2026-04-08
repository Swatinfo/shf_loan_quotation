# Complete Workflow Documentation

## Context
Create comprehensive documentation covering the full loan lifecycle — from quotation creation to loan completion. Two documents needed:
1. **End-user guide** (`.docs/workflow-guide.md`) — for staff/admin users operating the system
2. **Developer reference** (`.docs/workflow-developer.md`) — technical details for developers

## Deliverables

### File 1: `.docs/workflow-guide.md`
End-user documentation with:
- ASCII workflow diagram (quotation → conversion → all 11 stages → completion)
- Each stage explained: what to do, who does it, what buttons to click
- Role responsibilities (Branch Manager, Loan Advisor, Bank Employee, Office Employee)
- Permission requirements per action
- Stage dependencies and blocking conditions
- Parallel processing explained visually
- Disbursement decision tree (fund transfer vs cheque → OTC)

### File 2: `.docs/workflow-developer.md`
Developer documentation with:
- Architecture: controllers, services, models involved
- Stage lifecycle: initialization, auto-assignment, phase transitions, completion
- Auto-assignment priority hierarchy (5 tiers)
- Permission resolution (3-tier + task role additive)
- Stage notes JSON structure per stage
- Phase flow diagrams for multi-phase stages
- Database tables involved
- Key methods and their signatures
- Validation rules per stage
- Event/notification triggers

## Files to Create
- `.docs/workflow-guide.md` (new)
- `.docs/workflow-developer.md` (new)

## No code changes — documentation only
