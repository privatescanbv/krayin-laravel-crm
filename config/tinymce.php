<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TinyMCE image support
    |--------------------------------------------------------------------------
    |
    | When disabled, the image plugin, toolbar button, upload handler and
    | file picker are not registered in the admin rich text editor.
    |
    */

    'images_enabled' => filter_var(env('TINYMCE_IMAGES_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

];
