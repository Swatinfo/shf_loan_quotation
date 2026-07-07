# Routes Reference

Complete route definitions with middleware and permissions.

**Global middleware** applied to all web routes:
- `web` middleware group (session, CSRF, etc.)
- `EnsureUserIsActive` — logs out deactivated users

**Permission middleware** syntax: `permission:slug_name` — checks `User::hasPermission($slug)`, aborts 403 if denied. Super admins bypass all permission checks.

---

## Dashboard & Activity Log

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/` | Redirect to `dashboard` | auth |
| GET | `/dashboard` | DashboardController@index | auth |
| GET | `/dashboard/quotation-data` | DashboardController@quotationData | auth |
| GET | `/dashboard/task-data` | DashboardController@taskData | auth |
| GET | `/dashboard/loan-data` | DashboardController@dashboardLoanData | auth |
| GET | `/activity-log` | DashboardController@activityLog | view_activity_log |
| GET | `/activity-log/data` | DashboardController@activityLogData | view_activity_log |

---

## Profile

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/profile` | ProfileController@edit | auth |
| PATCH | `/profile` | ProfileController@update | auth |
| DELETE | `/profile` | ProfileController@destroy | auth |

---

## Users

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/users` | UserController@index | view_users |
| GET | `/users/data` | UserController@userData | view_users |
| GET | `/users/create` | UserController@create | create_users |
| POST | `/users` | UserController@store | create_users |
| GET | `/users/{user}/edit` | UserController@edit | edit_users |
| PUT | `/users/{user}` | UserController@update | edit_users |
| POST | `/users/{user}/toggle-active` | UserController@toggleActive | edit_users |
| DELETE | `/users/{user}` | UserController@destroy | delete_users |

---

## Permissions

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/permissions` | PermissionController@index | manage_permissions |
| PUT | `/permissions` | PermissionController@update | manage_permissions |

---

## Roles

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/roles` | RoleManagementController@index | manage_permissions |
| GET | `/roles/create` | RoleManagementController@create | manage_permissions |
| POST | `/roles` | RoleManagementController@store | manage_permissions |
| GET | `/roles/{role}/edit` | RoleManagementController@edit | manage_permissions |
| PUT | `/roles/{role}` | RoleManagementController@update | manage_permissions |

---

## Quotations (including PDF generation)

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/quotations/create` | QuotationController@create | create_quotation |
| POST | `/quotations/generate` | QuotationController@generate | generate_pdf |
| GET | `/quotations/{quotation}/download` | QuotationController@download | download_pdf |
| GET | `/download-pdf` | QuotationController@downloadByFilename | download_pdf |
| GET | `/quotations/{quotation}` | QuotationController@show | auth |
| DELETE | `/quotations/{quotation}` | QuotationController@destroy | delete_quotations |

### Quotation to Loan Conversion

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/quotations/{quotation}/convert` | LoanConversionController@showConvertForm | convert_to_loan |
| POST | `/quotations/{quotation}/convert` | LoanConversionController@convert | convert_to_loan |

---

## Loans (CRUD)

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans` | LoanController@index | view_loans |
| GET | `/loans/data` | LoanController@loanData | view_loans |
| GET | `/loans/create` | LoanController@create | create_loan |
| POST | `/loans` | LoanController@store | create_loan |
| GET | `/loans/{loan}` | LoanController@show | view_loans |
| GET | `/loans/{loan}/edit` | LoanController@edit | edit_loan |
| PUT | `/loans/{loan}` | LoanController@update | edit_loan |
| POST | `/loans/{loan}/status` | LoanController@updateStatus | edit_loan |
| DELETE | `/loans/{loan}` | LoanController@destroy | delete_loan |

---

