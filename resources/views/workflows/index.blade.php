@extends('admin::layouts.content')

@section('page_title')
    {{ __('workflows::app.workflow.title') }}
@stop

@section('content')
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ __('workflows::app.workflow.title') }}</h1>
            </div>

            <div class="page-action">
                <a href="{{ route('workflows.create') }}" class="btn btn-lg btn-primary">
                    {{ __('workflows::app.workflow.add-title') }}
                </a>
            </div>
        </div>

        <div class="page-content">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('workflows::app.workflow.name') }}</th>
                            <th>{{ __('workflows::app.workflow.pipeline-stage') }}</th>
                            <th>{{ __('workflows::app.workflow.lead') }}</th>
                            <th>{{ __('workflows::app.workflow.order') }}</th>
                            <th>{{ __('workflows::app.workflow.user') }}</th>
                            <th>{{ __('workflows::app.workflow.action') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($workflows as $workflow)
                            <tr>
                                <td>{{ $workflow->name }}</td>
                                <td>{{ $workflow->pipelineStage->name }}</td>
                                <td>{{ $workflow->lead ? $workflow->lead->title : '-' }}</td>
                                <td>{{ $workflow->order ? $workflow->order->id : '-' }}</td>
                                <td>{{ $workflow->user ? $workflow->user->name : '-' }}</td>
                                <td>
                                    <a href="{{ route('workflows.edit', $workflow->id) }}" class="btn btn-sm btn-primary">
                                        {{ __('workflows::app.workflow.edit') }}
                                    </a>

                                    <form action="{{ route('workflows.destroy', $workflow->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ __('workflows::app.workflow.delete-confirm') }}')">
                                            {{ __('workflows::app.workflow.delete') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop 