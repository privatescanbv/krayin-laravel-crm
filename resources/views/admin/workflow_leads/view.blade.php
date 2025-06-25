<x-admin::layouts>
    <x-slot:title>
        View Workflow Lead
    </x-slot>

    <!-- Header -->
    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-2">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="workflow-leads.view" :entity="$workflowLead" />

            <div class="text-xl font-bold dark:text-white">
                View Workflow Lead
            </div>
        </div>

        <div class="flex items-center gap-x-2.5">
            @if (bouncer()->hasPermission('workflow-leads.edit'))
                <a
                    href="{{ route('admin.workflow-leads.edit', $workflowLead->id) }}"
                    class="primary-button"
                >
                    Edit Workflow Lead
                </a>
            @endif
        </div>
    </div>

    <!-- Content -->
    <div class="mt-3.5">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Basic Information -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Basic Information</h3>

                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $workflowLead->id }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $workflowLead->name }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $workflowLead->description ?: 'No description' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Related Information -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Related Information</h3>

                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Pipeline Stage</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">
                                {{ $workflowLead->pipelineStage ? $workflowLead->pipelineStage->name : 'No stage assigned' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Lead</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">
                                {{ $workflowLead->lead ? $workflowLead->lead->title : 'No lead assigned' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">User</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">
                                {{ $workflowLead->user ? $workflowLead->user->name : 'No user assigned' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $workflowLead->created_at->format('M d, Y H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Updated At</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $workflowLead->updated_at->format('M d, Y H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts>
