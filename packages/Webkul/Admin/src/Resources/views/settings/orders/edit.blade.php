@extends('admin::layouts.master')

@section('page_title')
    {{ __('Order bewerken') }}
@endsection

@section('content')
    <div class="flex gap-4 items-center mb-4">
        <h1 class="text-xl font-semibold">Order bewerken</h1>
    </div>

    <x-admin::form :action="route('admin.settings.orders.update', ['id' => $orders->id])">
        <input type="hidden" name="_method" value="put">

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>{{ __('Titel') }}</x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="text"
                name="title"
                :value="$orders->title"
                rules="required"
            />
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>{{ __('Sales Order ID') }}</x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="text"
                name="sales_order_id"
                :value="$orders->sales_order_id"
            />
        </x-admin::form.control-group>

        <x-admin::form.control-group>
            <x-admin::form.control-group.label>{{ __('Totale prijs') }}</x-admin::form.control-group.label>
            <x-admin::form.control-group.control
                type="number"
                step="0.01"
                name="total_price"
                :value="$orders->total_price"
            />
        </x-admin::form.control-group>

        <div class="flex gap-2 justify-end">
            <a href="{{ route('admin.settings.orders.index') }}" class="btn btn-secondary">{{ __('Annuleren') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Opslaan') }}</button>
        </div>
    </x-admin::form>
@endsection

