@php
    /*
     * Inline image component — embeds an image as a Base64 data URI so it works
     * in emails that are proxied by Gmail/Outlook without access to the server.
     *
     * Usage:
     *   @include('adminc.components.inline-image', [
     *       'path'  => 'images/logo.svg',   // relative to public_path()
     *       'alt'   => 'Logo',
     *       'class' => 'logo',              // optional
     *       'style' => 'height:40px;',      // optional
     *   ])
     */
    $absolutePath = public_path($path);
    $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'svg'        => 'image/svg+xml',
        'png'        => 'image/png',
        'jpg', 'jpeg'=> 'image/jpeg',
        'gif'        => 'image/gif',
        'webp'       => 'image/webp',
        default      => 'image/png',
    };
    $src = file_exists($absolutePath)
        ? "data:{$mime};base64," . base64_encode(file_get_contents($absolutePath))
        : asset($path);
@endphp
<img
    src="{{ $src }}"
    alt="{{ $alt ?? '' }}"
    @if (!empty($class)) class="{{ $class }}" @endif
    @if (!empty($style)) style="{{ $style }}" @endif
>
