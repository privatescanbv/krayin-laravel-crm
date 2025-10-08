@extends('admin::layouts.master')

@section('page_title')
    {{ __('Orderregel aanmaken') }}
@endsection

@section('content')
    <div class="flex gap-4 items-center mb-4">
        <h1 class="text-xl font-semibold">Orderregel aanmaken</h1>
    </div>

    <x-admin::form :action="route('admin.settings.order_regels.store')">
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>{{ __('Order ID') }}</x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="number"
                name="order_id"
                rules="required|integer"
            />
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>{{ __('Product ID') }}</x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="number"
                name="product_id"
                rules="required|integer"
            />
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>{{ __('Aantal') }}</x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="number"
                name="quantity"
                rules="required|integer|min:1"
            />
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>{{ __('Totale prijs') }}</x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="number"
                step="0.01"
                name="total_price"
            />
        </x-admin::form.control-group>

        <div class="flex gap-2 justify-end">
            <a href="{{ route('admin.settings.order_regels.index') }}" class="btn btn-secondary">{{ __('Annuleren') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Opslaan') }}</button>
        </div>
    </x-admin::form>
@endsection

