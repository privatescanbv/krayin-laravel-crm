<?php

namespace Webkul\Admin\Http\Controllers\Products;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Products\ProductGroupDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ProductGroupResource;
use Webkul\Product\Repositories\ProductGroupRepository;

class ProductGroupController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ProductGroupRepository $productGroupRepository) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(ProductGroupDataGrid::class)->process();
        }

        return view('admin::products.groups.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin::products.groups.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $this->validate(request(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $productGroup = $this->productGroupRepository->create(request()->all());

        session()->flash('success', trans('admin::app.productgroups.index.create-success'));

        return redirect()->route('admin.productgroups.index');
    }

    /**
     * Show the form for viewing the specified resource.
     */
    public function view(int $id): View
    {
        $productGroup = $this->productGroupRepository->findOrFail($id);

        return view('admin::products.groups.view', compact('productGroup'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $productGroup = $this->productGroupRepository->findOrFail($id);

        return view('admin::products.groups.edit', compact('productGroup'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id)
    {
        $this->validate(request(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $productGroup = $this->productGroupRepository->update(request()->all(), $id);

        session()->flash('success', trans('admin::app.productgroups.index.update-success'));

        return redirect()->route('admin.productgroups.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->productGroupRepository->delete($id);

        return new JsonResponse([
            'message' => trans('admin::app.products.product-groups.delete-success'),
        ]);
    }
}
