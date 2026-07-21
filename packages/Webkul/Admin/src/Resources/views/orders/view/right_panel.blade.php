@isset($order)
    <x-admin::ai-summary subject="orders" :id="$order->id"/>
@else
    <div class="p-4">
        <p>Deze kolom is gereserveerd voor aanvullende widgets en informatie.</p>
    </div>
@endisset
