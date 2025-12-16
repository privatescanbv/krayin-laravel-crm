@props([
    'title',
    'headerTitle',
    'headerDescription',
    'backRoute',
    'formAction',
    'formId',
    'matchScore' => null,
    'matchScoreTitle' => 'Match Score',
    'redirectRoute',
])

<x-admin::layouts>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    {{ $headerBefore ?? '' }}

    <!-- Page Header -->
    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="flex flex-col gap-2">
            {{ $breadcrumbs ?? '' }}

            <div class="text-xl font-bold dark:text-white">
                {{ $headerTitle }}
            </div>

            <p class="text-gray-600 dark:text-gray-300">
                {!! $headerDescription !!}
            </p>
        </div>

        <div class="flex items-center gap-x-2.5">
            <a
                href="{{ $backRoute }}"
                class="secondary-button"
            >
                @lang('admin::app.account.edit.back-btn')
            </a>
        </div>
    </div>

    {{ $headerAfter ?? '' }}

    <form id="{{ $formId }}" action="{{ $formAction }}" method="POST">
        @csrf

        <div class="mt-3.5">
            @if ($matchScore)
                <x-admin.sync.match-score :score="$matchScore" :title="$matchScoreTitle" />
            @endif

            {{ $slot }}
        </div>
    </form>

    {{ $contentAfter ?? '' }}
</x-admin::layouts>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission
            const form = document.getElementById('{{ $formId }}');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(form);
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn ? submitBtn.innerHTML : '';

                    if (submitBtn) {
                        // Show loading state
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = 'Bezig met overnemen...';
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.message) {
                                // Show success message briefly
                                alert(data.message);
                            }

                            // Redirect immediately if URL provided
                            if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            } else {
                                // Fallback: redirect
                                window.location.href = "{{ $redirectRoute }}";
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Er is een fout opgetreden bij het overnemen: ' + error.message);

                            // Restore button state on error
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }
                        });
                });
            }
        });
    </script>
@endpush

