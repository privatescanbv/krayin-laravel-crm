<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Actions\Clinic\SendClinicForgotPasswordMailAction;
use App\Enums\RoleCode;
use Webkul\User\Models\User;

trait ResolvesClinicDoctor
{
    /**
     * 404 for an unknown clinic user, 403 when the user is not a doctor.
     */
    private function doctor(string $keycloakUserId): User
    {
        $user = SendClinicForgotPasswordMailAction::clinicUsers()
            ->where('keycloak_user_id', $keycloakUserId)
            ->with('role')
            ->firstOrFail();

        abort_unless($user->role?->code === RoleCode::ARTS, 403);

        return $user;
    }
}
