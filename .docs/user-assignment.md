# User Assignment

## Overview

Auto-assignment determines which user gets assigned to each loan stage based on role eligibility, branch, bank, product configuration, and location.

## Auto-Assignment: `LoanStageService::findBestAssignee()`

Priority order:
1. **ProductStageUser config** — branch/location-specific user from `product_stage_users` table
2. **Loan advisor** — if stage's default_role includes an advisor-eligible role, assign the loan's advisor
3. **Bank default employee** — `Bank::getDefaultEmployeeForCity(cityId)` for bank_employee roles
4. **Role + branch match** — user with matching role in same branch
5. **Any matching role** — fallback to any active user with the role

## ProductStage Configuration

### `product_stages` table
Per-product stage settings:
- `is_enabled` — whether stage is active for this product
- `default_assignee_role` — role slug for assignment
- `default_user_id` — specific user fallback
- `allow_skip` — whether stage can be skipped
- `auto_skip` — whether stage auto-skips

### `product_stage_users` table
Branch/location-specific user assignments:
- `product_stage_id` — links to product_stages
- `branch_id` — specific branch (nullable)
- `location_id` — specific city/state (nullable)
- `user_id` — assigned user
- `is_default` — default assignment flag

### Location Hierarchy: `ProductStage::getUserForLocation()`
Resolution order:
1. Exact branch match
2. City match (location_id = city)
3. State match (location_id = state)
4. Default user (default_user_id)

## Bank Employee Assignment

`Bank::getDefaultEmployeeForCity(cityId)`:
1. Look for employee with `is_default = true` AND matching `location_id`
2. Fallback to employee with `is_default = true` AND no location

## Stage Role Eligibility

`Stage.default_role` — JSON array of role slugs eligible for each stage.

`LoanStageService::getStageRoleEligibility(stageKey)` returns the array.

## Parallel Sub-Stage Assignment

`autoAssignParallelSubStages(loan)`:
- Only auto-assigns `app_number` initially
- Other sub-stages assigned when their predecessor completes (handled in `handleStageCompletion`)

## Eligible Users Endpoint

`LoanStageController@eligibleUsers(loan, stageKey)`:
- Returns users eligible for a specific stage assignment
- Filters by stage's default_role slugs
- Used by transfer and assign UI dropdowns

## Configuration UI

Managed in Loan Settings page:
- Product stage configuration per product
- Branch user assignments per product per stage
- Master stage enable/disable and default role
