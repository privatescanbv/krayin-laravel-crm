@extends('admin::layouts.master')

@section('page_title')
    {{ __('Orders') }}
@endsection

@section('content')
    <x-admin::datagrid src="{{ route('admin.settings.orders.index') }}">
        @slot('header')
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold">Orders</h1>
                @can('settings.orders.create')
                    <a href="{{ route('admin.settings.orders.create') }}" class="btn btn-primary">
                        {{ __('Nieuw') }}
                    </a>
                @endcan
            </div>
        @endslot
    </x-admin::datagrid>
@endsection

