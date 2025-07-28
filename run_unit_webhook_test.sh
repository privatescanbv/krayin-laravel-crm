#!/bin/bash

echo "🔗 Running Unit Lead Webhook Test"
echo "=================================="

# Run the specific unit test file
./vendor/bin/pest tests/Unit/LeadWebhookTest.php --verbose

echo ""
echo "✅ Unit test completed!"
echo ""
echo "This unit test verifies that:"
echo "- Webhook is NOT sent on lead creation when pipeline will be updated"
echo "- Webhook IS sent on lead creation when pipeline won't be updated"
echo "- Webhook IS sent on lead update when stage changed"
echo "- The fix prevents duplicate webhooks during lead creation"