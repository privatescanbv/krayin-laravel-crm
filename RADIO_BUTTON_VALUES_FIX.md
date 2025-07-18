# Radio Button Values Fix

## Probleem
De radio buttons in het anamnesis edit formulier toonden geen geselecteerde waarden wanneer er al data aanwezig was. Dit gebeurde omdat:

1. **Strikte vergelijking**: De radio buttons gebruikten strikte vergelijking (`===`) met integers
2. **Database waarden**: De database slaat boolean waarden op als strings ('0' en '1')
3. **Type mismatch**: `'1' === 1` is `false` in PHP, dus geen radio button werd geselecteerd

## Oplossing
Alle vergelijkingen in het anamnesis edit formulier zijn gewijzigd van strikte (`===`) naar losse (`==`) vergelijking.

### Wat er is gewijzigd:

#### Voor Radio Buttons:
```php
// Oud (werkte niet):
{{ $anamnesis->metalen === 1 ? 'checked' : '' }}
{{ $anamnesis->metalen === 0 ? 'checked' : '' }}

// Nieuw (werkt wel):
{{ $anamnesis->metalen == 1 ? 'checked' : '' }}
{{ $anamnesis->metalen == 0 ? 'checked' : '' }}
```

#### Voor Comment Field Display:
```php
// Oud (werkte niet):
style="display: {{ $anamnesis->metalen === 1 ? 'block' : 'none' }}"

// Nieuw (werkt wel):
style="display: {{ $anamnesis->metalen == 1 ? 'block' : 'none' }}"
```

## Gewijzigde Velden
Alle boolean velden in het anamnesis formulier zijn gerepareerd:

### Medische Condities:
- metalen
- medicijnen
- glaucoom
- claustrofobie
- dormicum
- hart_operatie_c
- implantaat_c
- operaties_c

### Erfelijke Factoren:
- hart_erfelijk
- vaat_erfelijk
- tumoren_erfelijk

### Levensstijl:
- smoking
- diabetes
- actief
- spijsverteringsklachten

### Overige:
- allergie_c
- rugklachten
- heart_problems

## Resultaat
Nu worden:
1. **Opgeslagen waarden correct getoond** in de radio buttons
2. **Comment velden automatisch getoond** als de waarde 'Ja' (1) is
3. **Geen selectie getoond** als er geen data is (null waarden)

## Technische Details
- **Losse vergelijking (`==`)** converteert automatisch tussen string en integer
- **Strikte vergelijking (`===`)** vereist exact hetzelfde type
- **Database boolean waarden** worden vaak opgeslagen als strings ('0', '1')
- **PHP type coercion** zorgt ervoor dat '1' == 1 `true` is

## Verificatie
Test het formulier door:
1. Een anamnesis record te bewerken met bestaande data
2. Te controleren of de juiste radio buttons geselecteerd zijn
3. Te controleren of comment velden zichtbaar zijn bij 'Ja' selecties
4. Een nieuw anamnesis record aan te maken (geen selecties verwacht)