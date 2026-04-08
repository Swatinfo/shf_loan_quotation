# Routes Reference

## Web Routes (`routes/web.php`)

### Public
| Method | URI | Action | Middleware |
|--------|-----|--------|-----------|
| GET | `/` | redirect → dashboard | - |

### Dashboard & Activity
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/dashboard` | DashboardController@index | auth |
| GET | `/dashboard/quotation-data` | DashboardController@quotationData | auth |
| GET | `/activity-log` | DashboardController@activityLog | view_activity_log |

### Quotations
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/quotations/create` | QuotationController@create | create_quotation |
| POST | `/quotations/generate` | QuotationController@generate | generate_pdf |
| GET | `/quotations/{quotation}` | QuotationController@show | auth (own or view_all) |
| GET | `/quotations/{quotation}/download` | QuotationController@download | download_pdf |
| GET | `/download-pdf` | QuotationController@downloadByFilename | download_pdf |
| DELETE | `/quotations/{quotation}` | QuotationController@destroy | delete_quotations |

### Users
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/users` | UserController@index | view_users |
| GET | `/users/create` | UserController@create | create_users |
| POST | `/users` | UserController@store | create_users |
| GET | `/users/{user}/edit` | UserController@edit | edit_users |
| PUT | `/users/{user}` | UserController@update | edit_users |
| POST | `/users/{user}/toggle-active` | UserController@toggleActive | edit_users |
| DELETE | `/users/{user}` | UserController@destroy | delete_users |

### Permissions
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/permissions` | PermissionController@index | manage_permissions |
| PUT | `/permissions` | PermissionController@update | manage_permissions |

### Settings
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/settings` | SettingsController@index | view_settings |
| POST | `/settings/company` | updateCompany | edit_company_info |
| POST | `/settings/banks` | updateBanks | edit_banks |
| POST | `/settings/tenures` | updateTenures | edit_tenures |
| POST | `/settings/documents` | updateDocuments | edit_documents |
| POST | `/settings/charges` | updateCharges | edit_charges |
| POST | `/settings/bank-charges` | updateBankCharges | edit_charges |
| POST | `/settings/services` | updateServices | edit_services |
| POST | `/settings/gst` | updateGst | edit_gst |
| POST | `/settings/reset` | reset | view_settings |

### Profile
| Method | URI | Action |
|--------|-----|--------|
| GET | `/profile` | ProfileController@edit |
| PATCH | `/profile` | ProfileController@update |
| DELETE | `/profile` | ProfileController@destroy |

### Loans (Phase 2-5)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/loans` | LoanController@index | view_loans |
| GET | `/loans/data` | LoanController@loanData | view_loans |
| GET | `/loans/create` | LoanController@create | create_loan |
| POST | `/loans` | LoanController@store | create_loan |
| GET | `/loans/{loan}` | LoanController@show | view_loans |
| GET | `/loans/{loan}/timeline` | LoanController@timeline | view_loans |
| GET | `/loans/{loan}/edit` | LoanController@edit | edit_loan |
| PUT | `/loans/{loan}` | LoanController@update | edit_loan |
| POST | `/loans/{loan}/status` | LoanController@updateStatus | edit_loan |
| DELETE | `/loans/{loan}` | LoanController@destroy | delete_loan |

### Quotation-to-Loan Conversion (Phase 2)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/quotations/{quotation}/convert` | LoanConversionController@showConvertForm | convert_to_loan |
| POST | `/quotations/{quotation}/convert` | LoanConversionController@convert | convert_to_loan |

### Document Collection (Phase 4)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/loans/{loan}/documents` | LoanDocumentController@index | view_loans |
| POST | `/loans/{loan}/documents/{document}/status` | LoanDocumentController@updateStatus | manage_loan_documents |
| POST | `/loans/{loan}/documents` | LoanDocumentController@store | manage_loan_documents |
| DELETE | `/loans/{loan}/documents/{document}` | LoanDocumentController@destroy | manage_loan_documents |

