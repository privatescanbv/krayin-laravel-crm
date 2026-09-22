<?php

namespace Webkul\Admin\Http\Controllers\Dashboard;

use App\Services\Metabase\MetabaseDashboardRegistry;
use App\Services\Metabase\MetabaseEmbedException;
use App\Services\Metabase\MetabaseEmbedUrlFactory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class MetabaseDashboardController extends Controller
{
    public function __construct(
        private readonly MetabaseDashboardRegistry $registry,
        private readonly MetabaseEmbedUrlFactory $embedUrls,
    ) {}

    public function show(Request $request): View
    {
        $page = $this->resolvePage(
            $request->route('key'),
            $request->route('slug'),
        );

        if ($page === null) {
            abort(404);
        }

        if (! bouncer()->hasPermission($page['key'])) {
            abort(401, 'This action is unauthorized');
        }

        try {
            $embedUrl = $this->embedUrls->forDashboard(
                $page['dashboard_id'],
                $page['params'],
            );
        } catch (MetabaseEmbedException $e) {
            abort(503, $e->getMessage());
        }

        return view('admin::dashboard.metabase', [
            'title'    => trans($page['name']),
            'embedUrl' => $embedUrl,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePage(mixed $key, mixed $slug): ?array
    {
        if (is_string($key) && $key !== '') {
            return $this->registry->findByKey($key);
        }

        if (is_string($slug) && $slug !== '') {
            return $this->registry->findBySlug($slug);
        }

        return null;
    }
}