## Loan Documents

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans/{loan}/documents` | LoanDocumentController@index | view_loans |
| POST | `/loans/{loan}/documents/{document}/status` | LoanDocumentController@updateStatus | manage_loan_documents |
| POST | `/loans/{loan}/documents` | LoanDocumentController@store | manage_loan_documents |
| DELETE | `/loans/{loan}/documents/{document}` | LoanDocumentController@destroy | manage_loan_documents |
| POST | `/loans/{loan}/documents/{document}/upload` | LoanDocumentController@upload | upload_loan_documents |
| GET | `/loans/{loan}/documents/{document}/download` | LoanDocumentController@download | download_loan_documents |
| DELETE | `/loans/{loan}/documents/{document}/file` | LoanDocumentController@deleteFile | delete_loan_files |

---

## Loan Stages

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans/{loan}/stages` | LoanStageController@index | view_loans |
| POST | `/loans/{loan}/stages/{stageKey}/status` | LoanStageController@updateStatus | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/assign` | LoanStageController@assign | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/reject` | LoanStageController@reject | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/query` | LoanStageController@raiseQuery | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/notes` | LoanStageController@saveNotes | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/skip` | LoanStageController@skip | skip_loan_stages |
| GET | `/loans/{loan}/stages/{stageKey}/eligible-users` | LoanStageController@eligibleUsers | manage_loan_stages |

### Stage-Specific Actions

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| POST | `/loans/{loan}/stages/esign/action` | LoanStageController@esignAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/docket/action` | LoanStageController@docketAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/rate_pf/action` | LoanStageController@ratePfAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/sanction/action` | LoanStageController@sanctionAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/legal_verification/action` | LoanStageController@legalAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/sanction_decision/action` | LoanStageController@sanctionDecisionAction | manage_loan_stages |

---

## Loan Transfers

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans/{loan}/transfers` | LoanStageController@transferHistory | view_loans |
| POST | `/loans/{loan}/stages/{stageKey}/transfer` | LoanStageController@transfer | manage_loan_stages |

---

## Loan Queries/Remarks

### Queries (via Stage Controller)

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| POST | `/loans/{loan}/stages/{stageKey}/query` | LoanStageController@raiseQuery | manage_loan_stages |
| POST | `/loans/queries/{query}/respond` | LoanStageController@respondToQuery | manage_loan_stages |
| POST | `/loans/queries/{query}/resolve` | LoanStageController@resolveQuery | manage_loan_stages |

### Remarks

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans/{loan}/remarks` | LoanRemarkController@index | view_loans |
| POST | `/loans/{loan}/remarks` | LoanRemarkController@store | add_remarks |

---

## Loan Valuation

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans/{loan}/valuation` | LoanValuationController@show | manage_loan_stages |
| POST | `/loans/{loan}/valuation` | LoanValuationController@store | manage_loan_stages |

---

## Loan Disbursement

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans/{loan}/disbursement` | LoanDisbursementController@show | manage_loan_stages |
| POST | `/loans/{loan}/disbursement` | LoanDisbursementController@store | manage_loan_stages |

---

## Loan Timeline

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loans/{loan}/timeline` | LoanController@timeline | view_loans |

---

## Loan Settings

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/loan-settings` | LoanSettingsController@index | view_loans |
| POST | `/loan-settings/master-stages` | LoanSettingsController@saveMasterStages | manage_workflow_config |
| POST | `/loan-settings/locations` | LoanSettingsController@storeLocation | manage_workflow_config |
| DELETE | `/loan-settings/locations/{location}` | LoanSettingsController@destroyLocation | manage_workflow_config |
| POST | `/loan-settings/task-role-permissions` | LoanSettingsController@saveTaskRolePermissions | manage_workflow_config |

---

