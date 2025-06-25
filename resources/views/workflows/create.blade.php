@extends('admin::layouts.content')

@section('page_title')
    {{ __('workflows::app.workflow.add-title') }}
@stop

@section('content')
    <div class="content">
        <form method="POST" action="{{ route('workflows.store') }}" @submit.prevent="onSubmit">
            @csrf

            <div class="page-header">
                <div class="page-title">
                    <h1>{{ __('workflows::app.workflow.add-title') }}</h1>
                </div>

                <div class="page-action">
                    <button type="submit" class="btn btn-lg btn-primary">
                        {{ __('workflows::app.workflow.save-btn-title') }}
                    </button>
                </div>
            </div>

            <div class="page-content">
                <div class="form-container">
                    <div class="control-group" :class="[errors.has('name') ? 'has-error' : '']">
                        <label for="name" class="required">{{ __('workflows::app.workflow.name') }}</label>
                        <input type="text" name="name" class="control" id="name" v-validate="'required'" data-vv-as="&quot;{{ __('workflows::app.workflow.name') }}&quot;">
                        <span class="control-error" v-if="errors.has('name')">@{{ errors.first('name') }}</span>
                    </div>

                    <div class="control-group" :class="[errors.has('description') ? 'has-error' : '']">
                        <label for="description">{{ __('workflows::app.workflow.description') }}</label>
                        <textarea name="description" class="control" id="description" rows="5"></textarea>
                        <span class="control-error" v-if="errors.has('description')">@{{ errors.first('description') }}</span>
                    </div>

                    <div class="control-group" :class="[errors.has('pipeline_stage_id') ? 'has-error' : '']">
                        <label for="pipeline_stage_id" class="required">{{ __('workflows::app.workflow.pipeline-stage') }}</label>
                        <select name="pipeline_stage_id" class="control" id="pipeline_stage_id" v-validate="'required'" data-vv-as="&quot;{{ __('workflows::app.workflow.pipeline-stage') }}&quot;">
                            <option value="">{{ __('workflows::app.workflow.select-pipeline-stage') }}</option>
                            @foreach ($pipelineStages as $pipelineStage)
                                <option value="{{ $pipelineStage->id }}">{{ $pipelineStage->name }}</option>
                            @endforeach
                        </select>
                        <span class="control-error" v-if="errors.has('pipeline_stage_id')">@{{ errors.first('pipeline_stage_id') }}</span>
                    </div>

                    <div class="control-group" :class="[errors.has('lead_id') ? 'has-error' : '']">
                        <label for="lead_id">{{ __('workflows::app.workflow.lead') }}</label>
                        <select name="lead_id" class="control" id="lead_id">
                            <option value="">{{ __('workflows::app.workflow.select-lead') }}</option>
                            @foreach ($leads as $lead)
                                <option value="{{ $lead->id }}">{{ $lead->title }}</option>
                            @endforeach
                        </select>
                        <span class="control-error" v-if="errors.has('lead_id')">@{{ errors.first('lead_id') }}</span>
                    </div>

                    <div class="control-group" :class="[errors.has('order_id') ? 'has-error' : '']">
                        <label for="order_id">{{ __('workflows::app.workflow.order') }}</label>
                        <select name="order_id" class="control" id="order_id">
                            <option value="">{{ __('workflows::app.workflow.select-order') }}</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order->id }}">{{ $order->id }}</option>
                            @endforeach
                        </select>
                        <span class="control-error" v-if="errors.has('order_id')">@{{ errors.first('order_id') }}</span>
                    </div>

                    <div class="control-group" :class="[errors.has('user_id') ? 'has-error' : '']">
                        <label for="user_id">{{ __('workflows::app.workflow.user') }}</label>
                        <select name="user_id" class="control" id="user_id">
                            <option value="">{{ __('workflows::app.workflow.select-user') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <span class="control-error" v-if="errors.has('user_id')">@{{ errors.first('user_id') }}</span>
                    </div>
                </div>
            </div>
        </form>
    </div>
@stop 