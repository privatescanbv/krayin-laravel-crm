<?php

use App\Enums\EmailTemplateType;

test('templateTypeFilterValues includes lead and algemeen for lead entity filter', function () {
    expect(EmailTemplateType::LEAD->templateTypeFilterValues())
        ->toBe([EmailTemplateType::LEAD->value, EmailTemplateType::ALGEMEEN->value]);
});

test('templateTypeFilterValues defaults to single db type for other cases', function () {
    expect(EmailTemplateType::PATIENT->templateTypeFilterValues())
        ->toBe([EmailTemplateType::PATIENT->value]);

    expect(EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->templateTypeFilterValues())
        ->toBe([EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value]);
});

test('tryResolveTemplateTypeFilter returns null for unknown entity_type string', function () {
    expect(EmailTemplateType::tryResolveTemplateTypeFilter('not-a-valid-type'))->toBeNull();
});

test('tryResolveTemplateTypeFilter resolves every enum value', function () {
    foreach (EmailTemplateType::cases() as $case) {
        $resolved = EmailTemplateType::tryResolveTemplateTypeFilter($case->value);
        expect($resolved)->toBeArray()->not->toBeEmpty();
        expect($resolved)->toBe($case->templateTypeFilterValues());
    }
});
