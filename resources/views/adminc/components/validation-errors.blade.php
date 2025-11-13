@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
        <h3 class="mb-2 text-sm font-medium">Er zijn validatiefouten opgetreden:</h3>
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

