# 06 — Tab Dateien: documenten van een sale

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
