<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Migrate existing person_id data to lead_persons pivot table
        $leads = DB::table('leads')->whereNotNull('person_id')->get();

        foreach ($leads as $lead) {
            DB::table('lead_persons')->insert([
                'lead_id'    => $lead->id,
                'person_id'  => $lead->person_id,
                'created_at' => $lead->created_at,
                'updated_at' => $lead->updated_at,
            ]);
        }
    }

    public function down()
    {
        // Restore person_id data back to leads table
        $leadPersons = DB::table('lead_persons')->get();

        foreach ($leadPersons as $leadPerson) {
            DB::table('leads')
                ->where('id', $leadPerson->lead_id)
                ->update(['person_id' => $leadPerson->person_id]);
        }
    }
};
