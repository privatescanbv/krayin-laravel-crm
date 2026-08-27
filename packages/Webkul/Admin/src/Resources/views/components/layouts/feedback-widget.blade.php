{{-- MB Software feedback widget. Only rendered when FEEDBACK_WIDGET_KEY is set. --}}
@php($feedbackWidgetKey = config('services.feedback_widget.key'))

@if (! empty($feedbackWidgetKey))
    <script
        src="https://orchestrator.mbsoftware.nl/widget/feedback.js?v=1.2.0"
        data-key="{{ $feedbackWidgetKey }}"
        async
    ></script>
@endif
