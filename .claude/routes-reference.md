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
| PATCH | `/users/{user}/toggle-active` | UserController@toggleActive | edit_users |
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
