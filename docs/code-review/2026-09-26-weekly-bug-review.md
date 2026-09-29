# Weekly code review – 2026-09-26 (top 5 bugs)

All five have been fixed in the working tree. Nothing is committed. Regression tests are in
`tests/Feature/CodeReview/WeeklyReview20260926Test.php`.
**The tests have not been run** because this review environment has no PHP. Before merging, run:
`sail artisan test --filter=WeeklyReview20260926`.

## 1. SQL injection in the Lead datagrid (security, high)
`packages/Webkul/Admin/src/DataGrids/Lead/LeadDataGrid.php`
```php
$queryBuilder->havingRaw($tablePrefix.'rotten_lead = '.request()->input('rotten_lead.in'));
```
The value from the query string was concatenated straight into the SQL. Any logged-in user
could run their own SQL through `GET /admin/leads/get?rotten_lead[in]=...`, for example to
read `users`, API keys or patient data.
**Fix:** bind the value as a parameter, cast it to 0/1, and ignore non-scalar input.

## 2. Mutating sales-lead routes had no ACL check (authorization, high)
`packages/Webkul/Admin/src/Config/acl.php`. The Bouncer only checks routes that appear in `acl.php`.
These routes were missing, so any employee with a login could call them:
- `admin.sales-leads.stage.update`
- `admin.sales-leads.lost`
- `create-preventie-sales` / `create-hernia-sales`
- `attach_person`
- `debug`

This matters because a stage change to "lost" also marks orders LOST, clears planning and removes GVL forms.
**Fix:**
- stage, lost and attach-person now require `sales-leads.edit`
- the two referral routes now require `sales-leads.create`
- debug now requires `sales-leads.view`

*Assumption:* custom roles that should be able to change sales stages already have
`sales-leads.edit`. Check this in the role settings after deploying.

## 3. Referral sales orders ended up in the wrong order pipeline (business logic, high)
`OrderRepository::cleanUpFromLostSales()` and `OrderConfirmationController` (all persons confirmed)
picked the target stage from the **lead's** department:
```php
Order::lostOrderStageId($sales->lead?->department);
Order::orderSendByDepartmentStageId($order->salesLead?->lead?->department);
```
Since the HerniaPoli referral flow (d83bade59), a Preventie sales hangs off a Hernia lead. When
that sales is lost or confirmed, its Privatescan order was moved into the Hernia order pipeline
(stage 47/Bevestigd-Hernia). The order then disappeared from the Privatescan kanban and reports.
**Fix:** new `Order::lostStageIdForOwnPipeline()` / `sentStageIdForOwnPipeline()`. These use the
order's own current pipeline and fall back to `SalesLead::getDepartment()`.
`OrderObserver::deleting` uses the same helper.

## 4. Paid €0 orders showed "Niet van toepassing" instead of "Credit" (financial, medium)
`app/Enums/OrderPaymentStatus.php`
```php
if ($total <= 0) { return self::NOT_APPLICABLE; }
```
When every order line is set to LOST after a deposit, the total becomes €0. The status then
read "N.v.t.", and the payment overview filters N.v.t. out, so the credit owed to the customer
did not show up anywhere.
**Fix:** a €0 total with more than €0 received now returns `CREDIT`.

## 5. Sales stage update accepted a stage from any pipeline (data integrity, medium)
`SalesLeadController::updateStage()` only validated `exists:lead_pipeline_stages,id`. That let a
Privatescan sales be put into a Hernia, lead or order stage, which breaks the kanban,
webhooks/n8n flows and the "lost" cascade.
**Fix:** it now returns 422 when the target stage is not in the sales' current pipeline.
Switching pipeline still goes through the department change in `update()`.

## Noted, not fixed (lower impact)
- `SalesLeadRepository::createFromWonLead()` has no transaction. If order creation fails, a
  SalesLead without an Order stays behind. The failure is logged and the method returns null.
- `ApiKeyAuth` logs the full invalid API key in plain text (`provided_api_key`).
- `OrderRefundService`: a surplus of exactly €0.01 is skipped (`<= 0.01`).
