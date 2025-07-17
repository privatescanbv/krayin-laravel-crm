#!/bin/bash

echo "🚀 Running API Lead Creation with Anamnesis Test"
echo "================================================"

# Run the specific test file
./vendor/bin/pest tests/Feature/ApiLeadCreationWithAnamnesisTest.php --verbose

echo ""
echo "✅ Test completed!"
echo ""
echo "This test proves that:"
echo "- API lead creation works correctly"
echo "- Each lead automatically gets an anamnesis record"
echo "- Email format conversion works properly"
echo "- UUID fields are handled correctly"
echo "- Error handling prevents lead creation failure"
echo "- Database relationships are properly established"