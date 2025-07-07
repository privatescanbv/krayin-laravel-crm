{!! view_render_event('admin.leads.edit.address.before') !!}

@if(isset($lead))
    @include('admin::components.address', ['entity' => $lead])
@else
    @include('admin::components.address', ['entity' => null])
@endif

{!! view_render_event('admin.leads.edit.address.after') !!}

@pushOnce('scripts')
@verbatim
    <script type="text/x-template" id="v-address-preview-template">
        <div v-if="fullAddress" class="mt-4 p-3 bg-gray-50 rounded border">
            <div class="text-sm font-medium text-gray-700 mb-1">Adres preview:</div>
            <div class="text-sm text-gray-600">{{ fullAddress }}</div>
        </div>
    </script>

    <script type="module">
        app.component('v-address-preview', {
            template: '#v-address-preview-template',

            props: ['address'],

            computed: {
                fullAddress() {
                    if (!this.address) return '';
                    
                    const parts = [];
                    
                    if (this.address.street && this.address.house_number) {
                        let streetPart = this.address.street + ' ' + this.address.house_number;
                        if (this.address.house_number_suffix) {
                            streetPart += ' ' + this.address.house_number_suffix;
                        }
                        parts.push(streetPart);
                    }
                    
                    if (this.address.postal_code && this.address.city) {
                        parts.push(this.address.postal_code + ' ' + this.address.city);
                    }
                    
                    if (this.address.state) {
                        parts.push(this.address.state);
                    }
                    
                    if (this.address.country) {
                        parts.push(this.address.country);
                    }
                    
                    return parts.join(', ');
                }
            }
        });
    </script>
@endverbatim
@endPushOnce 