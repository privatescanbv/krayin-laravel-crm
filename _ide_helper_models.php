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
 * @property int $id
 * @property string|null $street
 * @property string $house_number
 * @property string $postal_code
 * @property string|null $house_number_suffix
 * @property string|null $state
 * @property string|null $city
 * @property string|null $country
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read mixed $full_address
 * @property-read string $multiline_address
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\AddressFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Address newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Address newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Address query()
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereHouseNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereHouseNumberSuffix($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Address whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAddress {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $name
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $description
 * @property bool $deleted
 * @property string|null $team_id
 * @property string|null $team_set_id
 * @property string|null $comment_clinic
 * @property int|null $height
 * @property int|null $weight
 * @property bool|null $metals
 * @property string|null $metals_notes
 * @property bool|null $medications
 * @property string|null $medications_notes
 * @property bool|null $glaucoma
 * @property string|null $glaucoma_notes
 * @property bool|null $claustrophobia
 * @property bool|null $dormicum
 * @property bool|null $heart_surgery
 * @property string|null $heart_surgery_notes
 * @property bool|null $implant
 * @property string|null $implant_notes
 * @property bool|null $surgeries
 * @property string|null $surgeries_notes
 * @property string|null $remarks
 * @property bool|null $hereditary_heart
 * @property string|null $hereditary_heart_notes
 * @property bool|null $hereditary_vascular
 * @property string|null $hereditary_vascular_notes
 * @property bool|null $hereditary_tumors
 * @property string|null $hereditary_tumors_notes
 * @property bool|null $allergies
 * @property string|null $allergies_notes
 * @property bool|null $back_problems
 * @property string|null $back_problems_notes
 * @property bool|null $heart_problems
 * @property string|null $heart_problems_notes
 * @property bool|null $smoking
 * @property string|null $smoking_notes
 * @property bool|null $diabetes
 * @property bool $spijsverteringsklachten
 * @property string|null $digestive_complaints_notes
 * @property string|null $diabetes_notes
 * @property bool|null $digestive_problems
 * @property string|null $digestive_problems_notes
 * @property string|null $heart_attack_risk
 * @property bool|null $active
 * @property string|null $advice_notes
 * @property int|null $lead_id
 * @property int|null $sales_id
 * @property int|null $person_id
 * @property string|null $gvl_form_link
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read string $label
 * @property-read \Webkul\Lead\Models\Lead|null $lead
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @property-read \App\Models\SalesLead|null $sales
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\AnamnesisFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis query()
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereAdviceNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereAllergies($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereAllergiesNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereBackProblems($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereBackProblemsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereClaustrophobia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereCommentClinic($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDiabetes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDiabetesNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDigestiveComplaintsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDigestiveProblems($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDigestiveProblemsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereDormicum($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereGlaucoma($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereGlaucomaNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereGvlFormLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHeartAttackRisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHeartProblems($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHeartProblemsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHeartSurgery($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHeartSurgeryNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHereditaryHeart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHereditaryHeartNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHereditaryTumors($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHereditaryTumorsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHereditaryVascular($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereHereditaryVascularNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereImplant($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereImplantNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereMedications($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereMedicationsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereMetals($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereMetalsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereSalesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereSmoking($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereSmokingNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereSpijsverteringsklachten($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereSurgeries($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereSurgeriesNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereTeamSetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Anamnesis whereWeight($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAnamnesis {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \App\Enums\CallStatus $status
 * @property string|null $omschrijving
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $activity_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\Activity\Models\Activity $activity
 * @property-read \App\Models\User|null $creator
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereOmschrijving($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CallStatus whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperCallStatus {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $registration_form_clinic_name
 * @property string|null $website_url
 * @property string|null $order_confirmation_note
 * @property string|null $external_id
 * @property bool $is_active
 * @property \Illuminate\Database\Eloquent\Collection<int, \Webkul\Email\Models\Email> $emails
 * @property array|null $phones
 * @property int|null $visit_address_id
 * @property int|null $postal_address_id
 * @property bool $is_postal_address_same_as_visit_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Activity\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read int|null $emails_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartnerProduct> $partnerProducts
 * @property-read int|null $partner_products_count
 * @property-read \App\Models\Address|null $postalAddress
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Resource> $resources
 * @property-read int|null $resources_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @property-read \App\Models\Address|null $visitAddress
 * @method static \Database\Factories\ClinicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic query()
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereEmails($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereIsPostalAddressSameAsVisitAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereOrderConfirmationNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic wherePhones($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic wherePostalAddressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereRegistrationFormClinicName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereVisitAddressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Clinic whereWebsiteUrl($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperClinic {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\User\Models\Group> $groups
 * @property-read int|null $groups_count
 * @method static \Database\Factories\DepartmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Department newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Department newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Department query()
 * @method static \Illuminate\Database\Eloquent\Builder|Department whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Department whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Department whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Department whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperDepartment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \App\Enums\DuplicateEntityType $entity_type
 * @property int $entity_id_1
 * @property int $entity_id_2
 * @property string|null $reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive query()
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive whereEntityId1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive whereEntityId2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DuplicateFalsePositive whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperDuplicateFalsePositive {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $sync_type
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int $processed_count
 * @property int $error_count
 * @property string|null $error_message
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereErrorCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereProcessedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereSyncType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperEmailLog {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $import_run_id
 * @property string $level
 * @property string $message
 * @property array|null $context
 * @property string|null $record_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ImportRun $importRun
 * @method static \Database\Factories\ImportLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereImportRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperImportLog {}
}

namespace App\Models{
/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string $status
 * @property string|null $import_type
 * @property int $records_processed
 * @property int $records_imported
 * @property int $records_skipped
 * @property int $records_errored
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ImportLog> $importLogs
 * @property-read int|null $import_logs_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ImportRunFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun query()
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereImportType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereRecordsErrored($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereRecordsImported($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereRecordsProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereRecordsSkipped($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ImportRun whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperImportRun {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property int $sales_lead_id
 * @property int|null $user_id
 * @property bool $combine_order
 * @property string $total_price
 * @property string|null $confirmation_letter_content
 * @property int|null $pipeline_stage_id
 * @property \Illuminate\Support\Carbon|null $first_examination_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Activity\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read int $open_activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderCheck> $orderChecks
 * @property-read int|null $order_checks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read \App\Models\SalesLead $salesLead
 * @property-read \Webkul\Lead\Models\Stage|null $stage
 * @property-read \Webkul\User\Models\User|null $updater
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|Order appointmentEligible()
 * @method static \Illuminate\Database\Eloquent\Builder|Order appointmentTimeFilter(?string $filter, \Carbon\Carbon $now)
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Order forPerson(\Webkul\Contact\Models\Person $person)
 * @method static \Illuminate\Database\Eloquent\Builder|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereCombineOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereConfirmationLetterContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereFirstExaminationAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order wherePipelineStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereSalesLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Order whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrder {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property string $name
 * @property bool $done
 * @property bool $removable
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \App\Models\Order $order
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck query()
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereDone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereRemovable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderCheck whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrderCheck {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int|null $person_id
 * @property int $quantity
 * @property string $total_price
 * @property \App\Enums\OrderItemStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read string $can_plan
 * @property-read \App\Models\Order $order
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @property-read \Webkul\Product\Models\Product $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ResourceOrderItem> $resourceOrderItems
 * @property-read int|null $resource_order_items_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\OrderItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OrderItem whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrderItem {}
}

namespace App\Models{
/**
 * Note; sales prices only used as back-up. Use Product sales price
 *
 * @property int $id
 * @property int|null $product_id
 * @property string $currency
 * @property string $sales_price
 * @property string|null $related_sales_price
 * @property string $name
 * @property string|null $external_id
 * @property bool $active
 * @property string|null $description
 * @property string|null $discount_info
 * @property int|null $resource_type_id
 * @property string|null $clinic_description
 * @property int|null $duration
 * @property string $purchase_price_misc
 * @property string $purchase_price_doctor
 * @property string $purchase_price_cardiology
 * @property string $purchase_price_clinic
 * @property string $purchase_price_radiology
 * @property string $purchase_price
 * @property array|null $reporting
 * @property string|null $rel_purchase_price_misc
 * @property string|null $rel_purchase_price_doctor
 * @property string|null $rel_purchase_price_cardiology
 * @property string|null $rel_purchase_price_clinic
 * @property string|null $rel_purchase_price_radiology
 * @property string|null $rel_purchase_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Clinic> $clinics
 * @property-read int|null $clinics_count
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\Product\Models\Product|null $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartnerProduct> $relatedProducts
 * @property-read int|null $related_products_count
 * @property-read \App\Models\ResourceType|null $resourceType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Resource> $resources
 * @property-read int|null $resources_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\PartnerProductFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereClinicDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereDiscountInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct wherePurchasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct wherePurchasePriceCardiology($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct wherePurchasePriceClinic($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct wherePurchasePriceDoctor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct wherePurchasePriceMisc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct wherePurchasePriceRadiology($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereRelPurchasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereRelPurchasePriceCardiology($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereRelPurchasePriceClinic($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereRelPurchasePriceDoctor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereRelPurchasePriceMisc($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereRelPurchasePriceRadiology($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereRelatedSalesPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereReporting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereResourceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereSalesPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PartnerProduct withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPartnerProduct {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $activity_id
 * @property int $person_id
 * @property \App\Enums\PatientMessageSenderType $sender_type
 * @property int|null $sender_id
 * @property string $body
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\Activity\Models\Activity|null $activity
 * @property-read \Webkul\Contact\Models\Person $person
 * @property-read \Webkul\User\Models\User|null $sender
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereSenderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMessage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPatientMessage {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $patient_id
 * @property string $type
 * @property bool $dismissable
 * @property string $title
 * @property string|null $summary
 * @property \App\Enums\NotificationReferenceType $reference_type
 * @property int $reference_id
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $dismissed_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_notified_by_email_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\Contact\Models\Person $patient
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\PatientNotificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification forMailNotification()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification forPatient(int $patientId)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereDismissable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereDismissedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereLastNotifiedByEmailAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereReferenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereReferenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientNotification whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPatientNotification {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $person_id
 * @property string $key
 * @property array $value
 * @property string $value_type
 * @property bool $is_system_managed
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read mixed|null $typed_value
 * @property-read \Webkul\Contact\Models\Person $person
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference query()
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereIsSystemManaged($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonPreference whereValueType($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPersonPreference {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $external_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ProductTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType query()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductType whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProductType {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $external_id
 * @property int $resource_type_id
 * @property int|null $clinic_id
 * @property bool $is_active
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \App\Models\Clinic|null $clinic
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartnerProduct> $partnerProducts
 * @property-read int|null $partner_products_count
 * @property-read \App\Models\ResourceType $resourceType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift> $shifts
 * @property-read int|null $shifts_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ResourceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Resource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Resource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Resource query()
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereClinicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereResourceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Resource whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperResource {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $resource_id
 * @property int $orderitem_id
 * @property \Illuminate\Support\Carbon $from
 * @property \Illuminate\Support\Carbon $to
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \App\Models\OrderItem $orderItem
 * @property-read \App\Models\Resource $resource
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ResourceOrderItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereOrderitemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceOrderItem whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperResourceOrderItem {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $external_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ResourceTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType query()
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResourceType whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperResourceType {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \App\Enums\LostReason|null $lost_reason
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int $pipeline_stage_id
 * @property int|null $lead_id
 * @property int|null $quote_id
 * @property int|null $user_id
 * @property int|null $contact_person_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \App\Enums\WorkflowType $workflow_type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Activity\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Anamnesis> $anamnesisRecords
 * @property-read int|null $anamnesis_records_count
 * @property-read \Webkul\Contact\Models\Person|null $contactPerson
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Email\Models\Email> $emails
 * @property-read int|null $emails_count
 * @property-read mixed $anamnesis
 * @property-read int|null $days_until_due_date
 * @property-read int $duplicates_count
 * @property-read bool $has_diagnosis_form
 * @property-read bool $has_duplicates
 * @property-read string|null $lost_reason_label
 * @property-read string|null $mri_status
 * @property-read string|null $mri_status_label
 * @property-read int $open_activities_count
 * @property-read int|null $persons_count
 * @property-read int $rotten_days
 * @property-read int $unread_emails_count
 * @property-read \Webkul\Lead\Models\Lead|null $lead
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Contact\Models\Person> $persons
 * @property-read \Webkul\Lead\Models\Stage|null $stage
 * @property-read \Webkul\User\Models\User|null $updater
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\SalesLeadFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead query()
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead resolveDepartment(int $salesId)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereContactPersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereLostReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead wherePipelineStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereQuoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SalesLead whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSalesLead {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $resource_id
 * @property string|null $notes
 * @property bool $available
 * @property \Illuminate\Support\Carbon|null $period_start
 * @property \Illuminate\Support\Carbon|null $period_end
 * @property array|null $weekday_time_blocks
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \App\Models\Resource $resource
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ShiftFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Shift newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift query()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereWeekdayTimeBlocks($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperShift {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $external_id
 * @property string|null $keycloak_user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string|null $password
 * @property int $status
 * @property string|null $view_permission
 * @property string|null $signature
 * @property int $role_id
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $image
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property string $name
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Webkul\User\Models\Role $role
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereKeycloakUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereSignature($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereViewPermission($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

