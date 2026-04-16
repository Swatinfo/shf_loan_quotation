# Routes Reference

All application routes organized by domain.

## Middleware

- `active` (EnsureUserIsActive) — appended globally to all web routes. Logs out users with `is_active = false`.
- `permission:slug` (CheckPermission) — aborts 403 if user lacks the required permission slug.
- Middleware aliases configured in `bootstrap/app.php`.

---

## Auth Routes (routes/auth.php)

| Method | URI | Controller@Method | Name | Middleware |
|--------|-----|-------------------|------|------------|
| GET | /login | AuthenticatedSessionController@create | login | guest |
| POST | /login | AuthenticatedSessionController@store | — | guest |
| GET | /forgot-password | PasswordResetLinkController@create | password.request | guest |
| POST | /forgot-password | PasswordResetLinkController@store | password.email | guest |
| GET | /reset-password/{token} | NewPasswordController@create | password.reset | guest |
| POST | /reset-password | NewPasswordController@store | password.store | guest |
| GET | /verify-email | EmailVerificationPromptController | verification.notice | auth |
| GET | /verify-email/{id}/{hash} | VerifyEmailController | verification.verify | auth, signed, throttle:6,1 |
| POST | /email/verification-notification | EmailVerificationNotificationController@store | verification.send | auth, throttle:6,1 |
| GET | /confirm-password | ConfirmablePasswordController@show | password.confirm | auth |
| POST | /confirm-password | ConfirmablePasswordController@store | — | auth |
| PUT | /password | PasswordController@update | password.update | auth |
| POST | /logout | AuthenticatedSessionController@destroy | logout | auth |

---

## Public API Routes (routes/api.php, no auth)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /api/config/public | ConfigApiController@public | — |
| GET | /api/notes | NotesApiController@get | — |

---

## Dashboard (auth)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /dashboard | DashboardController@index | dashboard |
| GET | /dashboard/quotation-data | DashboardController@quotationData | dashboard.quotation-data |
| GET | /dashboard/task-data | DashboardController@taskData | dashboard.task-data |
| GET | /dashboard/loan-data | DashboardController@dashboardLoanData | dashboard.loan-data |
| GET | /dashboard/dvr-data | DashboardController@dvrData | dashboard.dvr-data |

---

## Profile (auth)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /profile | ProfileController@edit | profile.edit |
| PATCH | /profile | ProfileController@update | profile.update |
| DELETE | /profile | ProfileController@destroy | profile.destroy |

---

## Users (auth + permission:view_users/create_users/edit_users/delete_users)

| Method | URI | Controller@Method | Name | Permission |
|--------|-----|-------------------|------|------------|
| GET | /users | UserController@index | users.index | view_users |
| GET | /users/data | UserController@userData | users.data | view_users |
| GET | /users/create | UserController@create | users.create | create_users |
| POST | /users | UserController@store | users.store | create_users |
| GET | /users/check-email | UserController@checkEmail | users.check-email | view_users |
| GET | /users/product-stage-holders | UserController@productStageHolders | users.product-stage-holders | view_users |
| GET | /users/{user}/edit | UserController@edit | users.edit | edit_users |
| PUT | /users/{user} | UserController@update | users.update | edit_users |
| POST | /users/{user}/toggle-active | UserController@toggleActive | users.toggle-active | edit_users |
| DELETE | /users/{user} | UserController@destroy | users.destroy | delete_users |

---

## Permissions & Roles (auth + permission:manage_permissions)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /permissions | PermissionController@index | permissions.index |
| PUT | /permissions | PermissionController@update | permissions.update |
| GET | /roles | RoleManagementController@index | roles.index |
| GET | /roles/create | RoleManagementController@create | roles.create |
| POST | /roles | RoleManagementController@store | roles.store |
| GET | /roles/{role}/edit | RoleManagementController@edit | roles.edit |
| PUT | /roles/{role} | RoleManagementController@update | roles.update |
| GET | /roles/check-name | RoleManagementController@checkName | roles.check-name |

---

## Settings (auth + permission:view_settings + specific edit perms)

