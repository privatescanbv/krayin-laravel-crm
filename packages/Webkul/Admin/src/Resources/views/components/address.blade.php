{!! view_render_event('admin.address.before') !!}

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <p class="text-base font-semibold dark:text-white">
            Adresgegevens
        </p>
    </div>

    <!-- Address Form -->
    <div class="grid grid-cols-2 gap-4">
        <!-- Street -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Straat
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="address[street]"
                :value="old('address.street', $entity?->address?->street ?? '')"
                placeholder="Straatnaam"
            />

            <x-admin::form.control-group.error control-name="address.street" />
        </x-admin::form.control-group>

        <!-- House Number -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Huisnummer
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="address[house_number]"
                :value="old('address.house_number', $entity?->address?->house_number ?? '')"
                placeholder="123"
            />

            <x-admin::form.control-group.error control-name="address.house_number" />
        </x-admin::form.control-group>

        <!-- House Number Suffix -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Toevoeging
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="address[house_number_suffix]"
                :value="old('address.house_number_suffix', $entity?->address?->house_number_suffix ?? '')"
                placeholder="A, 1e verdieping, etc."
            />

            <x-admin::form.control-group.error control-name="address.house_number_suffix" />
        </x-admin::form.control-group>

        <!-- Postal Code -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Postcode
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="address[postal_code]"
                :value="old('address.postal_code', $entity?->address?->postal_code ?? '')"
                placeholder="1234 AB"
            />

            <x-admin::form.control-group.error control-name="address.postal_code" />
        </x-admin::form.control-group>

        <!-- City -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Stad
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="address[city]"
                :value="old('address.city', $entity?->address?->city ?? '')"
                placeholder="Amsterdam"
            />

            <x-admin::form.control-group.error control-name="address.city" />
        </x-admin::form.control-group>

        <!-- State -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                Provincie
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="address[state]"
                :value="old('address.state', $entity?->address?->state ?? '')"
                placeholder="Noord-Holland"
            />

            <x-admin::form.control-group.error control-name="address.state" />
        </x-admin::form.control-group>

        <!-- Country -->
        <x-admin::form.control-group class="col-span-2">
            <x-admin::form.control-group.label>
                Land
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="address[country]"
                :value="old('address.country', $entity?->address?->country ?? 'Nederland')"
                placeholder="Nederland"
            />

            <x-admin::form.control-group.error control-name="address.country" />
        </x-admin::form.control-group>
    </div>

    <!-- Address Preview -->
    <v-address-preview
        :address='@json($entity?->address ?? new stdClass())'
    ></v-address-preview>
</div>

{!! view_render_event('admin.address.after') !!}

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
