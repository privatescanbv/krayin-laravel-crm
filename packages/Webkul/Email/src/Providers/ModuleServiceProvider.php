<?php

namespace Webkul\Email\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Email\Models\Attachment;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Email::class,
        Attachment::class,
        Folder::class,
    ];
}
