<?php

namespace Webkul\Installer\Database\Seeders\Workflow;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Models\AttributeOption;
use Webkul\Lead\Models\Source;
use Webkul\User\Models\Group;

class WorkflowSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('workflows')->delete();

        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        $sourcePCId = Source::where('name', 'like', '%2552680%')->value('id');
        $sourceHerniaId = Source::where('name', 'like', '%8200100%')->value('id');
        $attributeOptionHerniaId = AttributeOption::where('name', 'Hernia')->value('id');
        $attributeOptionPCId = AttributeOption::where('name', 'Privatescan')->value('id');
//        $id = 1;
//        DB::table('workflows')->insert([
//            [
//                'id'             => $id,
//                'name'           => trans('installer::app.seeders.workflow.email-to-participants-after-activity-creation', [], $defaultLocale),
//                'description'    => trans('installer::app.seeders.workflow.email-to-participants-after-activity-creation', [], $defaultLocale),
//                'entity_type'    => 'activities',
//                'event'          => 'activity.create.after',
//                'condition_type' => 'and',
//                'conditions'     => '[{"value": ["call", "meeting", "task"], "operator": "{}", "attribute": "type", "attribute_type": "multiselect"}]',
//                'actions'        => '[{"id": "send_email_to_participants", "value": "1"}]',
//                'created_at'     => $now,
//                'updated_at'     => $now,
//            ], [
//                'id'             => ++$id,
//                'name'           => trans('installer::app.seeders.workflow.email-to-participants-after-activity-updation', [], $defaultLocale),
//                'description'    => trans('installer::app.seeders.workflow.email-to-participants-after-activity-updation', [], $defaultLocale),
//                'entity_type'    => 'activities',
//                'event'          => 'activity.update.after',
//                'condition_type' => 'and',
//                'conditions'     => '[{"value": ["call", "meeting", "task"], "operator": "{}", "attribute": "type", "attribute_type": "multiselect"}]',
//                'actions'        => '[{"id": "send_email_to_participants", "value": "2"}]',
//                'created_at'     => $now,
//                'updated_at'     => $now,
//            ],
////            [
////                'id'             => ++$id,
////                'name'           => 'Set afdeling lead Hernia',
////                'description'    => 'Op basis van kanaal / source',
////                'entity_type'    => 'leads',
////                'event'          => 'lead.create.after',
////                'condition_type' => 'and',
////                'conditions'     => '[{"value": "'.$sourceHerniaId.'", "operator": "==", "attribute": "source", "attribute_type": "select"}]',
////                'actions'        => '[{"id": "update_lead", "value": "'.$attributeOptionHerniaId.'", "attribute": "department", "attribute_type": "select"}]',
////                'created_at'     => $now,
////                'updated_at'     => $now,
////            ],
////            [
////                'id'             => ++$id,
////                'name'           => 'Set afdeling lead Privatescan',
////                'description'    => 'Op basis van kanaal / source',
////                'entity_type'    => 'leads',
////                'event'          => 'lead.create.after',
////                'condition_type' => 'and',
////                'conditions'     => '[{"value": "'.$sourcePCId.'", "operator": "==", "attribute": "lead_source_id", "attribute_type": "select"}]',
////                'actions'        => '[{"id": "update_lead", "value": "'.$attributeOptionPCId.'", "attribute": "department", "attribute_type": "select"}]',
////                'created_at'     => $now,
////                'updated_at'     => $now,
////            ]
//        ]);
    }
}
