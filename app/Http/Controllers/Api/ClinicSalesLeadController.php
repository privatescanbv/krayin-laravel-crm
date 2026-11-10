<?php

namespace App\Http\Controllers\Api;

use App\Actions\Activities\CreateActivityForSalesLeadAction;
use App\Actions\Clinic\SendClinicForgotPasswordMailAction;
use App\Enums\ActivityType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClinicSalesLeadIndexRequest;
use App\Http\Requests\Api\ClinicSalesLeadStageRequest;
use App\Http\Requests\Api\ClinicSalesNoteIndexRequest;
use App\Http\Requests\Api\ClinicSalesNoteStoreRequest;
use App\Http\Resources\ClinicSalesLeadDetailResource;
use App\Http\Resources\ClinicSalesLeadResource;
use App\Http\Resources\ClinicSalesNoteResource;
use App\Http\Resources\ClinicSalesStageResource;
use App\Models\SalesLead;
use App\Services\PlainText;
use App\Services\StageTransitionAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Webkul\Activity\Models\Activity;
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
     * Patient card of one sale assigned to the doctor: card fields plus all linked persons (with date of birth),
     * stage, doctor, description (plain text), assessment outcome and additional-research flag.
     * Closed sales that are visible to the doctor stay readable. No diagnosis, region, advice, contact or financial data.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam saleId integer required The sale id (must be assigned to this doctor and visible to the role).
     *
     * @response 200 scenario="Success" {"data":{"id":12,"name":"Rugklachten","pipeline_stage_id":41,"created_at":"2026-11-05T09:00:00+00:00","updated_at":"2026-11-05T09:00:00+00:00","closed_at":null,"stage_entered_at":null,"persons":[{"id":7,"name":"Jan Jansen","first_name":"Jan","last_name":"Jansen","date_of_birth":"1980-02-03"}],"stage":{"id":41,"code":"sales-intake-hernia","name":"Casus bij arts ter beoordeling","sort_order":2,"is_won":false,"is_lost":false},"doctor":{"id":3,"name":"Dr. Voorbeeld"},"description":"Omschrijving van het traject.","assessment_outcome":{"code":"example-code","label":"Voorbeeldlabel"},"additional_research_required":false}}
     * @response 403 scenario="User is not a doctor, or Bearer token belongs to another user"
     * @response 404 scenario="Unknown user, or sale not assigned to / not visible for this doctor"
     */
    public function show(string $keycloakUserId, int $saleId): ClinicSalesLeadDetailResource
    {
        $doctor = $this->doctor($keycloakUserId);

        return new ClinicSalesLeadDetailResource(
            SalesLead::query()
                ->forPortalDoctor($doctor)
                ->withStageEnteredAt()
                ->with(['persons', 'stage', 'doctor', 'assessmentOutcome'])
                ->findOrFail($saleId)
        );
    }

    /**
     * Internal notes directly linked to this sale (type note only), newest first. Text is plain text.
     * `author` is the CRM user who created the note, or null when unknown.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam saleId integer required The sale id.
     *
     * @response 200 scenario="Success" {"data":[{"id":5,"text":"Patiënt belt terug na MRI.","created_at":"2026-11-10T09:00:00+00:00","author":{"id":3,"name":"Dr. Voorbeeld"}}],"links":{},"meta":{}}
     * @response 403 scenario="User is not a doctor, or Bearer token belongs to another user"
     * @response 404 scenario="Unknown user, or sale not visible for this doctor"
     */
    public function notes(ClinicSalesNoteIndexRequest $request, string $keycloakUserId, int $saleId): AnonymousResourceCollection
    {
        $sale = SalesLead::query()->forPortalDoctor($this->doctor($keycloakUserId))->findOrFail($saleId);

        $page = Activity::query()
            ->where('sales_lead_id', $sale->id)
            ->where('type', ActivityType::NOTE->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10));

        $authors = User::query()->whereIn('id', $page->getCollection()->pluck('created_by')->filter()->unique())->get()->keyBy('id');
        $page->getCollection()->each(fn (Activity $note) => $note->setAttribute('author_user', $authors->get($note->created_by)));

        return ClinicSalesNoteResource::collection($page);
    }

    /**
     * Add a plain-text internal note to this sale. The doctor from the URL is the author; type and sale are fixed
     * server-side. Changes nothing else and notifies nobody.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam saleId integer required The sale id.
     *
     * @response 201 scenario="Created" {"data":{"id":5,"text":"Patiënt belt terug na MRI.","created_at":"2026-11-10T09:00:00+00:00","author":{"id":3,"name":"Dr. Voorbeeld"}}}
     * @response 403 scenario="User is not a doctor, or Bearer token belongs to another user"
     * @response 404 scenario="Unknown user, or sale not visible for this doctor"
     * @response 422 scenario="Empty or too long text" {"message":"The text field is required.","errors":{"text":["The text field is required."]}}
     */
    public function storeNote(ClinicSalesNoteStoreRequest $request, CreateActivityForSalesLeadAction $create, string $keycloakUserId, int $saleId): JsonResponse
    {
        $doctor = $this->doctor($keycloakUserId);
        $sale = SalesLead::query()->forPortalDoctor($doctor)->findOrFail($saleId);

        // Bearer calls have no CRM session, so the audit trail cannot infer the actor: set it explicitly.
        $note = $create->execute($sale, true, [
            'type'       => ActivityType::NOTE->value,
            'comment'    => PlainText::toHtml($request->validated('text')),
            'user_id'    => $doctor->id,
            'created_by' => $doctor->id,
            'updated_by' => $doctor->id,
        ]);
        $note->setAttribute('author_user', $doctor);

        return (new ClinicSalesNoteResource($note))->response()->setStatusCode(201);
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
