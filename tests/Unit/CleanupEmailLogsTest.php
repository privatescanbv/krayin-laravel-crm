<?php

namespace Tests\Unit;

use App\Console\Commands\CleanupEmailLogs;
use App\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupEmailLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_removes_old_logs()
    {
        // Create old logs (8 days ago)
        EmailLog::create([
            'sync_type' => 'graph',
            'started_at' => Carbon::now()->subDays(8),
            'completed_at' => Carbon::now()->subDays(8)->addMinutes(5),
            'processed_count' => 10,
            'error_count' => 0,
        ]);

        // Create recent logs (3 days ago)
        EmailLog::create([
            'sync_type' => 'imap',
            'started_at' => Carbon::now()->subDays(3),
            'completed_at' => Carbon::now()->subDays(3)->addMinutes(2),
            'processed_count' => 5,
            'error_count' => 1,
        ]);

        // Create very recent logs (1 day ago)
        EmailLog::create([
            'sync_type' => 'graph',
            'started_at' => Carbon::now()->subDay(),
            'completed_at' => Carbon::now()->subDay()->addMinutes(3),
            'processed_count' => 15,
            'error_count' => 0,
        ]);

        $this->assertDatabaseCount('email_logs', 3);

        // Run cleanup with 7 days retention
        $this->artisan('emails:cleanup-logs')
            ->expectsConfirmation('Are you sure you want to delete 1 email logs?', 'yes')
            ->assertExitCode(0);

        // Should only keep logs from 3 days ago and 1 day ago
        $this->assertDatabaseCount('email_logs', 2);
        
        // Verify old log is deleted
        $this->assertDatabaseMissing('email_logs', [
            'sync_type' => 'graph',
            'processed_count' => 10,
        ]);

        // Verify recent logs are kept
        $this->assertDatabaseHas('email_logs', [
            'sync_type' => 'imap',
            'processed_count' => 5,
        ]);

        $this->assertDatabaseHas('email_logs', [
            'sync_type' => 'graph',
            'processed_count' => 15,
        ]);
    }

    public function test_cleanup_with_custom_days()
    {
        // Create logs from 5 days ago
        EmailLog::create([
            'sync_type' => 'graph',
            'started_at' => Carbon::now()->subDays(5),
            'completed_at' => Carbon::now()->subDays(5)->addMinutes(5),
            'processed_count' => 10,
            'error_count' => 0,
        ]);

        // Create logs from 2 days ago
        EmailLog::create([
            'sync_type' => 'imap',
            'started_at' => Carbon::now()->subDays(2),
            'completed_at' => Carbon::now()->subDays(2)->addMinutes(2),
            'processed_count' => 5,
            'error_count' => 1,
        ]);

        $this->assertDatabaseCount('email_logs', 2);

        // Run cleanup with 3 days retention
        $this->artisan('emails:cleanup-logs', ['--days' => '3'])
            ->expectsConfirmation('Are you sure you want to delete 1 email logs?', 'yes')
            ->assertExitCode(0);

        // Should only keep logs from 2 days ago
        $this->assertDatabaseCount('email_logs', 1);
        
        // Verify old log is deleted
        $this->assertDatabaseMissing('email_logs', [
            'sync_type' => 'graph',
            'processed_count' => 10,
        ]);

        // Verify recent log is kept
        $this->assertDatabaseHas('email_logs', [
            'sync_type' => 'imap',
            'processed_count' => 5,
        ]);
    }

    public function test_cleanup_with_no_logs()
    {
        $this->assertDatabaseCount('email_logs', 0);

        $this->artisan('emails:cleanup-logs')
            ->assertExitCode(0);

        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_cleanup_with_invalid_days_option()
    {
        $this->artisan('emails:cleanup-logs', ['--days' => 'invalid'])
            ->assertExitCode(1);
    }

    public function test_cleanup_with_negative_days_option()
    {
        $this->artisan('emails:cleanup-logs', ['--days' => '-1'])
            ->assertExitCode(1);
    }

    public function test_cleanup_uses_config_default()
    {
        // Set config value
        config(['mail.log_retention_days' => 5]);

        // Create logs from 6 days ago (should be deleted)
        EmailLog::create([
            'sync_type' => 'graph',
            'started_at' => Carbon::now()->subDays(6),
            'completed_at' => Carbon::now()->subDays(6)->addMinutes(5),
            'processed_count' => 10,
            'error_count' => 0,
        ]);

        // Create logs from 3 days ago (should be kept)
        EmailLog::create([
            'sync_type' => 'imap',
            'started_at' => Carbon::now()->subDays(3),
            'completed_at' => Carbon::now()->subDays(3)->addMinutes(2),
            'processed_count' => 5,
            'error_count' => 1,
        ]);

        $this->assertDatabaseCount('email_logs', 2);

        $this->artisan('emails:cleanup-logs')
            ->expectsConfirmation('Are you sure you want to delete 1 email logs?', 'yes')
            ->assertExitCode(0);

        // Should only keep logs from 3 days ago
        $this->assertDatabaseCount('email_logs', 1);
        
        $this->assertDatabaseMissing('email_logs', [
            'sync_type' => 'graph',
            'processed_count' => 10,
        ]);

        $this->assertDatabaseHas('email_logs', [
            'sync_type' => 'imap',
            'processed_count' => 5,
        ]);
    }
}