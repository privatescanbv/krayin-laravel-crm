<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Email\Repositories\FolderRepository;

class FolderController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected FolderRepository $folderRepository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $folders = $this->folderRepository->all();

        return view('admin::settings.folders.index', compact('folders'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $folders = $this->folderRepository->getTree();

        return view('admin::settings.folders.create', compact('folders'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): RedirectResponse
    {
        $this->validate(request(), [
            'name'         => 'required|string|max:255',
            'parent_id'    => 'nullable|exists:folders,id',
        ]);

        $data = request()->all();
        $data['is_deletable'] = true; // Always allow deletion for new folders

        $this->folderRepository->create($data);

        session()->flash('success', trans('admin::app.settings.folders.create-success'));

        return redirect()->route('admin.settings.folders.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $folder = $this->folderRepository->findOrFail($id);
        $folders = $this->folderRepository->getTree()->where('id', '!=', $id);

        return view('admin::settings.folders.edit', compact('folder', 'folders'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id): RedirectResponse
    {
        $this->validate(request(), [
            'name'         => 'required|string|max:255',
            'parent_id'    => 'nullable|exists:folders,id|different:' . $id,
        ]);

        $data = request()->all();
        // Don't update is_deletable field - keep existing value

        $this->folderRepository->update($data, $id);

        session()->flash('success', trans('admin::app.settings.folders.update-success'));

        return redirect()->route('admin.settings.folders.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse|RedirectResponse
    {
        try {
            $folder = $this->folderRepository->findOrFail($id);

            // Check if folder is deletable
            if (!$folder->is_deletable) {
                $message = trans('admin::app.settings.folders.delete-not-allowed');
                
                if (request()->ajax()) {
                    return response()->json([
                        'message' => $message,
                    ], 403);
                }

                session()->flash('error', $message);
                return redirect()->back();
            }

            $this->folderRepository->delete($id);

            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.settings.folders.delete-success'),
                ]);
            }

            session()->flash('success', trans('admin::app.settings.folders.delete-success'));

            return redirect()->route('admin.settings.folders.index');
        } catch (Exception $e) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.settings.folders.delete-failed'),
                ], 400);
            }

            session()->flash('error', trans('admin::app.settings.folders.delete-failed'));

            return redirect()->back();
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        try {
            $nonDeletableFolders = [];
            
            foreach ($massDestroyRequest->input('indices') as $id) {
                $folder = $this->folderRepository->findOrFail($id);
                
                if (!$folder->is_deletable) {
                    $nonDeletableFolders[] = $folder->name;
                    continue;
                }
                
                $this->folderRepository->delete($id);
            }

            if (!empty($nonDeletableFolders)) {
                return response()->json([
                    'message' => trans('admin::app.settings.folders.mass-delete-partial-success', [
                        'folders' => implode(', ', $nonDeletableFolders)
                    ]),
                ], 207); // 207 Multi-Status
            }

            return response()->json([
                'message' => trans('admin::app.settings.folders.mass-delete-success'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => trans('admin::app.settings.folders.mass-delete-failed'),
            ], 400);
        }
    }
}