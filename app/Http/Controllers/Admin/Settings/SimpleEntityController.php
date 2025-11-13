<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Concerns\HasEntitySearch;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

abstract class SimpleEntityController extends Controller
{
    use HasEntitySearch;

    protected string $entityName;

    protected string $datagridClass;

    protected string $indexView;

    protected string $createView;

    protected string $editView;

    protected string $indexRoute;

    protected string $permissionPrefix;

    public function __construct(protected $repository) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return datagrid($this->datagridClass)->process();
        }

        return view($this->indexView, $this->getIndexViewData($request));
    }

    public function create(Request $request): View
    {
        return view($this->createView, $this->getCreateViewData($request));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->validateStore($request);

        Event::dispatch("settings.{$this->entityName}.create.before");

        $entity = $this->repository->create($this->transformPayload($request->all()));

        Event::dispatch("settings.{$this->entityName}.create.after", $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getCreateSuccessMessage(),
            ], 200);
        }

        return redirect()
            ->route($this->indexRoute)
            ->with('success', $this->getCreateSuccessMessage());
    }

    public function edit(Request $request, int $id): View|JsonResponse
    {
        $entity = $this->repository->findOrFail($id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $entity]);
        }

        return view($this->editView, $this->getEditViewData($request, $entity));
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->validateUpdate($request, $id);

        Event::dispatch("settings.{$this->entityName}.update.before", $id);

        $entity = $this->repository->update($this->transformPayload($request->all(), $id), $id);

        Event::dispatch("settings.{$this->entityName}.update.after", $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        return redirect()
            ->route($this->indexRoute)
            ->with('success', $this->getUpdateSuccessMessage());
    }

    public function destroy(Request $request, ?int $id = null): RedirectResponse|JsonResponse
    {
        if (! $id) {
            $indices = $request['indices'];
            if (is_array($indices) && count($indices) > 0) {
                $id = (int) $indices[0];
            }
        }

        if (! $id) {
            return redirect()
                ->route($this->indexRoute)
                ->with('error', 'Geen geldig ID opgegeven.');
        }

        $entity = $this->repository->findOrFail($id);

        try {
            Event::dispatch("settings.{$this->entityName}.delete.before", $id);

            $entity->delete();

            Event::dispatch("settings.{$this->entityName}.delete.after", $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $this->getDestroySuccessMessage(),
                ], 200);
            }

            return redirect()
                ->route($this->indexRoute)
                ->with('success', $this->getDestroySuccessMessage());
        } catch (Exception $exception) {
            logger()->error('Could not delete: '.$exception->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $this->getDeleteFailedMessage(),
                ], 400);
            }

            return redirect()
                ->route($this->indexRoute)
                ->with('error', $this->getDeleteFailedMessage());
        }
    }

    /**
     * Search entity results.
     *
     * Supports both `query` parameter (for entity selector) and `search`/`searchFields` (for RequestCriteria).
     * If `query` is provided, it's converted to `search` and `searchFields` format for RequestCriteria.
     */
    public function search(Request $request): JsonResponse
    {
        $entities = $this->performEntitySearch($request);

        // Return simple array format for entity selector
        $data = $entities->map(function ($entity) {
            return [
                'id'    => $entity->id,
                'name'  => $entity->name,
                'label' => $entity->name, // Alias for entity selector compatibility
            ];
        });

        return response()->json($data);
    }

    protected function getIndexViewData(Request $request): array
    {
        return [];
    }

    protected function getCreateViewData(Request $request): array
    {
        return [];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        return [
            $this->entityName => $entity,
        ];
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        return $payload;
    }

    abstract protected function validateStore(Request $request): void;

    abstract protected function validateUpdate(Request $request, int $id): void;

    abstract protected function getCreateSuccessMessage(): string;

    abstract protected function getUpdateSuccessMessage(): string;

    abstract protected function getDestroySuccessMessage(): string;

    abstract protected function getDeleteFailedMessage(): string;
}
