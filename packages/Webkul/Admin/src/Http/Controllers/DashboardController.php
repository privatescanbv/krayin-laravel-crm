<?php

namespace Webkul\Admin\Http\Controllers;

use App\Services\Metabase\MetabaseDashboardRegistry;
use App\Actions\Keycloak\GetKeycloakActiveSessionCountAction;
use App\Enums\KeyCloakClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Webkul\Admin\Helpers\Dashboard;

class DashboardController extends Controller
{
    /**
     * Seconds a live Keycloak session-count attempt (success or failure) is cached
     * for, so concurrent dashboard viewers don't each trigger their own admin API call.
     */
    protected const PATIENT_PORTAL_SESSIONS_CACHE_TTL = 30;

    protected const PATIENT_PORTAL_SESSIONS_ATTEMPT_KEY = 'dashboard.patient-portal-sessions.attempt';

    protected const PATIENT_PORTAL_SESSIONS_LAST_GOOD_KEY = 'dashboard.patient-portal-sessions.last-good';
    /**
     * Request param functions
     *
     * @var array
     */
    protected $typeFunctions = [
        'over-all'             => 'getOverAllStats',
        'total-leads'          => 'getTotalLeadsStats',
        'revenue-by-sources'   => 'getLeadsStatsBySources',
        'revenue-by-types'     => 'getLeadsStatsByTypes',
        'top-selling-products' => 'getTopSellingProducts',
        'top-persons'          => 'getTopPersons',
        'open-leads-by-states' => 'getOpenLeadsByStates',
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected Dashboard $dashboardHelper,
        protected MetabaseDashboardRegistry $metabaseDashboards,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin::dashboard.index')->with([
            'startDate'     => $this->dashboardHelper->getStartDate(),
            'endDate'       => $this->dashboardHelper->getEndDate(),
            'metabasePages' => $this->metabaseDashboards->visiblePages(),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $stats = $this->dashboardHelper->{$this->typeFunctions[request()->query('type')]}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->dashboardHelper->getDateRange(),
        ]);
    }

    /**
     * JSON endpoint returning the number of active Keycloak sessions for the
     * patient portal client, with graceful fallback when Keycloak is unreachable.
     */
    public function patientPortalSessions(GetKeycloakActiveSessionCountAction $action): JsonResponse
    {
        $attempt = Cache::remember(
            self::PATIENT_PORTAL_SESSIONS_ATTEMPT_KEY,
            self::PATIENT_PORTAL_SESSIONS_CACHE_TTL,
            fn () => array_merge($action->execute(KeyCloakClient::PATIENT), [
                'fetched_at' => now()->toIso8601String(),
            ])
        );

        if ($attempt['success']) {
            $lastGood = [
                'count'      => $attempt['count'],
                'fetched_at' => $attempt['fetched_at'],
            ];

            Cache::forever(self::PATIENT_PORTAL_SESSIONS_LAST_GOOD_KEY, $lastGood);

            return response()->json([
                'available'  => true,
                'count'      => $lastGood['count'],
                'fetched_at' => $lastGood['fetched_at'],
            ]);
        }

        $lastGood = Cache::get(self::PATIENT_PORTAL_SESSIONS_LAST_GOOD_KEY);

        return response()->json([
            'available'  => false,
            'count'      => null,
            'fetched_at' => $lastGood['fetched_at'] ?? null,
        ]);
    }
}
