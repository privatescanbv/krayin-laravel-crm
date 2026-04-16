<?php

namespace App\Support;

use Webkul\Contact\Models\Person;

/**
 * Builds URLs to open a patient GVL form from the CRM, optionally via impersonation
 * (same rules as the admin `gvl-form-link` Blade component).
 */
final class GvlFormLink
{
    /**
     * @param  string|null  $gvlFormLink  Raw patient-portal form URL (e.g. https://patient.../patient/forms/85/step/1)
     * @return string|null URL to use in admin UI: impersonation wrapper when allowed, otherwise the raw link
     */
    public static function adminOpenUrl(?string $gvlFormLink, ?int $personId, bool $personHasPortalAccount): ?string
    {
        if ($gvlFormLink === null || $gvlFormLink === '') {
            return null;
        }

        $useImpersonation = $personId !== null
            && $personId > 0
            && $personHasPortalAccount
            && bouncer()->hasPermission('contacts.persons.impersonate');

        if (! $useImpersonation) {
            return $gvlFormLink;
        }

        return self::buildImpersonationWrapperUrl($personId, $gvlFormLink);
    }

    /**
     * CRM URL that starts impersonation and passes through to the patient portal (see ImpersonationController::impersonateAndOpenForm).
     */
    public static function buildImpersonationWrapperUrl(int $personId, string $patientPortalDestinationUrl): string
    {
        return route('admin.contacts.persons.impersonate-and-open-form', $personId)
            .'?redirect='.urlencode($patientPortalDestinationUrl);
    }

    /**
     * @param  Person|null  $person  When null (e.g. missing relation), only the raw portal URL can be returned.
     */
    public static function adminOpenUrlForPerson(?string $gvlFormLink, ?Person $person): ?string
    {
        if ($gvlFormLink === null || $gvlFormLink === '') {
            return null;
        }

        if ($person === null) {
            return $gvlFormLink;
        }

        $personId = (int) $person->id;
        $hasPortal = ! empty($person->keycloak_user_id);

        return self::adminOpenUrl($gvlFormLink, $personId, $hasPortal);
    }
}
