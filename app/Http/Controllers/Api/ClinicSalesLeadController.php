<?php

namespace App\Http\Controllers\Api;

use App\Actions\Clinic\SendClinicForgotPasswordMailAction;
use App\Enums\PipelineDefaultKeys;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClinicSalesLeadIndexRequest;
use App\Http\Requests\Api\ClinicSalesLeadStageRequest;
use App\Http\Resources\ClinicSalesLeadResource;
use App\Http\Resources\ClinicSalesStageResource;
use App\Models\SalesLead;
use App\Services\StageTransitionAttributes;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
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
     * Herniapoli sales assigned to the doctor, newest first. Card data: persons carry id, name and date_of_birth (nullable); `stage_entered_at` is null when the move into the current stage is not in the audit trail.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     *
     * @response 200 scenario="Success" {"data":[{"id":12,"name":"Rugklachten","pipeline_stage_id":41,"created_at":"2026-11-05T09:00:00+00:00","updated_at":"2026-11-05T09:00:00+00:00","closed_at":null,"stage_entered_at":"2026-11-06T10:30:00+00:00","persons":[{"id":7,"name":"Jan Jansen","date_of_birth":"1980-04-02"}]}],"links":{},"meta":{}}
     * @response 403 scenario="User is not a doctor"
     * @response 404 scenario="No clinic user for this Keycloak user id"
     */
    public function index(ClinicSalesLeadIndexRequest $request, string $keycloakUserId): AnonymousResourceCollection
    {
        $doctor = $this->doctor($keycloakUserId);

        return ClinicSalesLeadResource::collection(
            SalesLead::query()
                ->forPortalDoctor($doctor)
                ->withStageEnteredAt()
                ->when($request->integer('stage_id'), fn ($query, $stageId) => $query->where('pipeline_stage_id', $stageId))
                ->with('persons')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 25))
        );
    }

    /**
     * Move the doctor's own sale to another ordinary stage of the Herniapoli sales pipeline (kanban drag).
     * Only `pipeline_stage_id` is accepted. Won/lost targets and already closed sales are refused; the assessment
     * outcome rules of the admin stage change apply. Moving to the current stage is a no-op (200, nothing written).
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam saleId integer required The sale id (must be assigned to this doctor and visible to the role).
     *
     * @response 200 scenario="Success" {"data":{"id":12,"name":"Rugklachten","pipeline_stage_id":42,"created_at":"2026-11-05T09:00:00+00:00","updated_at":"2026-11-10T09:00:00+00:00","closed_at":null,"stage_entered_at":"2026-11-10T09:00:00+00:00","persons":[{"id":7,"name":"Jan Jansen","date_of_birth":"1980-04-02"}]}}
     * @response 403 scenario="User is not a doctor, or Bearer token belongs to another user"
     * @response 404 scenario="Unknown user, or sale not assigned to / not visible for this doctor"
     * @response 422 scenario="Invalid or refused move" {"message":"Vul eerst de uitkomst beoordeling in voordat de sales naar \"Beoordeling gereed\" gaat.","errors":{"assessment_outcome":["Vul eerst de uitkomst beoordeling in voordat de sales naar \"Beoordeling gereed\" gaat."]}}
     */
    public function updateStage(ClinicSalesLeadStageRequest $request, string $keycloakUserId, int $saleId): ClinicSalesLeadResource
    {
        $doctor = $this->doctor($keycloakUserId);
        $sale = SalesLead::query()->forPortalDoctor($doctor)->with('stage')->findOrFail($saleId);

        $target = Stage::query()
            ->where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID)
            ->visibleForRole($doctor->role_id)
            ->find($request->validated('pipeline_stage_id'));

        if (! $target) {
            throw ValidationException::withMessages(['pipeline_stage_id' => 'Deze status bestaat niet of is niet beschikbaar.']);
        }

        if ($sale->closed_at || StageTransitionAttributes::isWonOrLost($sale->stage)) {
            throw ValidationException::withMessages(['pipeline_stage_id' => 'Een afgesloten sale kan hier niet worden verplaatst.']);
        }

        if (StageTransitionAttributes::isWonOrLost($target)) {
            throw ValidationException::withMessages(['pipeline_stage_id' => 'Verplaatsen naar gewonnen of verloren is hier niet mogelijk.']);
        }

        if ($target->id !== $sale->pipeline_stage_id) {
            $additionalResearch = StageTransitionAttributes::resolveAdditionalResearch($sale, $target);
            StageTransitionAttributes::ensureAssessmentOutcome($sale, $target, $sale->assessment_outcome, $additionalResearch);

            $sale->update([
                'pipeline_stage_id'            => $target->id,
                'additional_research_required' => $additionalResearch,
            ]);
        }

        return new ClinicSalesLeadResource(SalesLead::query()->withStageEnteredAt()->with('persons')->findOrFail($sale->id));
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
