<?php

require_once 'vendor/autoload.php';

use Webkul\User\Models\User;
use Webkul\User\Models\Role;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create a test role
$role = Role::first() ?? Role::create([
    'name'            => 'Test Admin',
    'description'     => 'Test Administrator role',
    'permission_type' => 'all',
    'permissions'     => [],
]);

// Create test users
$user1 = User::create([
    'name'     => 'Test User 1',
    'email'    => 'user1@test.com',
    'password' => bcrypt('password'),
    'status'   => 1,
    'role_id'  => $role->id,
]);

$user2 = User::create([
    'name'     => 'Test User 2',
    'email'    => 'user2@test.com',
    'password' => bcrypt('password'),
    'status'   => 1,
    'role_id'  => $role->id,
]);

echo "Created users:\n";
echo "User 1 ID: {$user1->id}\n";
echo "User 2 ID: {$user2->id}\n\n";

// Test 1: Create user as user1
auth()->login($user1);

$newUser = User::create([
    'name'     => 'New User',
    'email'    => 'newuser@test.com',
    'password' => bcrypt('password'),
    'status'   => 1,
    'role_id'  => $role->id,
]);

echo "Created new user:\n";
echo "ID: {$newUser->id}\n";
echo "Created by: {$newUser->created_by}\n";
echo "Updated by: {$newUser->updated_by}\n";
echo "Expected created_by: {$user1->id}\n";
echo "Expected updated_by: {$user1->id}\n\n";

// Test 2: Update user as user2
auth()->login($user2);

$newUser->update(['name' => 'Updated User Name']);

echo "Updated user:\n";
echo "Updated by: {$newUser->updated_by}\n";
echo "Expected updated_by: {$user2->id}\n\n";

// Test 3: Check if the update worked
$updatedUser = User::find($newUser->id);
echo "Final check:\n";
echo "Created by: {$updatedUser->created_by}\n";
echo "Updated by: {$updatedUser->updated_by}\n";
echo "Expected created_by: {$user1->id}\n";
echo "Expected updated_by: {$user2->id}\n";

if ($updatedUser->created_by == $user1->id && $updatedUser->updated_by == $user2->id) {
    echo "\n✅ Audit trail test PASSED!\n";
} else {
    echo "\n❌ Audit trail test FAILED!\n";
}