# 06 — Tab Dateien: documenten van een sale

- **Status:** gebouwd (2026-11-24)
  - Repo/branch: krayin-laravel-crm, `development`, ongecommit in de werkmap.
  - Gedaan: `GET …/documents` (gepagineerd) en `GET …/documents/{documentId}/download` (`ClinicSalesLeadDocumentController`); bestanden van file-activiteiten op de sale of een order van de sale, alleen types met `show_in_clinic_portal` (`SalesLead::clinicPortalFiles()`, hergebruikt `Activity::visibleInClinicPortal`); `counts.files` in de detail-resource; Bearer-whitelist.
  - Afwijking: de download **streamt** via dezelfde geauthenticeerde route (zoals de patiënt-download) in plaats van een signed URL; `download_url` in de lijst wijst daarnaar. Een "document" is één bestand (`activity_files`-rij), niet een activiteit.
  - Nog: portaal laten aansluiten; uploaden/verwijderen blijft buiten scope.

**Afhankelijk van:** [portaal/01](../portaal/01-document-type.md)  ·  **Grootte:** M  ·  **Bron:** [gap-analyse](00-gap-analyse.md) §3.2

## Scope

- `GET clinic/{keycloakUserId}/sales-leads/{saleId}/documents`: file-activiteiten met `sales_lead_id` van deze
  sale of een `order_id` van een order van deze sale. Per document: naam, `type: {id, label}`, datum, grootte.
- Alleen documenttypes met `show_in_clinic_portal = true` (scope uit portaal/01).
- `GET …/documents/{documentId}/download`: kortlevende signed URL (`URL::temporarySignedRoute`, 5 min) of
  stream; zelfde bereik- en typecontrole als de lijst.
- `counts.files` in de detail-resource voor het tabblad.
- Bearer-whitelist in `ApiKeyAuth` uitbreiden.

Uploaden en verwijderen vanuit het portaal horen niet bij dit ticket.

## Acceptatie

- Document van een type met `show_in_clinic_portal = false` staat niet in de lijst en de download geeft 404.
- Document van een sale van een andere arts: 404, ook via de download.
- `counts.files` klopt met de lijst.