### Stage Workflow (Phase 5)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/loans/{loan}/stages` | LoanStageController@index | view_loans |
| GET | `/loans/{loan}/transfers` | LoanStageController@transferHistory | view_loans |
| POST | `/loans/{loan}/stages/{stageKey}/status` | LoanStageController@updateStatus | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/assign` | LoanStageController@assign | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/transfer` | LoanStageController@transfer | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/reject` | LoanStageController@reject | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/query` | LoanStageController@raiseQuery | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/notes` | LoanStageController@saveNotes | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/skip` | LoanStageController@skip | skip_loan_stages |
| POST | `/loans/queries/{query}/respond` | LoanStageController@respondToQuery | manage_loan_stages |
| POST | `/loans/queries/{query}/resolve` | LoanStageController@resolveQuery | manage_loan_stages |

### Disbursement (Phase 7a)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/loans/{loan}/disbursement` | LoanDisbursementController@show | manage_loan_stages |
| POST | `/loans/{loan}/disbursement` | LoanDisbursementController@store | manage_loan_stages |
| POST | `/loans/{loan}/disbursement/clear-otc` | LoanDisbursementController@clearOtc | manage_loan_stages |

### Loan Settings (Phase 8 — replaces Phase 7b /settings/workflow)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/loan-settings` | LoanSettingsController@index | view_loans |
| POST | `/loan-settings/banks` | WorkflowConfigController@storeBank | manage_workflow_config |
| DELETE | `/loan-settings/banks/{bank}` | WorkflowConfigController@destroyBank | manage_workflow_config |
| POST | `/loan-settings/products` | WorkflowConfigController@storeProduct | manage_workflow_config |
| GET | `/loan-settings/products/{product}/stages` | WorkflowConfigController@productStages | manage_workflow_config |
| POST | `/loan-settings/products/{product}/stages` | WorkflowConfigController@saveProductStages | manage_workflow_config |
| POST | `/loan-settings/branches` | WorkflowConfigController@storeBranch | manage_workflow_config |
| DELETE | `/loan-settings/branches/{branch}` | WorkflowConfigController@destroyBranch | manage_workflow_config |
| DELETE | `/loan-settings/products/{product}` | WorkflowConfigController@destroyProduct | manage_workflow_config |
| POST | `/loan-settings/users/{user}/role` | LoanSettingsController@updateUserRole | manage_workflow_config |
| POST | `/loan-settings/master-stages` | LoanSettingsController@saveMasterStages | manage_workflow_config |

### Valuation (Phase 6a)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/loans/{loan}/valuation` | LoanValuationController@show | manage_loan_stages |
| POST | `/loans/{loan}/valuation` | LoanValuationController@store | manage_loan_stages |

### Remarks (Phase 6b)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/loans/{loan}/remarks` | LoanRemarkController@index | view_loans |
| POST | `/loans/{loan}/remarks` | LoanRemarkController@store | add_remarks |

### Notifications (Phase 6b)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/notifications` | NotificationController@index | auth |
| GET | `/api/notifications/count` | NotificationController@unreadCount | auth |
| POST | `/notifications/{notification}/read` | NotificationController@markRead | auth |
| POST | `/notifications/read-all` | NotificationController@markAllRead | auth |

### Impersonation (Phase 0)
| Method | URI | Action | Permission |
|--------|-----|--------|-----------|
| GET | `/api/impersonate/users` | ImpersonateController@users | canImpersonate() |
| GET | `/impersonate/take/{id}/{guard?}` | Package: ImpersonateController@take | canImpersonate() |
| GET | `/impersonate/leave` | Package: ImpersonateController@leave | — |

## API Routes (`routes/api.php`)

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| GET | `/api/config/public` | ConfigApiController@public | none |
| GET | `/api/notes` | NotesApiController@get | none |

## Session-Auth API Routes (`routes/web.php`)

| Method | URI | Action | Auth |
|--------|-----|--------|------|
| POST | `/api/sync` | SyncApiController@sync | session |
| POST | `/api/notes` | NotesApiController@save | session |

## Auth Routes (`routes/auth.php`)
Standard Laravel Breeze routes: login, logout, register (disabled), forgot-password, reset-password, verify-email, confirm-password, password update.
