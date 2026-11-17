# 01 — Patiëntstatus van een sale

**Afhankelijk van:** –  ·  **Grootte:** S

## Doel

Een grove, patiëntvriendelijke status per sale, afgeleid van de interne salesfase. De patiënt
ziet nooit de interne fasenaam.

## Scope

- `app/Enums/PatientSaleStatus.php` met cases `received`, `in_review`, `decision`, `scheduled`,
  `aftercare`, `completed`, `closed`.
- `fromStage(?PipelineStage $stage): self` met een `match`; onbekende of ontbrekende fase → `received`.
- `label(Departments $department): string`, Nederlands, bijv. "Aanvraag ontvangen",
  "In behandeling bij Privatescan", "Ingepland", "Nazorg", "Afgerond", "Gestopt".
- `isClosed(): bool` (`completed` of `closed`) en een statische lijst van fase-ids per groep, zodat
  ticket 02 op `active|closed` kan filteren in SQL.

## Mapping

| Status | Fases (`App\Enums\PipelineStage`) |
|---|---|
| received | `SALES_ORDER_PREVENTIE_HERNIA`, `SALES_DOCTOR_ASSESSMENT_HERNIA`, onbekend |
| in_review | `SALES_IN_BEHANDELING`, `SALES_ASSESSMENT_DONE_HERNIA`, `SALES_PATIENT_REFLECTION_TIME_HERNIA` |
| decision | `SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA`, `SALES_WAIT_HEALTH_INSURER_HERNIA`, `SALES_CONFIRM_TREATMENT_HERNIA` |
| scheduled | `SALES_TREATMENT_PLANNED_HERNIA`, `SALES_AFTERCARE1_HERNIA` (operatie) |
| aftercare | `SALES_AFTERCARE2_HERNIA`, `SALES_PHYSICAL_CONSULTATION_HERNIA` |
| completed | `SALES_MET_SUCCES_AFGEROND`, `SALES_COMPLETE_HERNIA` |
| closed | `SALES_NIET_SUCCESVOL_AFGEROND`, `SALES_COMPLETE_NOT_SUCCESSFULLY_HERNIA` |

Privatescan heeft maar drie salesfases. Regel in ticket 02: een `in_review`-sale met een geplande
afspraak in de toekomst wordt `scheduled`.

## Open vraag

- Klopt de groepering van de Hernia-fases (vooral `decision` en "operatie = scheduled")? Zie ook V-P3 in
  het api-voorstel.

## Acceptatie

- Unit-test die elke sales-fase van beide pipelines op een status mapt (geen fase valt stil naar de fallback).
- Labels per afdeling getest voor minstens `in_review`.
