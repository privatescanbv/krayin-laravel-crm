<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\PatientMessageSenderType;
use App\Models\PatientMessage;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

test('it_reopens_existing_activity_when_new_patient_message_is_created_for_same_person', function () {
    // Arrange
    $user = User::factory()->create();
    $person = Person::factory()->create();

    // 1. Create first message
    $message1 = PatientMessage::create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::PATIENT, // or STAFF
        'body'        => 'First message',
        'is_read'     => false,
    ]);

    // Assert activity created
    $this->assertNotNull($message1->activity_id);
    $activity = Activity::find($message1->activity_id);
    $this->assertNotNull($activity);
    $this->assertEquals(ActivityType::PATIENT_MESSAGE, $activity->type);

    // Ensure activity is linked to person
    $this->assertTrue($activity->persons->contains($person->id));

    // 2. Close the activity
    $activity->is_done = 1;
    $activity->save();

    // 3. Create second message for same person
    $message2 = PatientMessage::create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::PATIENT,
        'body'        => 'Second message',
        'is_read'     => false,
    ]);

    // Assert message2 is linked to the SAME activity
    $this->assertEquals($activity->id, $message2->activity_id, 'The second message should be linked to the existing activity.');

    // Assert activity is reopened
    $activity->refresh();
    $this->assertEquals(0, $activity->is_done, 'The activity should be reopened.');

    // Verify no new activity was created (count should be 1)
    // Note: Use whereHas or join to check activities for this person if strictly needed,
    // but checking activity count in DB might be enough if clean DB.
    // However, since we rely on `activity_id` of message, verifying message2->activity_id is enough.
});
