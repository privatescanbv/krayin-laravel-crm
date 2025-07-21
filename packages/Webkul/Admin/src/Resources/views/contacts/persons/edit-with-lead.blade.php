@extends('admin::layouts.master')

@section('page_title')
    Person bijwerken met Lead gegevens
@stop

@section('content-wrapper')
    <div class="content full-page">
        {!! view_render_event('admin.contacts.persons.edit_with_lead.header.before', ['person' => $person, 'lead' => $lead]) !!}

        <div class="page-header">
            <div class="page-title">
                <h1>Person bijwerken met Lead gegevens</h1>
                <p class="text-gray-600">
                    Vergelijk en synchroniseer gegevens tussen 
                    <strong>{{ $person->name }}</strong> en Lead <strong>{{ $lead->title }}</strong>
                </p>
            </div>

            <div class="page-action">
                <a href="{{ route('admin.contacts.persons.view', $person->id) }}" class="btn btn-gray">
                    <i class="icon-arrow-left"></i>
                    Terug naar Person
                </a>
            </div>
        </div>

        {!! view_render_event('admin.contacts.persons.edit_with_lead.header.after', ['person' => $person, 'lead' => $lead]) !!}

        <form id="person-lead-update-form" action="{{ route('admin.contacts.persons.update_with_lead', [$person->id, $lead->id]) }}" method="POST">
            @csrf

            <div class="page-content">
                @if(empty($fieldDifferences))
                    <div class="empty-page">
                        <div class="empty-page-icon">
                            <i class="icon-check-circle text-green-500" style="font-size: 48px;"></i>
                        </div>
                        <div class="empty-page-content">
                            <h3>Geen verschillen gevonden</h3>
                            <p>Alle vergelijkbare velden tussen de person en lead hebben dezelfde waarden.</p>
                        </div>
                    </div>
                @else
                    <div class="panel">
                        <div class="panel-header">
                            <h3>Veld Verschillen</h3>
                            <p class="text-sm text-gray-600">
                                Selecteer welke velden je wilt bijwerken. Je kunt ook de lead waarden aanpassen voordat je opslaat.
                            </p>
                        </div>

                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">Update</th>
                                            <th style="width: 200px;">Veld</th>
                                            <th>Person Waarde</th>
                                            <th>Lead Waarde</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($fieldDifferences as $field => $difference)
                                            <tr>
                                                <td>
                                                    <input 
                                                        type="checkbox" 
                                                        name="person_updates[{{ $field }}]" 
                                                        value="1"
                                                        id="update_{{ $field }}"
                                                        class="form-checkbox"
                                                    >
                                                </td>
                                                <td>
                                                    <label for="update_{{ $field }}" class="font-medium">
                                                        {{ $difference['label'] }}
                                                    </label>
                                                </td>
                                                <td>
                                                    <div class="text-sm">
                                                        @if($difference['type'] === 'array')
                                                            <span class="text-gray-600">{{ $difference['person_value'] ?: 'Geen waarde' }}</span>
                                                        @else
                                                            <span class="text-gray-600">{{ $difference['person_value'] ?: 'Geen waarde' }}</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($difference['type'] === 'array')
                                                        <div class="text-sm">
                                                            <span class="text-blue-600">{{ $difference['lead_value'] ?: 'Geen waarde' }}</span>
                                                        </div>
                                                        <input type="hidden" name="lead_updates[{{ $field }}]" value="{{ $difference['lead_value'] }}">
                                                    @else
                                                        <input 
                                                            type="text" 
                                                            name="lead_updates[{{ $field }}]" 
                                                            value="{{ $difference['lead_value'] }}"
                                                            class="form-control"
                                                            placeholder="Geen waarde"
                                                        >
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="panel mt-4">
                        <div class="panel-body">
                            <div class="flex justify-between items-center">
                                <div>
                                    <button type="button" id="select-all" class="btn btn-outline">
                                        Alles selecteren
                                    </button>
                                    <button type="button" id="select-none" class="btn btn-outline ml-2">
                                        Niets selecteren
                                    </button>
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="icon-save"></i>
                                        Wijzigingen opslaan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </form>

        {!! view_render_event('admin.contacts.persons.edit_with_lead.content.after', ['person' => $person, 'lead' => $lead]) !!}
    </div>
@stop

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select all/none functionality
            const selectAllBtn = document.getElementById('select-all');
            const selectNoneBtn = document.getElementById('select-none');
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="person_updates"]');

            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                });
            }

            if (selectNoneBtn) {
                selectNoneBtn.addEventListener('click', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                });
            }

            // Form submission
            const form = document.getElementById('person-lead-update-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(form);
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    // Show loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="icon-spinner animate-spin"></i> Bezig met opslaan...';

                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.message) {
                            // Show success message
                            alert(data.message);

                            // Redirect if URL provided
                            if (data.redirect_url) {
                                setTimeout(() => {
                                    window.location.href = data.redirect_url;
                                }, 1000);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Er is een fout opgetreden bij het opslaan.');
                    })
                    .finally(() => {
                        // Restore button state
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
                });
            }
        });
    </script>
@endpush

@push('styles')
    <style>
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            vertical-align: middle;
            padding: 12px;
        }

        .form-checkbox {
            width: 18px;
            height: 18px;
        }

        .empty-page {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-page-icon {
            margin-bottom: 20px;
        }

        .empty-page-content h3 {
            margin-bottom: 10px;
            color: #374151;
        }

        .empty-page-content p {
            color: #6b7280;
            max-width: 400px;
            margin: 0 auto;
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
@endpush