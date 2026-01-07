<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Mail\EmailController as BaseEmailController;
use Webkul\Admin\Http\Resources\ActivityResource;

class SalesLeadEmailController extends BaseEmailController
{
    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $response = json_decode(parent::store()->getContent(), true);

        return response()->json([
            'data'    => $this->transformToActivity($response['data']),
            'message' => $response['message'],
        ]);

        return $response;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function detach($id)
    {
        Event::dispatch('email.update.before', request()->input('email_id'));

        $email = $this->emailRepository->update([
            'sales_lead_id' => null,
        ], request()->input('email_id'));

        Event::dispatch('email.update.after', $email);

        return response()->json([
            'message' => trans('admin::app.mail.update-success'),
        ]);
    }

}
