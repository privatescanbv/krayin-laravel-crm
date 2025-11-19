@unless ($breadcrumbs->isEmpty())
    <nav aria-label="">
        <ol class="flex flex-wrap items-baseline ">
            @foreach ($breadcrumbs as $breadcrumb)
                @if (
                    $breadcrumb->url 
                    && ! $loop->last
                )
                    <li class="flex items-center gap-x-1 text-xs font-normal text-brandColor dark:text-brandColor">
                        <a href="{{ $breadcrumb->url }}">
                            {{ $breadcrumb->title }}
                        </a>

                        <span class="after:content-['/'] ltr:mr-1 rtl:ml-1"></span>
                    </li>
                @else
                    <li 
                        class="flex items-center gap-x-1 text-xs after:content-['/'] last:cursor-default after:last:hidden dark:text-gray-300" 
                        aria-current="page"
                    >
                        {{ $breadcrumb->title }}
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>
@endunless
