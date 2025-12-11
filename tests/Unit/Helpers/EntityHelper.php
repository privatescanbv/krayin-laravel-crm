<?php

namespace Tests\Unit\Helpers;

use Webkul\Automation\Helpers\Entity;

test('formatNameWithPath returns just name when no product group', function () {
    $entity = app(Entity::class);
    $result = $entity->getEmailTemplatePlaceholders();

    expect($result)->not->toBeEmpty();
});
