#!/bin/bash

echo "🔗 Running API Lead Webhook Test"
echo "================================="

# Run the specific test file
./vendor/bin/pest tests/Feature/ApiLeadWebhookTest.php --verbose

echo ""
echo "✅ Test completed!"
echo ""
echo "This test verifies that:"
echo "- Only 1 webhook is sent when creating a lead via API"
echo "- Webhook contains correct lead data"
echo "- Both regular and Operatie type leads work correctly"
echo "- No duplicate webhooks are triggered during lead creation"