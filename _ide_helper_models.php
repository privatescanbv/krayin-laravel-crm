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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereHouseNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereHouseNumberSuffix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAddress {}
}

namespace App\Models{
/**
 * One email send to a clinic department; PDFs per patient are {@see AfbPersonDocument} rows.
 *
 * @property int $id
 * @property int $clinic_id
 * @property int|null $clinic_department_id
 * @property int|null $email_id
 * @property \App\Enums\AfbDispatchType $type
 * @property \App\Enums\AfbDispatchStatus $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $last_attempt_at
 * @property int $attempt
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Clinic $clinic
 * @property-read \App\Models\ClinicDepartment|null $clinicDepartment
 * @property-read \Webkul\Email\Models\Email|null $email
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AfbPersonDocument> $personDocuments
 * @property-read int|null $person_documents_count
 * @method static \Database\Factories\AfbDispatchFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereAttempt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereClinicDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereClinicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereEmailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereLastAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbDispatch whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAfbDispatch {}
}

namespace App\Models{
/**
 * One generated AFB PDF for a patient, linked to a CRM order and parent dispatch (email run).
 *
 * @property int $id
 * @property int $afb_dispatch_id
 * @property int $order_id
 * @property array<array-key, mixed>|null $order_item_ids
 * @property int|null $person_id
 * @property string|null $patient_name
 * @property string $file_name
 * @property string $file_path
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AfbDispatch $dispatch
 * @property-read \App\Models\Order $order
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @method static \Database\Factories\AfbPersonDocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereAfbDispatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereOrderItemIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument wherePatientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AfbPersonDocument whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperAfbPersonDocument {}
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
 * @property string|null $gvl_form_id
 * @property-read string|null $gvl_form_link
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read string $label
 * @property-read \Webkul\Lead\Models\Lead|null $lead
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @property-read \App\Models\SalesLead|null $sales
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\AnamnesisFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereAdviceNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereAllergies($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereAllergiesNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereBackProblems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereBackProblemsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereClaustrophobia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereCommentClinic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDiabetes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDiabetesNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDigestiveComplaintsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDigestiveProblems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDigestiveProblemsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereDormicum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereGlaucoma($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereGlaucomaNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereGvlFormLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHeartAttackRisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHeartProblems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHeartProblemsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHeartSurgery($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHeartSurgeryNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHereditaryHeart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHereditaryHeartNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHereditaryTumors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHereditaryTumorsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHereditaryVascular($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereHereditaryVascularNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereImplant($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereImplantNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereMedications($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereMedicationsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereMetals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereMetalsNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereSalesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereSmoking($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereSmokingNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereSpijsverteringsklachten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereSurgeries($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereSurgeriesNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereTeamSetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Anamnesis whereWeight($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereOmschrijving($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CallStatus whereUpdatedBy($value)
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
 * @property string|null $external_id
 * @property bool $is_active
 * @property \Illuminate\Database\Eloquent\Collection<int, \Webkul\Email\Models\Email> $emails
 * @property array<array-key, mixed>|null $phones
 * @property int|null $visit_address_id
 * @property int|null $postal_address_id
 * @property bool $is_postal_address_same_as_visit_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Activity\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AfbDispatch> $afbDispatches
 * @property-read int|null $afb_dispatches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AfbPersonDocument> $afbPersonDocuments
 * @property-read int|null $afb_person_documents_count
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClinicDepartment> $departments
 * @property-read int|null $departments_count
 * @property-read int|null $emails_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartnerProduct> $partnerProducts
 * @property-read int|null $partner_products_count
 * @property-read \App\Models\Address|null $postalAddress
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Resource> $resources
 * @property-read int|null $resources_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @property-read \App\Models\Address|null $visitAddress
 * @method static \Database\Factories\ClinicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereEmails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereIsPostalAddressSameAsVisitAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic wherePhones($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic wherePostalAddressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereRegistrationFormClinicName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereVisitAddressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Clinic whereWebsiteUrl($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperClinic {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $clinic_id
 * @property string $name
 * @property string|null $description
 * @property string|null $order_confirmation_note
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Clinic $clinic
 * @method static \Database\Factories\ClinicDepartmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereClinicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereOrderConfirmationNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClinicDepartment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperClinicDepartment {}
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive whereEntityId1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive whereEntityId2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DuplicateFalsePositive whereUpdatedBy($value)
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
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereErrorCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereProcessedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereSyncType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereUpdatedAt($value)
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
 * @property array<array-key, mixed>|null $context
 * @property string|null $record_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ImportRun $importRun
 * @method static \Database\Factories\ImportLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereImportRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportLog whereUpdatedAt($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereImportType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereRecordsErrored($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereRecordsImported($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereRecordsProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereRecordsSkipped($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportRun whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperImportRun {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $lead_id
 * @property string $key
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\Lead\Models\Lead|null $lead
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadMarketingData whereValue($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLeadMarketingData {}
}

namespace App\Models{
/**
 * @property int $lead_id
 * @property int $person_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadPerson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadPerson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadPerson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadPerson whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadPerson wherePersonId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperLeadPerson {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $external_id
 * @property string $order_number
 * @property string|null $invoice_number
 * @property bool $is_business
 * @property int|null $organization_id
 * @property string $title
 * @property int $sales_lead_id
 * @property int|null $user_id
 * @property int|null $clinic_coordinator_user_id
 * @property bool $combine_order
 * @property numeric $total_price
 * @property string|null $confirmation_letter_content
 * @property int|null $pipeline_stage_id
 * @property \App\Enums\LostReason|null $lost_reason
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $first_examination_at
 * @property string|null $first_examination_time
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Activity\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AfbPersonDocument> $afbPersonDocuments
 * @property-read int|null $afb_person_documents_count
 * @property-read \App\Models\User|null $clinicCoordinator
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read string|null $lost_reason_label
 * @property-read string $name
 * @property-read int $open_activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderCheck> $orderChecks
 * @property-read int|null $order_checks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read \Webkul\Contact\Models\Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderPayment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderPersonConfirmation> $personConfirmations
 * @property-read int|null $person_confirmations_count
 * @property-read \App\Models\SalesLead $salesLead
 * @property-read \Webkul\Lead\Models\Stage|null $stage
 * @property-read \Webkul\User\Models\User|null $updater
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order appointmentEligible()
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order forPerson(\Webkul\Contact\Models\Person $person)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereClinicCoordinatorUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCombineOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereConfirmationLetterContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereFirstExaminationAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereFirstExaminationTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereIsBusiness($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereLostReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePipelineStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSalesLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUserId($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereDone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereRemovable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderCheck whereUpdatedBy($value)
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
 * @property int|null $resource_type_id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $afb_description
 * @property int|null $person_id
 * @property int $quantity
 * @property numeric $total_price
 * @property string|null $currency
 * @property \App\Enums\OrderItemStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read string $can_plan
 * @property-read \App\Models\PurchasePrice|null $invoicePurchasePrice
 * @property-read \App\Models\Order $order
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @property-read \Webkul\Product\Models\Product $product
 * @property-read \App\Models\PurchasePrice|null $purchasePrice
 * @property-read \App\Models\ResourceOrderItem|null $resourceOrderItem
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ResourceOrderItem> $resourceOrderItems
 * @property-read int|null $resource_order_items_count
 * @property-read \App\Models\ResourceType|null $resourceType
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\OrderItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem forOrderAndNotLost(string $orderId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereAfbDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereResourceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem withPartnerProductCount()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrderItem {}
}

namespace App\Models{
/**
 * Patient Payments
 *
 * @property int $id
 * @property int $order_id
 * @property numeric $amount
 * @property \App\Enums\PaymentType $type
 * @property \App\Enums\PaymentMethod $method
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string $currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPayment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrderPayment {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $order_id
 * @property int $person_id
 * @property string|null $confirmation_letter_content
 * @property \Illuminate\Support\Carbon|null $email_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order $order
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation whereConfirmationLetterContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation whereEmailSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPersonConfirmation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperOrderPersonConfirmation {}
}

namespace App\Models{
/**
 * Note; sales prices only used as back-up. Use Product sales price
 *
 * @property int $id
 * @property int|null $product_id
 * @property string $currency
 * @property numeric $sales_price
 * @property numeric|null $related_sales_price
 * @property string $name
 * @property string|null $external_id
 * @property bool $active
 * @property string|null $description
 * @property string|null $discount_info
 * @property int|null $resource_type_id
 * @property string|null $clinic_description
 * @property int|null $duration
 * @property array<array-key, mixed>|null $reporting
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Clinic> $clinics
 * @property-read int|null $clinics_count
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Webkul\Product\Models\Product|null $product
 * @property-read \App\Models\PurchasePrice|null $purchasePrice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartnerProduct> $relatedProducts
 * @property-read int|null $related_products_count
 * @property-read \App\Models\PurchasePrice|null $relatedPurchasePrice
 * @property-read \App\Models\ResourceType|null $resourceType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Resource> $resources
 * @property-read int|null $resources_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\PartnerProductFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct forClinicAndProduct(int $clinicId, int $productId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereClinicDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereDiscountInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereRelatedSalesPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereReporting($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereResourceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereSalesPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartnerProduct withoutTrashed()
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
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @property-read \Webkul\User\Models\User|null $sender
 * @method static \Database\Factories\PatientMessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereActivityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereSenderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientMessage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPatientMessage {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $patient_id
 * @property bool $dismissable
 * @property string $title
 * @property string|null $summary
 * @property array<array-key, mixed>|null $entity_names
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
 * @property-read \Webkul\Contact\Models\Person|null $patient
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\PatientNotificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification forMailNotification()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification forPatient(int $patientId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereDismissable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereDismissedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereEntityNames($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereLastNotifiedByEmailAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereReferenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereReferenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PatientNotification whereUpdatedBy($value)
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
 * @property array<array-key, mixed> $value
 * @property string $value_type
 * @property bool $is_system_managed
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read mixed|null $typed_value
 * @property-read \Webkul\Contact\Models\Person|null $person
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereIsSystemManaged($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference wherePersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PersonPreference whereValueType($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductType whereUpdatedBy($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperProductType {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $priceable_type
 * @property int $priceable_id
 * @property \App\Enums\PurchasePriceType $type
 * @property numeric|null $purchase_price_misc
 * @property numeric|null $purchase_price_doctor
 * @property numeric|null $purchase_price_cardiology
 * @property numeric|null $purchase_price_clinic
 * @property numeric|null $purchase_price_radiology
 * @property numeric|null $purchase_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $priceable
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePriceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePriceableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePurchasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePurchasePriceCardiology($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePurchasePriceClinic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePurchasePriceDoctor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePurchasePriceMisc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice wherePurchasePriceRadiology($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchasePrice whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperPurchasePrice {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $external_id
 * @property int $resource_type_id
 * @property int|null $clinic_id
 * @property int|null $clinic_department_id
 * @property bool $is_active
 * @property bool $allow_outside_availability
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \App\Models\Clinic|null $clinic
 * @property-read \App\Models\ClinicDepartment|null $clinicDepartment
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartnerProduct> $partnerProducts
 * @property-read int|null $partner_products_count
 * @property-read \App\Models\ResourceType $resourceType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Shift> $shifts
 * @property-read int|null $shifts_count
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ResourceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereAllowOutsideAvailability($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereClinicDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereClinicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereResourceTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resource whereUpdatedBy($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem forAfbDispatch()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem onDate(\Carbon\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem onOrderItemId(string $orderItemId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereOrderitemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceOrderItem whereUpdatedBy($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResourceType whereUpdatedBy($value)
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
 * @property int|null $department_id
 * @property int|null $quote_id
 * @property int|null $user_id
 * @property int|null $contact_person_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Activity\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Anamnesis> $anamnesisRecords
 * @property-read int|null $anamnesis_records_count
 * @property-read \Webkul\Contact\Models\Person|null $contactPerson
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \App\Models\Department|null $department
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SalesLeadRelation> $incomingRelations
 * @property-read int|null $incoming_relations_count
 * @property-read \Webkul\Lead\Models\Lead|null $lead
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SalesLead> $linkedHerniaSales
 * @property-read int|null $linked_hernia_sales_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SalesLead> $linkedPreventieSales
 * @property-read int|null $linked_preventie_sales_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SalesLeadRelation> $outgoingRelations
 * @property-read int|null $outgoing_relations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Webkul\Contact\Models\Person> $persons
 * @property-read \Webkul\Lead\Models\Stage|null $stage
 * @property-read \Webkul\User\Models\User|null $updater
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\SalesLeadFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead resolveDepartment(int $salesId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereContactPersonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereLostReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead wherePipelineStageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereQuoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLead whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSalesLead {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $source_saleslead_id
 * @property int $target_saleslead_id
 * @property string $relation_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SalesLead $sourceSalesLead
 * @property-read \App\Models\SalesLead $targetSalesLead
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation whereRelationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation whereSourceSalesleadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation whereTargetSalesleadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesLeadRelation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperSalesLeadRelation {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $resource_id
 * @property string|null $notes
 * @property bool $available
 * @property \Illuminate\Support\Carbon|null $period_start
 * @property \Illuminate\Support\Carbon|null $period_end
 * @property array<array-key, mixed>|null $weekday_time_blocks
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Webkul\User\Models\User|null $creator
 * @property-read \App\Models\Resource $resource
 * @property-read \Webkul\User\Models\User|null $updater
 * @method static \Database\Factories\ShiftFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereWeekdayTimeBlocks($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereKeycloakUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSignature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereViewPermission($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}
