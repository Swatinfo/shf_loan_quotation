<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * Extends Spatie's Activity model so the `activity_log` table is used under
 * the hood while the existing `ActivityLog::log()` call sites keep working.
 *
 * Custom columns `ip_address` and `user_agent` are preserved in the new table
 * alongside Spatie's standard columns (causer_*, subject_*, properties, etc.).
 *
 * @property int $id
 * @property string|null $log_name
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $event
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property \Illuminate\Support\Collection<array-key, mixed>|null $attribute_changes
 * @property \Illuminate\Support\Collection<array-key, mixed>|null $properties
 * @property string|null $batch_uuid
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $causer
 * @property-read string|null $action
 * @property-read int|null $user_id
 * @property-read \Illuminate\Database\Eloquent\Model|null $subject
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog causedBy(\Illuminate\Database\Eloquent\Model $causer)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog forEvent(\Spatie\Activitylog\Enums\ActivityEvent|string $event)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog forSubject(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog inLog(array|string ...$logNames)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereAttributeChanges($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereBatchUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCauserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCauserType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereEvent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereLogName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereSubjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityLog whereUserAgent($value)
 */
	class ActivityLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $config_key
 * @property array<array-key, mixed>|null $config_json
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig whereConfigJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig whereConfigKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppConfig whereUpdatedAt($value)
 */
	class AppConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $employees
 * @property-read int|null $employees_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankStageConfig> $stageConfigs
 * @property-read int|null $stage_configs_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bank withoutTrashed()
 */
	class Bank extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $bank_name
 * @property numeric $pf
 * @property int $admin
 * @property int $stamp_notary
 * @property int $registration_fee
 * @property int $advocate
 * @property int $tc
 * @property string|null $extra1_name
 * @property int $extra1_amt
 * @property string|null $extra2_name
 * @property int $extra2_amt
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereAdvocate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereExtra1Amt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereExtra1Name($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereExtra2Amt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereExtra2Name($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge wherePf($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereRegistrationFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereStampNotary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereTc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankCharge whereUpdatedAt($value)
 */
	class BankCharge extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $bank_id
 * @property int $stage_id
 * @property string|null $assigned_role
 * @property array<array-key, mixed>|null $phase_roles
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Bank|null $bank
 * @property-read \App\Models\Stage $stage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig whereAssignedRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig whereBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig wherePhaseRoles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig whereStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStageConfig whereUpdatedAt($value)
 */
	class BankStageConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 * @property string|null $city
 * @property string|null $phone
 * @property bool $is_active
 * @property int|null $manager_id
 * @property int|null $location_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property-read \App\Models\Location|null $location
 * @property-read \App\Models\User|null $manager
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereManagerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch withoutTrashed()
 */
	class Branch extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $customer_name
 * @property string|null $mobile
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $pan_number
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $pan_active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerKycDetail> $kycDetails
 * @property-read int|null $kyc_details_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanDetail> $loans
 * @property-read int|null $loans_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer visibleTo(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereMobile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePanActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePanNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withoutTrashed()
 */
	class Customer extends \Eloquent {}
}

namespace App\Models{
/**
 * Per-loan KYC snapshot captured at loan creation/edit. The linked Customer is
 * the identity anchor (by PAN); this row holds the details as entered for one
 * loan and is what the loan displays.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $loan_id
 * @property int|null $quotation_id
 * @property string $customer_name
 * @property string|null $mobile
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $pan_number
 * @property string $source
 * @property int|null $captured_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $capturedBy
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\Quotation|null $quotation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereCapturedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereMobile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail wherePanNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereQuotationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerKycDetail withoutTrashed()
 */
	class CustomerKycDetail extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $visit_date
 * @property string $contact_name
 * @property string|null $contact_phone
 * @property string $contact_type
 * @property string $purpose
 * @property string|null $notes
 * @property string|null $outcome
 * @property bool $follow_up_needed
 * @property \Illuminate\Support\Carbon|null $follow_up_date
 * @property string|null $follow_up_notes
 * @property bool $is_follow_up_done
 * @property int|null $parent_visit_id
 * @property int|null $follow_up_visit_id
 * @property int|null $quotation_id
 * @property int|null $loan_id
 * @property int|null $branch_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read DailyVisitReport|null $followUpVisit
 * @property-read bool $is_overdue_follow_up
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read DailyVisitReport|null $parentVisit
 * @property-read \App\Models\Quotation|null $quotation
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport overdueFollowUps()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport pendingFollowUps()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport visibleTo(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereContactType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereFollowUpDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereFollowUpNeeded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereFollowUpNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereFollowUpVisitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereIsFollowUpDone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereOutcome($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereParentVisitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport wherePurpose($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereQuotationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DailyVisitReport whereVisitDate($value)
 */
	class DailyVisitReport extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property string|null $sound
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereSound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceToken whereUserId($value)
 */
	class DeviceToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $loan_id
 * @property string $disbursement_type
 * @property \Illuminate\Support\Carbon|null $disbursement_date
 * @property int|null $amount_disbursed
 * @property string|null $bank_account_number
 * @property string|null $ifsc_code
 * @property string|null $cheque_number
 * @property string|null $cheque_date
 * @property array<array-key, mixed>|null $cheques
 * @property array<array-key, mixed>|null $entries
 * @property string|null $dd_number
 * @property string|null $dd_date
 * @property int $is_otc
 * @property string|null $otc_branch
 * @property int $otc_cleared
 * @property string|null $otc_cleared_date
 * @property int|null $otc_cleared_by
 * @property string|null $reference_number
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisbursementEntry> $entryRows
 * @property-read int|null $entry_rows_count
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\User|null $otcClearedByUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereAmountDisbursed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereBankAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereChequeDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereChequeNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereCheques($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereDdDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereDdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereDisbursementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereDisbursementType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereEntries($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereIfscCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereIsOtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereOtcBranch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereOtcCleared($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereOtcClearedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereOtcClearedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementDetail whereUpdatedBy($value)
 */
	class DisbursementDetail extends \Eloquent {}
}

namespace App\Models{
/**
 * Normalized mirror of one tranche from `disbursement_details.entries` (json).
 *
 * The JSON is the form's source of truth; these rows exist for querying
 * (future payout calculation) and audit (updated_by / deleted_by / is_active).
 *
 * @property int $id
 * @property int $loan_id
 * @property int $disbursement_detail_id
 * @property \Illuminate\Support\Carbon|null $disbursement_date
 * @property string $method
 * @property int|null $product_id
 * @property string|null $product_name
 * @property string|null $loan_account_number
 * @property int $amount
 * @property string|null $cheque_name
 * @property string|null $cheque_number
 * @property string|null $cheque_date
 * @property bool $is_active
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $deletedByUser
 * @property-read \App\Models\DisbursementDetail $disbursement
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereChequeDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereChequeName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereChequeNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereDisbursementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereDisbursementDetailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereLoanAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DisbursementEntry withoutTrashed()
 */
	class DisbursementEntry extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $created_by
 * @property int|null $assigned_to
 * @property int|null $loan_detail_id
 * @property int|null $quotation_id
 * @property string $status
 * @property string $priority
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $assignee
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GeneralTaskComment> $comments
 * @property-read int|null $comments_count
 * @property-read \App\Models\User $creator
 * @property-read bool $is_overdue
 * @property-read string $priority_badge_html
 * @property-read string $status_badge_html
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\Quotation|null $quotation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask visibleTo(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereLoanDetailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereQuotationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTask withActiveLinks()
 */
	class GeneralTask extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $general_task_id
 * @property int $user_id
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GeneralTask $task
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment whereGeneralTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GeneralTaskComment whereUserId($value)
 */
	class GeneralTaskComment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $loan_number
 * @property int|null $quotation_id
 * @property int|null $customer_id
 * @property int|null $customer_kyc_details_id
 * @property int|null $branch_id
 * @property int|null $location_id
 * @property int|null $bank_id
 * @property int|null $product_id
 * @property string $customer_name
 * @property string $customer_type
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property string|null $pan_number
 * @property int $loan_amount
 * @property int|null $sanctioned_amount
 * @property int|null $disbursed_amount
 * @property string $status
 * @property bool $is_sanctioned
 * @property string $current_stage
 * @property string|null $bank_name
 * @property numeric|null $roi_min
 * @property numeric|null $roi_max
 * @property string|null $total_charges
 * @property string|null $application_number
 * @property int|null $assigned_bank_employee
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Illuminate\Support\Carbon|null $expected_docket_date
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejected_stage
 * @property string|null $rejection_reason
 * @property string|null $status_reason
 * @property \Illuminate\Support\Carbon|null $status_changed_at
 * @property int|null $status_changed_by
 * @property int|null $created_by
 * @property int|null $assigned_advisor
 * @property int|null $dme_user_id
 * @property string|null $notes
 * @property array<array-key, mixed>|null $workflow_config
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property-read \App\Models\User|null $advisor
 * @property-read \App\Models\Bank|null $bank
 * @property-read \App\Models\User|null $bankEmployee
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\CustomerKycDetail|null $customerKycDetails
 * @property-read \App\Models\DisbursementDetail|null $disbursement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DisbursementEntry> $disbursementEntries
 * @property-read int|null $disbursement_entries_count
 * @property-read \App\Models\User|null $dme
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanDocument> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\User|null $current_owner
 * @property-read string $current_stage_name
 * @property-read \App\Models\User|null $current_task_owner
 * @property-read \Illuminate\Support\Collection<int, User> $current_task_owners
 * @property-read string $customer_type_label
 * @property-read string $formatted_amount
 * @property-read string|null $formatted_disbursed_amount
 * @property-read string|null $formatted_sanctioned_amount
 * @property-read string $stage_badge_html
 * @property-read string $status_color
 * @property-read string $status_label
 * @property-read string $time_with_current_owner
 * @property-read string $total_loan_time
 * @property-read \App\Models\Location|null $location
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\LoanProgress|null $progress
 * @property-read \App\Models\Quotation|null $quotation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Remark> $remarks
 * @property-read int|null $remarks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StageAssignment> $stageAssignments
 * @property-read int|null $stage_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StageQuery> $stageQueries
 * @property-read int|null $stage_queries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StageTransfer> $stageTransfers
 * @property-read int|null $stage_transfers_count
 * @property-read \App\Models\User|null $statusChangedByUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ValuationDetail> $valuationDetails
 * @property-read int|null $valuation_details_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail open()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail visibleTo(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereApplicationNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereAssignedAdvisor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereAssignedBankEmployee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCurrentStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCustomerEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCustomerKycDetailsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCustomerPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereCustomerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereDisbursedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereDmeUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereExpectedDocketDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereIsSanctioned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereLoanAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereLoanNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail wherePanNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereQuotationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereRejectedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereRejectedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereRejectedStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereRoiMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereRoiMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereSanctionedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereStatusChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereStatusChangedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereStatusReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereTotalCharges($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail whereWorkflowConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDetail withoutTrashed()
 */
	class LoanDetail extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $loan_id
 * @property string $document_name_en
 * @property string|null $document_name_gu
 * @property bool $is_required
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $received_date
 * @property int|null $received_by
 * @property string|null $rejected_reason
 * @property string|null $notes
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $file_mime
 * @property int|null $uploaded_by
 * @property \Illuminate\Support\Carbon|null $uploaded_at
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\User|null $receivedByUser
 * @property-read \App\Models\User|null $uploadedByUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument received()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument required()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument resolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument unresolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereDocumentNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereDocumentNameGu($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereFileMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereReceivedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereReceivedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereRejectedReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereUploadedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanDocument whereUploadedBy($value)
 */
	class LoanDocument extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $loan_id
 * @property int $total_stages
 * @property int $completed_stages
 * @property numeric $overall_percentage
 * @property \Illuminate\Support\Carbon|null $estimated_completion
 * @property array<array-key, mixed>|null $workflow_snapshot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LoanDetail|null $loan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereCompletedStages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereEstimatedCompletion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereOverallPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereTotalStages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanProgress whereWorkflowSnapshot($value)
 */
	class LoanProgress extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $type
 * @property string|null $code
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $branches
 * @property-read int|null $branches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Location> $children
 * @property-read int|null $children_count
 * @property-read Location|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location cities()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location states()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereUpdatedAt($value)
 */
	class Location extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $group
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserPermission> $userPermissions
 * @property-read int|null $user_permissions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permission whereUpdatedAt($value)
 */
	class Permission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $bank_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_pf_based
 * @property numeric|null $max_payout_amount
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property-read \App\Models\Bank|null $bank
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductPayoutSlab> $payoutSlabs
 * @property-read int|null $payout_slabs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductStage> $productStages
 * @property-read int|null $product_stages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Stage> $stages
 * @property-read int|null $stages_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsPfBased($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereMaxPayoutAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withoutTrashed()
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property int $low_amount
 * @property int $high_amount
 * @property string $payout_type
 * @property float $payout_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab whereHighAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab whereLowAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab wherePayoutType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab wherePayoutValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductPayoutSlab whereUpdatedAt($value)
 */
	class ProductPayoutSlab extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_id
 * @property int $stage_id
 * @property bool $is_enabled
 * @property string|null $default_assignee_role
 * @property int|null $default_user_id
 * @property bool $auto_skip
 * @property bool $allow_skip
 * @property array<array-key, mixed>|null $sub_actions_override
 * @property int|null $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductStageUser> $branchUsers
 * @property-read int|null $branch_users_count
 * @property-read \App\Models\User|null $defaultUser
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\Stage $stage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereAllowSkip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereAutoSkip($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereDefaultAssigneeRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereDefaultUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereSubActionsOverride($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStage whereUpdatedBy($value)
 */
	class ProductStage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $product_stage_id
 * @property int|null $branch_id
 * @property int|null $location_id
 * @property int $user_id
 * @property int|null $phase_index
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\ProductStage $productStage
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser wherePhaseIndex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereProductStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductStageUser whereUserId($value)
 */
	class ProductStageUser extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $stage_query_id
 * @property string $response_text
 * @property int $responded_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User $respondedByUser
 * @property-read \App\Models\StageQuery $stageQuery
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse whereRespondedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse whereResponseText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueryResponse whereStageQueryId($value)
 */
	class QueryResponse extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $loan_id
 * @property string $status
 * @property string|null $hold_reason_key
 * @property string|null $hold_note
 * @property \Illuminate\Support\Carbon|null $hold_follow_up_date
 * @property \Illuminate\Support\Carbon|null $held_at
 * @property int|null $held_by
 * @property string|null $cancel_reason_key
 * @property string|null $cancel_note
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property int|null $location_id
 * @property int|null $branch_id
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_type
 * @property string|null $referral_name
 * @property string|null $referral_type
 * @property int $loan_amount
 * @property string|null $pdf_filename
 * @property string|null $pdf_path
 * @property string|null $additional_notes
 * @property string|null $prepared_by_name
 * @property string|null $prepared_by_mobile
 * @property array<array-key, mixed>|null $selected_tenures
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuotationBank> $banks
 * @property-read int|null $banks_count
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\User|null $cancelledBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuotationDocument> $documents
 * @property-read int|null $documents_count
 * @property-read string|null $cancel_reason_label
 * @property-read string $formatted_amount
 * @property-read string|null $hold_reason_label
 * @property-read bool $is_cancelled
 * @property-read bool $is_converted
 * @property-read bool $is_on_hold
 * @property-read string|null $referral_type_label
 * @property-read string $status_badge_html
 * @property-read \App\Models\User|null $heldBy
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\Location|null $location
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation cancelled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation onHold()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation visibleTo(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereAdditionalNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereCancelNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereCancelReasonKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereCancelledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereCustomerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereHeldAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereHeldBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereHoldFollowUpDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereHoldNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereHoldReasonKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereLoanAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereLocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation wherePdfFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation wherePdfPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation wherePreparedByMobile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation wherePreparedByName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereReferralName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereReferralType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereSelectedTenures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quotation withoutTrashed()
 */
	class Quotation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $quotation_id
 * @property string $bank_name
 * @property numeric $roi_min
 * @property numeric $roi_max
 * @property numeric $pf_charge
 * @property int $admin_charge
 * @property int $stamp_notary
 * @property int $registration_fee
 * @property int $advocate_fees
 * @property int $iom_charge
 * @property int $tc_report
 * @property string|null $extra1_name
 * @property int $extra1_amount
 * @property string|null $extra2_name
 * @property int $extra2_amount
 * @property int $total_charges
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuotationEmi> $emiEntries
 * @property-read int|null $emi_entries_count
 * @property-read \App\Models\Quotation|null $quotation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereAdminCharge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereAdvocateFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereExtra1Amount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereExtra1Name($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereExtra2Amount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereExtra2Name($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereIomCharge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank wherePfCharge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereQuotationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereRegistrationFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereRoiMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereRoiMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereStampNotary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereTcReport($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereTotalCharges($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationBank whereUpdatedAt($value)
 */
	class QuotationBank extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $quotation_id
 * @property string $document_name_en
 * @property string|null $document_name_gu
 * @property bool $is_excluded
 * @property int $sequence
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Quotation|null $quotation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereDocumentNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereDocumentNameGu($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereIsExcluded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereQuotationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereSequence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationDocument whereUpdatedAt($value)
 */
	class QuotationDocument extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $quotation_bank_id
 * @property int $tenure_years
 * @property int $monthly_emi
 * @property int $total_interest
 * @property int $total_payment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\QuotationBank $quotationBank
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereMonthlyEmi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereQuotationBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereTenureYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereTotalInterest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereTotalPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuotationEmi whereUpdatedAt($value)
 */
	class QuotationEmi extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $loan_id
 * @property string|null $stage_key
 * @property int $user_id
 * @property string $remark
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark forStage(string $key)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark general()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark whereRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark whereStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Remark whereUserId($value)
 */
	class Remark extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $can_be_advisor
 * @property bool $is_system
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role advisorEligible()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCanBeAdvisor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereIsSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role workflow()
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $message
 * @property string $type
 * @property bool $is_read
 * @property int|null $loan_id
 * @property string|null $stage_key
 * @property string|null $link
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification recent(int $limit = 50)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification unread()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShfNotification whereUserId($value)
 */
	class ShfNotification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $stage_key
 * @property bool $is_enabled
 * @property string $stage_name_en
 * @property string|null $stage_name_gu
 * @property int $sequence_order
 * @property bool $is_parallel
 * @property string|null $parent_stage_key
 * @property string $stage_type
 * @property string|null $description_en
 * @property string|null $description_gu
 * @property array<array-key, mixed>|null $default_role
 * @property string $assigned_role
 * @property array<array-key, mixed>|null $sub_actions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankStageConfig> $bankConfigs
 * @property-read int|null $bank_configs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Stage> $children
 * @property-read int|null $children_count
 * @property-read Stage|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage enabled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage mainStages()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage subStagesOf(string $parentKey)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereAssignedRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereDefaultRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereDescriptionEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereDescriptionGu($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereIsParallel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereParentStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereSequenceOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereStageNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereStageNameGu($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereStageType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereSubActions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stage whereUpdatedAt($value)
 */
	class Stage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $loan_id
 * @property string $stage_key
 * @property int|null $assigned_to
 * @property string $status
 * @property string|null $previous_status
 * @property string $priority
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $completed_by
 * @property bool $is_parallel_stage
 * @property string|null $parent_stage_key
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StageQuery> $activeQueries
 * @property-read int|null $active_queries_count
 * @property-read \App\Models\User|null $assignee
 * @property-read \App\Models\User|null $completedByUser
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StageQuery> $queries
 * @property-read int|null $queries_count
 * @property-read \App\Models\Stage|null $stage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StageTransfer> $transfers
 * @property-read int|null $transfers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment forUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment inProgress()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment mainStages()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment subStagesOf(string $parentKey)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereAssignedTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereCompletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereIsParallelStage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereParentStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment wherePreviousStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageAssignment whereUpdatedBy($value)
 */
	class StageAssignment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $stage_assignment_id
 * @property int $loan_id
 * @property string $stage_key
 * @property string $query_text
 * @property int $raised_by
 * @property int|null $assigned_to_user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $assignedToUser
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\User $raisedByUser
 * @property-read \App\Models\User|null $resolvedByUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QueryResponse> $responses
 * @property-read int|null $responses_count
 * @property-read \App\Models\StageAssignment $stageAssignment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery resolved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereAssignedToUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereQueryText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereRaisedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereResolvedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereStageAssignmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageQuery whereUpdatedAt($value)
 */
	class StageQuery extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $stage_assignment_id
 * @property int $loan_id
 * @property string $stage_key
 * @property int $transferred_from
 * @property int $transferred_to
 * @property string|null $reason
 * @property string $transfer_type
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\User $fromUser
 * @property-read \App\Models\LoanDetail|null $loan
 * @property-read \App\Models\StageAssignment $stageAssignment
 * @property-read \App\Models\User $toUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereStageAssignmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereStageKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereTransferType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereTransferredFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StageTransfer whereTransferredTo($value)
 */
	class StageTransfer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property int|null $created_by
 * @property string|null $phone
 * @property string|null $employee_id
 * @property int|null $default_branch_id
 * @property int|null $task_bank_id
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $branches
 * @property-read int|null $branches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $createdUsers
 * @property-read int|null $created_users_count
 * @property-read User|null $creator
 * @property-read \App\Models\Branch|null $defaultBranch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DeviceToken> $deviceTokens
 * @property-read int|null $device_tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bank> $employerBanks
 * @property-read int|null $employer_banks_count
 * @property-read string $role_label
 * @property-read array $role_slugs
 * @property-read string $workflow_role_label
 * @property-read string $workflow_role_label_gu
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Location> $locations
 * @property-read int|null $locations_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \NotificationChannels\WebPush\PushSubscription> $pushSubscriptions
 * @property-read int|null $push_subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\Bank|null $taskBank
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserPermission> $userPermissions
 * @property-read int|null $user_permissions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User advisorEligible()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDefaultBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTaskBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $permission_id
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Permission $permission
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPermission whereUserId($value)
 */
	class UserPermission extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $loan_id
 * @property string $valuation_type
 * @property string|null $property_address
 * @property string|null $landmark
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $property_type
 * @property string|null $land_area
 * @property numeric|null $land_rate
 * @property int|null $land_valuation
 * @property string|null $construction_area
 * @property numeric|null $construction_rate
 * @property int|null $construction_valuation
 * @property int|null $final_valuation
 * @property int|null $market_value
 * @property int|null $government_value
 * @property \Illuminate\Support\Carbon|null $valuation_date
 * @property string|null $valuator_name
 * @property string|null $valuator_report_number
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property-read \App\Models\LoanDetail|null $loan
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereConstructionArea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereConstructionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereConstructionValuation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereFinalValuation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereGovernmentValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereLandArea($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereLandRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereLandValuation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereLandmark($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereMarketValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail wherePropertyAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail wherePropertyType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereValuationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereValuationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereValuatorName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ValuationDetail whereValuatorReportNumber($value)
 */
	class ValuationDetail extends \Eloquent {}
}

