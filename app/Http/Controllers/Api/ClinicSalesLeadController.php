<?php

namespace App\Http\Controllers\Api;

use App\Actions\Clinic\SendClinicForgotPasswordMailAction;
use App\Enums\PipelineDefaultKeys;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClinicSalesLeadIndexRequest;
use App\Http\Resources\ClinicSalesLeadResource;
use App\Http\Resources\ClinicSalesStageResource;
use App\Models\SalesLead;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

/**
 * Sales-kanban of a doctor (role Arts) in the clinic portal, Herniapoli only.
 * A Keycloak bearer token must belong to {keycloakUserId} (patient.self middleware); X-API-KEY callers are service-to-service.
 */
class ClinicSalesLeadController extends Controller
{
    /**
     * Stages (kanban columns) of the Herniapoli sales pipeline that the user's role may see.
     * A stage without role restrictions is visible to every role.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     *
     * @response 200 scenario="Success" {"data":[{"id":41,"code":"sales-intake-hernia","name":"Casus bij arts ter beoordeling","sort_order":2,"is_won":false,"is_lost":false}]}
     * @response 403 scenario="User is not a doctor"
     * @response 404 scenario="No clinic user for this Keycloak user id"
     */
    public function stages(string $keycloakUserId): AnonymousResourceCollection
    {
        $doctor = $this->doctor($keycloakUserId);

        return ClinicSalesStageResource::collection(
            Stage::query()
                ->where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value)
                ->visibleForRole($doctor->role_id)
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Herniapoli sales assigned to the doctor, newest first. Minimal card data; persons only carry id and name.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     *
     * @response 200 scenario="Success" {"data":[{"id":12,"name":"Rugklachten","pipeline_stage_id":41,"created_at":"2026-11-05T09:00:00+00:00","updated_at":"2026-11-05T09:00:00+00:00","closed_at":null,"persons":[{"id":7,"name":"Jan Jansen"}]}],"links":{},"meta":{}}
     * @response 403 scenario="User is not a doctor"
     * @response 404 scenario="No clinic user for this Keycloak user id"
     */
    public function index(ClinicSalesLeadIndexRequest $request, string $keycloakUserId): AnonymousResourceCollection
    {
        $doctor = $this->doctor($keycloakUserId);

        return ClinicSalesLeadResource::collection(
            SalesLead::query()
                ->forPortalDoctor($doctor)
                ->when($request->integer('stage_id'), fn ($query, $stageId) => $query->where('pipeline_stage_id', $stageId))
                ->with('persons')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 25))
        );
    }

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