| Method | URI | Controller@Method | Name | Permission |
|--------|-----|-------------------|------|------------|
| GET | /settings | SettingsController@index | settings.index | view_settings |
| POST | /settings/company | SettingsController@updateCompany | settings.company | edit_company_info |
| POST | /settings/banks | SettingsController@updateBanks | settings.banks | edit_banks |
| POST | /settings/tenures | SettingsController@updateTenures | settings.tenures | edit_tenures |
| POST | /settings/documents | SettingsController@updateDocuments | settings.documents | edit_documents |
| POST | /settings/charges | SettingsController@updateCharges | settings.charges | edit_charges |
| POST | /settings/bank-charges | SettingsController@updateBankCharges | settings.bank-charges | edit_charges |
| POST | /settings/services | SettingsController@updateServices | settings.services | edit_services |
| POST | /settings/gst | SettingsController@updateGst | settings.gst | edit_gst |
| POST | /settings/dvr-contact-types | SettingsController@updateDvrContactTypes | settings.dvr-contact-types | view_settings |
| POST | /settings/dvr-purposes | SettingsController@updateDvrPurposes | settings.dvr-purposes | view_settings |
| POST | /settings/reset | SettingsController@reset | settings.reset | view_settings |

---

## Quotations (auth + specific permissions)

| Method | URI | Controller@Method | Name | Permission |
|--------|-----|-------------------|------|------------|
| GET | /quotations/create | QuotationController@create | quotations.create | create_quotation |
| POST | /quotations/generate | QuotationController@generate | quotations.generate | generate_pdf |
| GET | /quotations/{quotation}/download | QuotationController@download | quotations.download | download_pdf |
| GET | /download-pdf | QuotationController@downloadByFilename | quotations.download-file | download_pdf |
| GET | /quotations/{quotation}/preview-html | QuotationController@previewHtml | quotations.preview-html | auth only |
| GET | /quotations/{quotation} | QuotationController@show | quotations.show | auth only |
| DELETE | /quotations/{quotation} | QuotationController@destroy | quotations.destroy | delete_quotations |

---

## Quotation Conversion (auth + permission:convert_to_loan)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /quotations/{quotation}/convert | LoanConversionController@showConvertForm | quotations.convert |
| POST | /quotations/{quotation}/convert | LoanConversionController@convert | quotations.convert.store |

---

## Loans (auth + specific permissions)

| Method | URI | Controller@Method | Name | Permission |
|--------|-----|-------------------|------|------------|
| GET | /loans | LoanController@index | loans.index | view_loans |
| GET | /loans/data | LoanController@loanData | loans.data | view_loans |
| GET | /loans/create | LoanController@create | loans.create | create_loan |
| POST | /loans | LoanController@store | loans.store | create_loan |
| GET | /loans/{loan} | LoanController@show | loans.show | view_loans |
| GET | /loans/{loan}/timeline | LoanController@timeline | loans.timeline | view_loans |
| GET | /loans/{loan}/edit | LoanController@edit | loans.edit | edit_loan |
| PUT | /loans/{loan} | LoanController@update | loans.update | edit_loan |
| POST | /loans/{loan}/status | LoanController@updateStatus | loans.update-status | edit_loan |
| DELETE | /loans/{loan} | LoanController@destroy | loans.destroy | delete_loan |

---

## Loan Stages (auth + permission:manage_loan_stages)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /loans/{loan}/stages | LoanStageController@index | loans.stages |
| GET | /loans/{loan}/transfers | LoanStageController@transferHistory | loans.transfers |
| POST | /loans/{loan}/stages/{stageKey}/status | LoanStageController@updateStatus | loans.stages.status |
| POST | /loans/{loan}/stages/{stageKey}/assign | LoanStageController@assign | loans.stages.assign |
| POST | /loans/{loan}/stages/{stageKey}/transfer | LoanStageController@transfer | loans.stages.transfer |
| POST | /loans/{loan}/stages/{stageKey}/reject | LoanStageController@reject | loans.stages.reject |
| POST | /loans/{loan}/stages/{stageKey}/query | LoanStageController@raiseQuery | loans.stages.query |
| POST | /loans/{loan}/stages/{stageKey}/notes | LoanStageController@saveNotes | loans.stages.notes |
| POST | /loans/{loan}/stages/{stageKey}/skip | LoanStageController@skip | loans.stages.skip |
| GET | /loans/{loan}/stages/{stageKey}/eligible-users | LoanStageController@eligibleUsers | loans.stages.eligible-users |
| POST | /loans/queries/{query}/respond | LoanStageController@respondToQuery | loans.queries.respond |
| POST | /loans/queries/{query}/resolve | LoanStageController@resolveQuery | loans.queries.resolve |

