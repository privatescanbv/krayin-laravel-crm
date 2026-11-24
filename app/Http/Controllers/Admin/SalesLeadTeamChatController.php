<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Activities\CreateActivityForSalesLeadAction;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesLeadTeamChatStoreRequest;
use App\Models\SalesLead;
use App\Services\PlainText;
use Illuminate\Http\RedirectResponse;

/**
 * CRM side of the team chat (doctor <-> staff) on a sale. Separate from the patient chat.
 */
class SalesLeadTeamChatController extends Controller
{
    public function store(SalesLeadTeamChatStoreRequest $request, CreateActivityForSalesLeadAction $create, int $id): RedirectResponse
    {
        $sale = SalesLead::findOrFail($id);

        $create->execute($sale, true, [
            'type'       => ActivityType::TEAM_CHAT->value,
            'comment'    => PlainText::toHtml($request->validated('text')),
            'user_id'    => auth()->id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect(route('admin.sales-leads.view', $id).'#teamchat');
    }
}
