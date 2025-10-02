<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix emails in persons table
        $this->fixContactArrays('persons', 'emails');

        // Fix phones in persons table (if column exists)
        if (Schema::hasColumn('persons', 'phones')) {
            $this->fixContactArrays('persons', 'phones');
        }

        // Fix contact_numbers in persons table
        if (Schema::hasColumn('persons', 'contact_numbers')) {
            $this->fixContactArrays('persons', 'contact_numbers');
        }

        // Fix emails in leads table
        if (Schema::hasColumn('leads', 'emails')) {
            $this->fixContactArrays('leads', 'emails');
        }

        // Fix phones in leads table
        if (Schema::hasColumn('leads', 'phones')) {
            $this->fixContactArrays('leads', 'phones');
        }
    }

    public function down(): void
    {
        // This migration only fixes data, no schema changes to reverse
    }

    /**
     * Fix contact arrays by ensuring they have proper label and is_default fields
     */
    private function fixContactArrays(string $table, string $column): void
    {
        // Check if table and column exist
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $records = DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '[]')
            ->where($column, '!=', '')
            ->get(['id', $column]);

        if ($records->isEmpty()) {
            return;
        }

        foreach ($records as $record) {
            $contactArray = json_decode($record->$column, true);

            if (! is_array($contactArray) || empty($contactArray)) {
                continue;
            }

            $fixed = false;
            foreach ($contactArray as &$contact) {
                if (! is_array($contact)) {
                    continue;
                }

                // Add missing label
                if (! isset($contact['label']) || empty($contact['label'])) {
                    $contact['label'] = 'work';
                    $fixed = true;
                }

                // Normalize label to lowercase
                if (isset($contact['label'])) {
                    $normalizedLabel = $this->normalizeLabel($contact['label']);
                    if ($contact['label'] !== $normalizedLabel) {
                        $contact['label'] = $normalizedLabel;
                        $fixed = true;
                    }
                }

                // Add missing is_default
                if (! isset($contact['is_default'])) {
                    $contact['is_default'] = false;
                    $fixed = true;
                }
            }

            // Ensure at least one item is marked as default
            if (count($contactArray) > 0) {
                $hasDefault = false;
                foreach ($contactArray as $contact) {
                    if (isset($contact['is_default']) && $contact['is_default'] === true) {
                        $hasDefault = true;
                        break;
                    }
                }

                if (! $hasDefault) {
                    $contactArray[0]['is_default'] = true;
                    $fixed = true;
                }
            }

            // Update the record if changes were made
            if ($fixed) {
                DB::table($table)
                    ->where('id', $record->id)
                    ->update([$column => json_encode($contactArray)]);
            }
        }
    }

    /**
     * Normalize label to lowercase and handle common variations
     */
    private function normalizeLabel(string $label): string
    {
        if (empty($label)) {
            return 'work';
        }

        // Convert to lowercase and map common variations
        $normalizedLabel = strtolower(trim($label));
        $labelMap = [
            'work'   => 'work',
            'werk'   => 'work',
            'home'   => 'home',
            'thuis'  => 'home',
            'mobile' => 'mobile',
            'mobiel' => 'mobile',
            'other'  => 'other',
            'anders' => 'other',
        ];

        return $labelMap[$normalizedLabel] ?? 'work';
    }
};