### Stage-Specific Actions (permission:manage_loan_stages)
| Method | URI | Name |
|--------|-----|------|
| POST | /loans/{loan}/stages/technical_valuation/action | loans.stages.technical-valuation-action |
| POST | /loans/{loan}/stages/esign/action | loans.stages.esign-action |
| POST | /loans/{loan}/stages/docket/action | loans.stages.docket-action |
| POST | /loans/{loan}/stages/rate_pf/action | loans.stages.rate-pf-action |
| POST | /loans/{loan}/stages/sanction/action | loans.stages.sanction-action |
| POST | /loans/{loan}/stages/legal_verification/action | loans.stages.legal-action |
| POST | /loans/{loan}/stages/sanction_decision/action | loans.stages.sanction-decision-action |

---

## Disbursement & Valuation (auth + permission:manage_loan_stages)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /loans/{loan}/disbursement | LoanDisbursementController@show | loans.disbursement |
| POST | /loans/{loan}/disbursement | LoanDisbursementController@store | loans.disbursement.store |
| GET | /loans/{loan}/valuation | LoanValuationController@show | loans.valuation |
| GET | /loans/{loan}/valuation-map | LoanValuationController@showMap | loans.valuation.map |
| POST | /loans/{loan}/valuation | LoanValuationController@store | loans.valuation.store |
| GET | /api/reverse-geocode | LoanValuationController@reverseGeocode | api.reverse-geocode |
| GET | /api/search-location | LoanValuationController@searchLocation | api.search-location |

---

## Loan Documents (auth + specific permissions)

| Method | URI | Controller@Method | Name | Permission |
|--------|-----|-------------------|------|------------|
| GET | /loans/{loan}/documents | LoanDocumentController@index | loans.documents | view_loans |
| POST | /loans/{loan}/documents/{document}/status | LoanDocumentController@updateStatus | loans.documents.status | manage_loan_documents |
| POST | /loans/{loan}/documents | LoanDocumentController@store | loans.documents.store | manage_loan_documents |
| DELETE | /loans/{loan}/documents/{document} | LoanDocumentController@destroy | loans.documents.destroy | manage_loan_documents |
| POST | /loans/{loan}/documents/{document}/upload | LoanDocumentController@upload | loans.documents.upload | upload_loan_documents |
| GET | /loans/{loan}/documents/{document}/download | LoanDocumentController@download | loans.documents.download | download_loan_documents |
| DELETE | /loans/{loan}/documents/{document}/file | LoanDocumentController@deleteFile | loans.documents.deleteFile | delete_loan_files |

---

## Remarks (auth)

| Method | URI | Controller@Method | Name | Permission |
|--------|-----|-------------------|------|------------|
| GET | /loans/{loan}/remarks | LoanRemarkController@index | loans.remarks.index | view_loans |
| POST | /loans/{loan}/remarks | LoanRemarkController@store | loans.remarks.store | add_remarks |

---

## Loan Settings (auth + permission:manage_workflow_config)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /loan-settings | LoanSettingsController@index | loan-settings.index |
| POST | /loan-settings/banks | WorkflowConfigController@storeBank | loan-settings.banks.store |
| DELETE | /loan-settings/banks/{bank} | WorkflowConfigController@destroyBank | loan-settings.banks.destroy |
| POST | /loan-settings/products | WorkflowConfigController@storeProduct | loan-settings.products.store |
| GET | /loan-settings/products/{product}/stages | WorkflowConfigController@productStages | loan-settings.product-stages |
| POST | /loan-settings/products/{product}/stages | WorkflowConfigController@saveProductStages | loan-settings.product-stages.save |
| POST | /loan-settings/products/{product}/locations | WorkflowConfigController@saveProductLocations | loan-settings.product-locations.save |
| POST | /loan-settings/branches | WorkflowConfigController@storeBranch | loan-settings.branches.store |
| DELETE | /loan-settings/branches/{branch} | WorkflowConfigController@destroyBranch | loan-settings.branches.destroy |
| DELETE | /loan-settings/products/{product} | WorkflowConfigController@destroyProduct | loan-settings.products.destroy |
| POST | /loan-settings/master-stages | LoanSettingsController@saveMasterStages | loan-settings.master-stages.save |
| POST | /loan-settings/locations | LoanSettingsController@storeLocation | loan-settings.locations.store |
| DELETE | /loan-settings/locations/{location} | LoanSettingsController@destroyLocation | loan-settings.locations.destroy |
| POST | /loan-settings/task-role-permissions | LoanSettingsController@saveTaskRolePermissions | loan-settings.task-role-permissions.save |

