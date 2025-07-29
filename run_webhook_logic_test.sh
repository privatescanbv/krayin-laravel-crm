#!/bin/bash

echo "🔗 Running Webhook Logic Test"
echo "=============================="

# Run the specific logic test file
./vendor/bin/pest tests/Unit/LeadWebhookLogicTest.php --verbose

echo ""
echo "✅ Logic test completed!"
echo ""
echo "This test verifies the core webhook logic:"
echo "- willPipelineBeUpdated() method works correctly"
echo "- Returns true when pipeline needs updating"
echo "- Returns false when pipeline is already correct"
echo "- Handles null department correctly"
echo "- Works for both Hernia and Privatescan departments"