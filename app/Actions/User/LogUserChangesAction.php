<?php

namespace App\Actions\User;

use App\Models\Clinic;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * Writes one system activity per audited field change of a user (shown in the Logboek of the user view).
 * Rows are matched to the user via additional.subject_user.id, see UserController::impersonationLog().
 */
class LogUserChangesAction
{
    /** column => label; everything else (keycloak ids, tokens, timestamps) is deliberately not audited. */
    private const FIELDS = [
        'first_name'      => 'Voornaam',
        'last_name'       => 'Achternaam',
        'email'           => 'E-mail',
        'status'          => 'Status',
        'role_id'         => 'Rol',
        'clinic_id'       => 'Kliniek',
        'view_permission' => 'Weergavepermissie',
        'password'        => 'Wachtwoord',
    ];

    public function __construct(protected ActivityRepository $activityRepository) {}

    public function __invoke(User $user): void
    {
        foreach (array_intersect_key($user->getDirty(), self::FIELDS) as $column => $new) {
            $old = $user->getOriginal($column);

            if ($old == $new) {
                continue;
            }

            $label = self::FIELDS[$column];
            $additional = ['attribute' => $label, 'subject_user' => ['id' => $user->id, 'name' => $user->name]];

            // Never store password hashes; the fact that it changed is the audit.
            if ($column !== 'password') {
                $additional['old'] = ['value' => $old, 'label' => $this->label($column, $old)];
                $additional['new'] = ['value' => $new, 'label' => $this->label($column, $new)];
            }

            $this->activityRepository->createSystem([
                'title'      => $column === 'password' ? 'Wachtwoord gewijzigd' : trans('admin::app.activities.updated', ['attribute' => $label]),
                'user_id'    => auth()->guard('user')->id(),
                'additional' => $additional,
            ]);
        }
    }

    private function label(string $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Leeg';
        }

        return match ($column) {
            'status'    => $value ? 'Actief' : 'Inactief',
            'role_id'   => Role::find($value)?->name ?? (string) $value,
            'clinic_id' => Clinic::find($value)?->name ?? (string) $value,
            default     => (string) $value,
        };
    }
}
