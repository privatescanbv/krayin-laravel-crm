@props([
    'title',
    'entity',
    'sections' => ['algemeen', 'activiteiten'],
])
{{--
    Shared detail-page shell: sticky left card (breadcrumb, info, actions, navigation, dates) + content area.
    Slots: breadcrumb, info, actions, navigation. Default slot = the `v-if="leadDetailSection === ..."` panels.
    Section switching state (`leadDetailSection`, `setSection`) is shared with entity-navigation-menu and synced to the URL hash.
--}}
<x-admin::layouts>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    <div class="relative flex flex-col gap-4 pt-3 max-lg:flex-wrap lg:grid lg:grid-cols-[394px,minmax(0,1fr)]">
        <div class="flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border bg-white dark:border-gray-800 dark:bg-gray-900 max-lg:min-w-full max-lg:max-w-full lg:sticky lg:top-[73px] [&>div:last-child]:border-b-0">
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between">{{ $breadcrumb }}</div>

                {{ $info }}

                @isset($actions)
                    <div class="flex flex-wrap gap-2">{{ $actions }}</div>
                @endisset
            </div>

            {{ $navigation }}

            <div class="flex w-full flex-col gap-2 border-t border-gray-200 p-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <div class="flex justify-between">
                    <span>Aangemaakt:</span>
                    <span>{{ $entity->created_at?->format('d-m-Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Laatst gewijzigd:</span>
                    <span>{{ $entity->updated_at?->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        <div class="flex w-full flex-col gap-4">{{ $slot }}</div>
    </div>

    @pushOnce('scripts', 'entity-view-sections')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof app === 'undefined') return;

                app.mixin({
                    data() {
                        return { leadDetailSection: 'algemeen' };
                    },

                    mounted() {
                        const hash = window.location.hash.substring(1);

                        if (@json($sections).includes(hash)) {
                            this.leadDetailSection = hash;
                        }
                    },

                    methods: {
                        setSection(section) {
                            this.leadDetailSection = section;
                            window.location.hash = section;
                        },
                    },
                });
            });
        </script>
    @endPushOnce

    {{ $scripts ?? '' }}
</x-admin::layouts>
