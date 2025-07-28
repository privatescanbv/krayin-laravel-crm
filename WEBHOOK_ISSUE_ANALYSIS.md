# Webhook Duplication Issue Analysis

## Problem Description

When creating a lead via the API, **2 webhooks are being sent to n8n instead of 1**. This causes duplicate processing and potential data inconsistencies.

## Root Cause Analysis

The issue occurs in the lead creation flow due to the following sequence:

1. **API Call**: `POST /api/leads` calls `LeadController@store`
2. **Lead Creation**: `AdminLeadController@storeLead` creates the lead with a technical pipeline stage
3. **First Webhook**: `LeadObserver@created` is triggered and sends webhook #1
4. **Pipeline Update**: `LeadObserver@updatePipelineState` updates the pipeline based on department
5. **Second Webhook**: `LeadObserver@updated` is triggered (because pipeline stage changed) and sends webhook #2

### Code Flow Analysis

```php
// API LeadController@store (line 119)
$request['lead_pipeline_stage_id'] = PipelineDefaultKeys::PIPELINE_TECHNICAL_STAGE_ID->value;
$request['lead_pipeline_id'] = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;

// AdminLeadController@storeLead creates the lead
$lead = $this->leadRepository->create($data);
Event::dispatch('lead.create.after', $lead);

// LeadObserver@created is called
public function created(Lead $lead): void
{
    // ... other code ...
    $this->updatePipelineState($lead); // This updates the pipeline
    $this->sendWebhook($lead, 'LeadObserver@created'); // Webhook #1
}

// updatePipelineState triggers an update which calls LeadObserver@updated
private function updatePipelineState(Lead $lead): void
{
    // ... department logic ...
    if ($lead->lead_pipeline_id != $leadPipelineId) {
        $lead->update([
            'lead_pipeline_id'       => $leadPipelineId,
            'lead_pipeline_stage_id' => $leadPipelineStageId,
        ]); // This triggers LeadObserver@updated
    }
}

// LeadObserver@updated is called
public function updated(Lead $lead): void
{
    // ... other code ...
    if ($lead->wasChanged('lead_pipeline_stage_id') && $lead->stage) {
        $this->sendWebhook($lead, 'LeadObserver@updated'); // Webhook #2
    }
}
```

## Test Implementation

Created `tests/Feature/ApiLeadWebhookTest.php` with the following test cases:

### 1. `test_lead_creation_via_api_sends_only_one_webhook()`
- Mocks the WebhookService to count webhook calls
- Creates a lead via API
- **Asserts that exactly 1 webhook is sent**
- Verifies webhook contains correct data

### 2. `test_lead_creation_via_api_with_operatie_type_sends_only_one_webhook()`
- Tests the "Operatie" lead type which triggers Hernia department logic
- Verifies only 1 webhook is sent even with department changes
- Confirms webhook contains correct department information

### 3. `test_webhook_contains_correct_lead_data()`
- Validates the webhook payload structure
- Ensures all required fields are present
- Verifies data accuracy

## Expected Test Results

**Current Behavior (Failing Test):**
```
Expected exactly 1 webhook call, but got 2. 
Webhook calls: [
  {"data":{...},"type":"lead_pipeline_change","caller":"LeadObserver@created"},
  {"data":{...},"type":"lead_pipeline_change","caller":"LeadObserver@updated"}
]
```

## Proposed Solutions

### Solution 1: Prevent Webhook in Observer Created Method (Recommended)
Modify `LeadObserver@created` to not send webhook if pipeline will be updated:

```php
public function created(Lead $lead): void
{
    // Set created_by if not already set
    if (is_null($lead->created_by) && auth()->check()) {
        DB::table('leads')->where('id', $lead->id)->update(['created_by' => auth()->id()]);
    }

    Log::info('CREATE lead', [
        'lead_id' => $lead->id,
        'stage'   => $lead->stage?->name,
    ]);
    
    // Update pipeline state first
    $this->updatePipelineState($lead);
    
    // Only send webhook if pipeline wasn't updated (to avoid duplicate)
    // The updated observer will handle the webhook if pipeline changed
    $willUpdatePipeline = $this->willPipelineBeUpdated($lead);
    if (!$willUpdatePipeline) {
        $this->sendWebhook($lead, 'LeadObserver@created');
    }
}

private function willPipelineBeUpdated(Lead $lead): bool
{
    if (is_null($lead->department)) {
        return false;
    }
    
    $expectedPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
    if ($lead->department->name == 'Hernia') {
        $expectedPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
    }
    
    return $lead->lead_pipeline_id != $expectedPipelineId;
}
```

### Solution 2: Flag-Based Approach
Add a flag to prevent duplicate webhooks:

```php
class LeadObserver
{
    private $skipWebhookOnUpdate = false;
    
    public function created(Lead $lead): void
    {
        // ... existing code ...
        $this->skipWebhookOnUpdate = true;
        $this->updatePipelineState($lead);
        $this->skipWebhookOnUpdate = false;
        $this->sendWebhook($lead, 'LeadObserver@created');
    }
    
    public function updated(Lead $lead): void
    {
        // ... existing code ...
        if ($lead->wasChanged('lead_pipeline_stage_id') && $lead->stage && !$this->skipWebhookOnUpdate) {
            $this->sendWebhook($lead, 'LeadObserver@updated');
        }
    }
}
```

### Solution 3: Move Pipeline Logic to API Controller
Set the correct pipeline in the API controller before creating the lead:

```php
// In API LeadController@store
$departmentId = Department::findPrivateScanId();
$pipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
$stageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;

if (isset($request['lead_type_id'])) {
    $leadType = Type::query()->where('id', $request['lead_type_id'])->first();
    if ($leadType && $leadType->name == 'Operatie') {
        $departmentId = Department::findHerniaId();
        $pipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
        $stageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value;
    }
}

$request->merge([
    'user_id' => $currentUserId,
    'status' => 1,
    'department_id' => $departmentId,
    'lead_pipeline_stage_id' => $stageId,
    'lead_pipeline_id' => $pipelineId
]);
```

## Running the Test

To run the webhook test:

```bash
./run_webhook_test.sh
```

Or directly:

```bash
./vendor/bin/pest tests/Feature/ApiLeadWebhookTest.php --verbose
```

## Impact Assessment

- **Current Impact**: Duplicate webhooks cause double processing in n8n workflows
- **Business Impact**: Potential duplicate emails, notifications, or data processing
- **Technical Impact**: Increased server load and potential race conditions

## Recommendation

Implement **Solution 1** as it's the most robust and maintains the existing architecture while preventing duplicate webhooks. The test will verify that the fix works correctly.