---

## General Tasks (auth, no permission gate)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /general-tasks | GeneralTaskController@index | general-tasks.index |
| GET | /general-tasks/data | GeneralTaskController@taskData | general-tasks.data |
| GET | /general-tasks/search-loans | GeneralTaskController@searchLoans | general-tasks.search-loans |
| POST | /general-tasks | GeneralTaskController@store | general-tasks.store |
| GET | /general-tasks/{task} | GeneralTaskController@show | general-tasks.show |
| PUT | /general-tasks/{task} | GeneralTaskController@update | general-tasks.update |
| DELETE | /general-tasks/{task} | GeneralTaskController@destroy | general-tasks.destroy |
| PATCH | /general-tasks/{task}/status | GeneralTaskController@updateStatus | general-tasks.update-status |
| POST | /general-tasks/{task}/comments | GeneralTaskController@storeComment | general-tasks.comments.store |
| DELETE | /general-tasks/{task}/comments/{comment} | GeneralTaskController@destroyComment | general-tasks.comments.destroy |

---

## DVR (auth + permission:view_dvr/create_dvr/edit_dvr/delete_dvr)

| Method | URI | Controller@Method | Name | Permission |
|--------|-----|-------------------|------|------------|
| GET | /dvr | DailyVisitReportController@index | dvr.index | view_dvr |
| GET | /dvr/data | DailyVisitReportController@dvrData | dvr.data | view_dvr |
| GET | /dvr/search-loans | DailyVisitReportController@searchLoans | dvr.search-loans | view_dvr |
| GET | /dvr/search-quotations | DailyVisitReportController@searchQuotations | dvr.search-quotations | view_dvr |
| GET | /dvr/search-contacts | DailyVisitReportController@searchContacts | dvr.search-contacts | view_dvr |
| GET | /dvr/{dvr} | DailyVisitReportController@show | dvr.show | view_dvr |
| POST | /dvr | DailyVisitReportController@store | dvr.store | create_dvr |
| PUT | /dvr/{dvr} | DailyVisitReportController@update | dvr.update | edit_dvr |
| PATCH | /dvr/{dvr}/follow-up-done | DailyVisitReportController@markFollowUpDone | dvr.follow-up-done | edit_dvr |
| DELETE | /dvr/{dvr} | DailyVisitReportController@destroy | dvr.destroy | delete_dvr |

---

## Notifications (auth)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /notifications | NotificationController@index | notifications.index |
| GET | /api/notifications/count | NotificationController@unreadCount | api.notifications.count |
| POST | /notifications/{notification}/read | NotificationController@markRead | notifications.read |
| POST | /notifications/read-all | NotificationController@markAllRead | notifications.read-all |

---

## Reports (auth, data scoped by role in controller)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /reports/turnaround | ReportController@turnaround | reports.turnaround |
| GET | /reports/turnaround/data | ReportController@turnaroundData | reports.turnaround.data |

---

## Activity Log (auth + permission:view_activity_log)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /activity-log | DashboardController@activityLog | activity-log |
| GET | /activity-log/data | DashboardController@activityLogData | activity-log.data |

---

## Impersonation (auth)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| GET | /api/impersonate/users | ImpersonateController@users | impersonate.users |
| GET | /impersonate/take/{id} | ImpersonateController@take | impersonate.take |
| GET | /impersonate/leave | ImpersonateController@leave | impersonate.leave |

---

## Authenticated API (auth)

| Method | URI | Controller@Method | Name |
|--------|-----|-------------------|------|
| POST | /api/sync | SyncApiController@sync | api.sync |
| POST | /api/notes | NotesApiController@save | api.notes.save |
