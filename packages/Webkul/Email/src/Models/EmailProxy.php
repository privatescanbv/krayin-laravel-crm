<?php

namespace Webkul\Email\Models;

use Konekt\Concord\Proxies\ModelProxy;

class EmailProxy extends ModelProxy
{
    /**
     * The model class name.
     *
     * @var string
     */
    protected $modelClass = Email::class;
}
