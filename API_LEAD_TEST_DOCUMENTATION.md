# API Lead Creation Test Documentation

## Overview
This document describes the comprehensive feature test that proves API lead creation works correctly with automatic anamnesis creation.

## Test File
`tests/Feature/ApiLeadCreationWithAnamnesisTest.php`

## Test Cases

### 1. `API lead creation successfully creates a lead with anamnesis`
**Purpose**: Main test that proves the complete functionality works

**What it tests**:
- API endpoint responds with 201 status
- Lead is created in database with correct data
- Email is converted from string to array format
- Anamnesis is automatically created with correct relationship
- UUID fields are properly formatted
- Database relationships work both ways (lead->anamnesis and anamnesis->lead)

**Assertions**:
- HTTP 201 response with correct JSON structure
- Lead exists in database with all provided data
- Email array format is correct with `is_default: true`
- Anamnesis exists with correct `lead_id` and `name`
- UUID fields are 36 characters long
- Bidirectional relationships work

### 2. `API lead creation handles missing optional fields gracefully`
**Purpose**: Test robustness with minimal data

**What it tests**:
- API works with only required fields
- Anamnesis is still created even with minimal data

### 3. `API lead creation fails gracefully with invalid data`
**Purpose**: Test validation and error handling

**What it tests**:
- Invalid data returns 422 status
- No lead or anamnesis is created when validation fails
- Database remains clean after failed requests

### 4. `anamnesis creation failure does not prevent lead creation`
**Purpose**: Test error resilience

**What it tests**:
- Lead creation succeeds even if anamnesis creation might fail
- The try-catch block in LeadRepository works correctly

### 5. `API lead creation with different lead types works correctly`
**Purpose**: Test department logic and type handling

**What it tests**:
- Different lead types are handled correctly
- Department assignment logic works
- Each lead gets its own anamnesis regardless of type

### 6. `anamnesis has correct UUID format and relationships`
**Purpose**: Test UUID handling and data integrity

**What it tests**:
- Anamnesis ID is valid UUID format
- `created_by` field is UUID format (when not null)
- Timestamps are properly set
- `user_id` is numeric (integer)

### 7. `API response includes correct lead data structure`
**Purpose**: Test API response format

**What it tests**:
- Response has correct JSON structure
- Returned lead ID is valid integer
- Lead actually exists with the returned ID

## Running the Tests

### Method 1: Using the provided script
```bash
chmod +x run_api_lead_test.sh
./run_api_lead_test.sh
```

### Method 2: Using Pest directly
```bash
./vendor/bin/pest tests/Feature/ApiLeadCreationWithAnamnesisTest.php
```

### Method 3: Using PHPUnit
```bash
./vendor/bin/phpunit tests/Feature/ApiLeadCreationWithAnamnesisTest.php
```

### Method 4: Run specific test
```bash
./vendor/bin/pest tests/Feature/ApiLeadCreationWithAnamnesisTest.php --filter "API lead creation successfully creates a lead with anamnesis"
```

## Test Data Setup

The test automatically sets up required test data:
- Seeds lead channels using `LeadChannelSeeder`
- Creates test users via factory
- Uses existing sources, types, and channels from base seeders

## Expected Results

When all tests pass, it proves:

✅ **API Functionality**
- POST `/api/leads` endpoint works correctly
- Returns proper HTTP status codes
- JSON response structure is correct

✅ **Data Integrity**
- Lead data is stored correctly in database
- Email format conversion works (string → array)
- All required fields are populated

✅ **Anamnesis Auto-Creation**
- Every lead automatically gets an anamnesis record
- Anamnesis has correct name format: "Anamnesis voor {lead_title}"
- Proper UUID generation for ID and created_by fields
- Correct user_id assignment

✅ **Relationships**
- Lead → Anamnesis relationship works
- Anamnesis → Lead relationship works
- Database foreign keys are properly set

✅ **Error Handling**
- Validation errors are handled properly
- Anamnesis creation errors don't break lead creation
- Database transactions maintain integrity

✅ **Edge Cases**
- Minimal data requests work
- Different lead types are handled
- UUID format validation passes

## Troubleshooting

If tests fail, check:

1. **Database Setup**: Ensure test database is configured and migrations are run
2. **Seeders**: Make sure required seeders (PipelineSeeder, TypeSeeder, etc.) have run
3. **Dependencies**: Check that all required models and factories exist
4. **Permissions**: Ensure test database has proper write permissions

## Integration with CI/CD

This test can be integrated into CI/CD pipelines to ensure:
- API functionality remains stable
- Anamnesis auto-creation doesn't break
- Database relationships are maintained
- New changes don't break existing functionality

## Test Coverage

This test covers:
- API endpoint functionality
- Database operations
- Model relationships
- UUID handling
- Error handling
- Data validation
- Email format conversion
- Department assignment logic