## Workflow Config

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| POST | `/loan-settings/banks` | WorkflowConfigController@storeBank | manage_workflow_config |
| DELETE | `/loan-settings/banks/{bank}` | WorkflowConfigController@destroyBank | manage_workflow_config |
| POST | `/loan-settings/products` | WorkflowConfigController@storeProduct | manage_workflow_config |
| GET | `/loan-settings/products/{product}/stages` | WorkflowConfigController@productStages | manage_workflow_config |
| POST | `/loan-settings/products/{product}/stages` | WorkflowConfigController@saveProductStages | manage_workflow_config |
| POST | `/loan-settings/products/{product}/locations` | WorkflowConfigController@saveProductLocations | manage_workflow_config |
| DELETE | `/loan-settings/products/{product}` | WorkflowConfigController@destroyProduct | manage_workflow_config |
| POST | `/loan-settings/branches` | WorkflowConfigController@storeBranch | manage_workflow_config |
| DELETE | `/loan-settings/branches/{branch}` | WorkflowConfigController@destroyBranch | manage_workflow_config |

---

## Notifications

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/notifications` | NotificationController@index | auth |
| GET | `/api/notifications/count` | NotificationController@unreadCount | auth |
| POST | `/notifications/{notification}/read` | NotificationController@markRead | auth |
| POST | `/notifications/read-all` | NotificationController@markAllRead | auth |

---

## Impersonation

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/api/impersonate/users` | ImpersonateController@users | auth (controller checks canImpersonate) |
| GET | `/impersonate/take/{id}` | (laravel-impersonate package) | canImpersonate gate |
| GET | `/impersonate/leave` | (laravel-impersonate package) | any impersonating user |

---

## Settings (App Settings)

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/settings` | SettingsController@index | view_settings |
| POST | `/settings/company` | SettingsController@updateCompany | edit_company_info |
| POST | `/settings/banks` | SettingsController@updateBanks | edit_banks |
| POST | `/settings/tenures` | SettingsController@updateTenures | edit_tenures |
| POST | `/settings/documents` | SettingsController@updateDocuments | edit_documents |
| POST | `/settings/charges` | SettingsController@updateCharges | edit_charges |
| POST | `/settings/bank-charges` | SettingsController@updateBankCharges | edit_charges |
| POST | `/settings/services` | SettingsController@updateServices | edit_services |
| POST | `/settings/gst` | SettingsController@updateGst | edit_gst |
| POST | `/settings/reset` | SettingsController@reset | view_settings |

---

## API Routes

### Session-Authenticated (web middleware, inside auth group)

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| POST | `/api/sync` | SyncApiController@sync | auth |
| POST | `/api/notes` | NotesApiController@save | auth |

### Public API (api middleware, no auth)

Prefix: `/api`

| Method | URI | Action | Permission |
|--------|-----|--------|------------|
| GET | `/api/config/public` | ConfigApiController@public | none |
| GET | `/api/notes` | NotesApiController@get | none |

---

## Auth Routes

### Guest Only (redirected if authenticated)

| Method | URI | Action | Name |
|--------|-----|--------|------|
| GET | `/login` | AuthenticatedSessionController@create | login |
| POST | `/login` | AuthenticatedSessionController@store | - |
| GET | `/forgot-password` | PasswordResetLinkController@create | password.request |
| POST | `/forgot-password` | PasswordResetLinkController@store | password.email |
| GET | `/reset-password/{token}` | NewPasswordController@create | password.reset |
| POST | `/reset-password` | NewPasswordController@store | password.store |

> Registration is disabled. Users are created by admins via User Management.

### Authenticated

| Method | URI | Action | Name |
|--------|-----|--------|------|
| GET | `/verify-email` | EmailVerificationPromptController | verification.notice |
| GET | `/verify-email/{id}/{hash}` | VerifyEmailController | verification.verify |
| POST | `/email/verification-notification` | EmailVerificationNotificationController@store | verification.send |
| GET | `/confirm-password` | ConfirmablePasswordController@show | password.confirm |
| POST | `/confirm-password` | ConfirmablePasswordController@store | - |
| PUT | `/password` | PasswordController@update | password.update |
| POST | `/logout` | AuthenticatedSessionController@destroy | logout |
