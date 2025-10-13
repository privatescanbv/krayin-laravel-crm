<?php

namespace Webkul\Admin\Http\Controllers\Contact;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Contact\OrganizationDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\Http\Resources\OrganizationResource;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Models\Organization;
use App\Models\Address;

class OrganizationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected OrganizationRepository $organizationRepository)
    {
        request()->request->add(['entity_type' => 'organizations']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(OrganizationDataGrid::class)->process();
        }

        return view('admin::contacts.organizations.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::contacts.organizations.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $organization = $this->organizationRepository->findOrFail($id);

        return view('admin::contacts.organizations.view', compact('organization'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request): RedirectResponse|JsonResponse
    {
        Event::dispatch('contacts.organization.create.before');

        $data = request()->all();

        // Basic validation for name field
        if (empty($data['name'])) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Name is required'
                ], 422);
            }
            return redirect()->back()->with('error', 'Name is required');
        }

        $organization = $this->organizationRepository->create($data);

        // Handle address creation
        if (isset($data['address']) && !empty(array_filter($data['address']))) {
            $addressData = array_merge($data['address'], [
                'organization_id' => $organization->id
            ]);
            Address::create($addressData);
        }

        Event::dispatch('contacts.organization.create.after', $organization);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'address' => $organization->address
                ],
                'message' => trans('admin::app.contacts.organizations.index.create-success'),
            ], 200);
        }

        session()->flash('success', trans('admin::app.contacts.organizations.index.create-success'));

        return redirect()->route('admin.contacts.organizations.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        // Use the model directly to ensure proper relationship loading
        $organization = Organization::with('address')->findOrFail($id);

        return view('admin::contacts.organizations.edit', compact('organization'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id): RedirectResponse|JsonResponse
    {
        Event::dispatch('contacts.organization.update.before', $id);

        $data = request()->all();

        // Basic validation for name field
        if (empty($data['name'])) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Name is required'
                ], 422);
            }
            return redirect()->back()->with('error', 'Name is required');
        }

        try {
            $organization = $this->organizationRepository->update($data, $id);

            if (!$organization) {
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Organization not found'
                    ], 404);
                }
                return redirect()->back()->with('error', 'Organization not found');
            }

            // Handle address update
            if (isset($data['address'])) {
                // Get fresh organization instance with address relationship
                $organization = $this->organizationRepository->find($id);
                if (!$organization) {
                    if (request()->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Organization not found after update'
                        ], 404);
                    }
                    return redirect()->back()->with('error', 'Organization not found after update');
                }
                $existingAddress = $organization->address;

            if (!empty(array_filter($data['address']))) {
                $addressData = array_merge($data['address'], [
                    'organization_id' => $organization->id
                ]);

                if ($existingAddress && is_object($existingAddress)) {
                    $existingAddress->update($addressData);
                } else {
                    // Delete any existing address first (in case of data inconsistency)
                    Address::where('organization_id', $organization->id)->delete();
                    Address::create($addressData);
                }
            } else if ($existingAddress && is_object($existingAddress)) {
                // If address data is empty, delete existing address
                $existingAddress->delete();
            }
        }

            Event::dispatch('contacts.organization.update.after', $organization);

            session()->flash('success', trans('admin::app.contacts.organizations.index.update-success'));

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => trans('admin::app.contacts.organizations.index.update-success'),
                    'data' => $organization->load('address')
                ]);
            }

            return redirect()->route('admin.contacts.organizations.index');
        } catch (\Exception $e) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating organization: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Error updating organization: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            Event::dispatch('contact.organization.delete.before', $id);

            $this->organizationRepository->delete($id);

            Event::dispatch('contact.organization.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.contacts.organizations.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.organizations.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $organizations = $this->organizationRepository->findWhereIn('id', request()->input('indices'));

        foreach ($organizations as $organization) {
            Event::dispatch('contact.organization.delete.before', $organization);

            $this->organizationRepository->delete($organization->id);

            Event::dispatch('contact.organization.delete.after', $organization);
        }

        return response()->json([
            'message' => trans('admin::app.contacts.organizations.index.delete-success'),
        ]);
    }

    /**
     * Search organizations for lookup.
     */
    public function search()
    {
        $searchTerm = request('search');
        
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $organizations = $this->organizationRepository
                ->with(['address'])
                ->findWhereIn('user_id', $userIds);
        } else {
            $organizations = $this->organizationRepository
                ->with(['address'])
                ->all();
        }

        // If search term is provided, filter by name
        if (!empty($searchTerm)) {
            $organizations = $organizations->filter(function($organization) use ($searchTerm) {
                return stripos($organization->name, $searchTerm) !== false;
            });
        }

        return OrganizationResource::collection($organizations);
    }


}
