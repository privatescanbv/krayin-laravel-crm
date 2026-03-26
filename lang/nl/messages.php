<?php

return [

    'organization' => [
        'name_required' => 'Naam is verplicht.',
        'not_found'     => 'Organisatie niet gevonden.',
        'update_error'  => 'Fout bij bijwerken van de organisatie: :error',
    ],

    'person' => [
        'merge_success'     => 'Personen succesvol samengevoegd.',
        'merge_failed'      => 'Samenvoegen mislukt: :error',
        'match_score_error' => 'Kon de matchscore niet berekenen.',
    ],

    'lead' => [
        'merge_success' => 'Leads succesvol samengevoegd.',
        'merge_failed'  => 'Samenvoegen mislukt: :error',
    ],

    'activity' => [
        'duplicate_lead'   => 'Er bestaat al een openstaande activiteit met dezelfde titel voor deze lead.',
        'duplicate_clinic' => 'Er bestaat al een openstaande activiteit met dezelfde titel voor deze kliniek.',
    ],

    'sales' => [
        'created'           => 'Sales aangemaakt.',
        'updated'           => 'Sales bijgewerkt.',
        'deleted'           => 'Sales verwijderd.',
        'stage_updated'     => 'Sales fase bijgewerkt.',
        'activity_created'  => 'Activiteit aangemaakt.',
        'email_detached'    => 'E-mail ontkoppeld.',
    ],

    'email' => [
        'server_error'          => 'Er is een interne serverfout opgetreden.',
        'entity_required'       => 'Minimaal één van lead_id, person_id of sales_lead_id is vereist.',
        'template_not_found'    => 'E-mailsjabloon niet gevonden.',
        'template_render_error' => 'Sjabloon niet gevonden of fout bij renderen.',
    ],

    'search' => [
        'term_too_long'   => 'Zoekterm is te lang (maximaal 50 tekens).',
        'invalid_field'   => 'Ongeldig zoekveld.',
    ],

    'group' => [
        'not_found' => 'Groep niet gevonden: :name',
    ],

    'product' => [
        'not_found' => 'Product niet gevonden.',
    ],

];
