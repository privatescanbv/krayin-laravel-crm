@extends('admin::layouts.master')

@section('page_title')
    {{ trans('admin::app.settings.product_types.index.title') }}
@endsection

@section('content')
    <div class="content full-page">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ trans('admin::app.settings.product_types.index.title') }}</h1>
            </div>

            <div class="page-action">
                @if (bouncer()->hasPermission('settings.product_types.create'))
                    <a href="{{ route('admin.settings.product_types.create') }}" class="btn btn-lg btn-primary">
                        {{ trans('admin::app.settings.product_types.index.create-btn') }}
                    </a>
                @endif
            </div>
        </div>

        {!! datagrid('App\\DataGrids\\Settings\\ProductTypeDataGrid')->render() !!}
    </div>
@endsection

