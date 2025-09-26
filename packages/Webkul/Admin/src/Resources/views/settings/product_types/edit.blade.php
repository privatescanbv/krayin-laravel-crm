@extends('admin::layouts.master')

@section('page_title')
    {{ trans('admin::app.settings.product_types.edit.title') }}
@endsection

@section('content')
    <div class="content full-page">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ trans('admin::app.settings.product_types.edit.title') }}</h1>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.product_types.update', $product_type->id) }}" @submit.prevent="onSubmit">
            @csrf
            @method('PUT')

            <div class="page-content">
                <div class="form-container">
                    <div class="control-group" :class="{'has-error': errors.has('name')}">
                        <label for="name" class="required">{{ trans('admin::app.settings.product_types.edit.name') }}</label>
                        <input type="text" name="name" v-validate="'required'" value="{{ old('name', $product_type->name) }}" data-vv-as="&quot;{{ trans('admin::app.settings.product_types.edit.name') }}&quot;">
                        <span class="control-error" v-if="errors.has('name')">@{{ errors.first('name') }}</span>
                    </div>

                    <div class="control-group">
                        <label for="description">{{ trans('admin::app.settings.product_types.edit.description') }}</label>
                        <textarea name="description" rows="3">{{ old('description', $product_type->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="page-action">
                <button type="submit" class="btn btn-lg btn-primary">
                    {{ trans('admin::app.settings.product_types.edit.save-btn') }}
                </button>
            </div>
        </form>
    </div>
@endsection

