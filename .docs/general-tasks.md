# General Tasks

## Overview

Personal and delegated task management system, separate from loan workflow. Any logged-in user can create tasks.

## Model: `GeneralTask`

### Statuses
| Status | Badge |
|--------|-------|
| pending | gray |
| in_progress | blue |
| completed | green |
| cancelled | red |

### Priorities
| Priority | Badge |
|----------|-------|
| low | gray |
| normal | blue |
| high | orange |
| urgent | red |

### Fields
- title, description, due_date, priority, status
- created_by (FK users — task creator)
- assigned_to (FK users — nullable, task assignee)
- loan_detail_id (FK loan_details — nullable, optional loan link)
- completed_at (timestamp — set when status = completed)

## Visibility (`scopeVisibleTo`)

1. **view_all_tasks** permission → sees everything (admin, read-only)
2. **Own tasks** → created_by = user OR assigned_to = user
3. **BDH** → also sees tasks from users in their branches (via user_branches pivot)

## Controller: `GeneralTaskController`

### No Permission Gate
All logged-in users can access task routes. No `permission:` middleware.

### Routes
- `GET /general-tasks` → index (DataTable list)
- `POST /general-tasks` → store (create)
- `GET /general-tasks/{task}` → show (detail page)
- `PUT /general-tasks/{task}` → update
- `DELETE /general-tasks/{task}` → destroy
- `PATCH /general-tasks/{task}/status` → updateStatus
- `POST /general-tasks/{task}/comments` → storeComment
- `DELETE /general-tasks/{task}/comments/{comment}` → destroyComment
- `GET /general-tasks/search-loans` → AJAX loan search
- `GET /general-tasks/data` → DataTable server-side data

### DataTable Filters
- View: my_tasks_and_assigned, my_tasks, assigned_to_me, my_branch (BDH), all (admin)
- Status: pending, in_progress, completed, cancelled
- Priority: low, normal, high, urgent
- Search: title, assignee name, creator name, loan number/customer

### After Create
Redirects to task show page, not list.

## Comments

`GeneralTaskComment` model:
- body text, user_id, general_task_id
- Created by any user who can view the task
- Delete by comment author only

## Loan Link

Tasks can optionally link to a loan via `loan_detail_id`:
- Search endpoint: `/general-tasks/search-loans`
- Searches by loan number, application number, customer name

## Notifications

- Task assignment → notify assignee
- Task completion → notify creator (if different from completer)
- Comment added → notify task participants

## Dashboard Integration

"Personal Tasks" tab on dashboard with:
- Create task modal (opens directly, no redirect)
- Stat cards: pending, in progress, overdue, completed
- Default tab when user has overdue tasks

## Views

| View | Purpose |
|------|---------|
| general-tasks/index.blade.php | DataTable list with filters |
| general-tasks/show.blade.php | Task detail with comments section |

## Edit/Delete Rules

- Only task creator can edit or delete
- Admin has view_all_tasks (read-only, cannot edit)

## Status Change Rules

- Task creator can change to any status (pending, in_progress, completed, cancelled)
- Task assignee can change status (pending, in_progress, completed) but cannot cancel
- Server-side guard in `updateStatus()`: only creator can set status to cancelled
- View uses `canChangeStatus(user)` to show/hide status buttons
