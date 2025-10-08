@extends('admin::layouts.master')

@section('page_title')
    {{ __('Orderregels') }}
@endsection

@section('content')
    <x-admin::datagrid src="{{ route('admin.settings.order_regels.index') }}">
        @slot('header')
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold">Orderregels</h1>
                @can('settings.order_regels.create')
                    <a href="{{ route('admin.settings.order_regels.create') }}" class="btn btn-primary">
                        {{ __('Nieuw') }}
                    </a>
                @endcan
            </div>
        @endslot
    </x-admin::datagrid>
@endsection

