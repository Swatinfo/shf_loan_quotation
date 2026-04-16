# Permissions

## Overview

3-tier permission resolution system with 48 permissions across 8 groups.

## Resolution Order

1. **super_admin bypass** — super_admin role always returns true
2. **User override** — `user_permissions` table with grant/deny type
3. **Role default** — any of the user's roles having the permission grants it

Implemented in `PermissionService::userHasPermission()`.

## Caching

- 5-minute TTL per user/role combination
- Cache keys: `user_perms:{id}`, `user_role_ids:{id}`, `role_perms:{role_ids}`
- Cleared on: user edit, role edit, permission matrix update

## Middleware

`CheckPermission` middleware (`permission:slug_name`) — aborts 403 if user lacks permission.

Applied per-route in `routes/web.php`.

## Permission Groups

### Settings (8)
| Slug | Description |
|------|-------------|
| view_settings | View settings page |
| edit_company_info | Edit company information |
| edit_banks | Add/edit/remove quotation banks |
| edit_documents | Add/edit/remove required documents |
| edit_tenures | Add/edit/remove loan tenures |
| edit_charges | Edit IOM and bank charges |
| edit_services | Edit service description |
| edit_gst | Edit GST percentage |

### Quotations (8)
| Slug | Description |
|------|-------------|
| create_quotation | Create new quotations |
| generate_pdf | Generate PDF |
| view_own_quotations | View own quotations |
| view_all_quotations | View all quotations |
| delete_quotations | Delete quotations |
| download_pdf | Download PDF |
| download_pdf_branded | Download branded PDF |
| download_pdf_plain | Download plain PDF |

### Users (5)
| Slug | Description |
|------|-------------|
| view_users | View users list |
| create_users | Create users |
| edit_users | Edit users |
| delete_users | Delete users |
| assign_roles | Assign roles |

### Loans (14)
| Slug | Description |
|------|-------------|
| convert_to_loan | Convert quotation to loan |
| view_loans | View loan list |
| view_all_loans | View all loans |
| create_loan | Create loans directly |
| edit_loan | Edit loan details |
| delete_loan | Delete loans |
| manage_loan_documents | Mark docs received/pending, add/remove |
| upload_loan_documents | Upload document files |
| download_loan_documents | Download document files |
| delete_loan_files | Remove uploaded files |
| manage_loan_stages | Update stage status and assignments |
| skip_loan_stages | Skip workflow stages |
| add_remarks | Add remarks to stages |
| manage_workflow_config | Configure banks, products, stages |

### Transfer (1)
| Slug | Description |
|------|-------------|
| transfer_loan_stages | Transfer stages between users |

### Tasks (1)
| Slug | Description |
|------|-------------|
| view_all_tasks | View all tasks (admin read-only) |

### DVR (5)
| Slug | Description |
|------|-------------|
| view_dvr | View DVR reports |
| create_dvr | Create DVR |
| edit_dvr | Edit DVR |
| delete_dvr | Delete DVR |
| view_all_dvr | View all DVR across users |

### System (4)
| Slug | Description |
|------|-------------|
| change_own_password | Change own password |
| manage_permissions | Manage role/user permissions |
| view_activity_log | View activity log |
| view_reports | View turnaround time and other reports |

## Management

- **Permission matrix:** `PermissionController` — manages role → permission mappings (excludes Loans group, managed in Loan Settings)
- **User overrides:** Set per-user in user edit form (grant/deny)
- **Defined in:** `config/permissions.php`
- **Seeded via:** migration `2026_04_09_211216_create_unified_roles_system.php`
