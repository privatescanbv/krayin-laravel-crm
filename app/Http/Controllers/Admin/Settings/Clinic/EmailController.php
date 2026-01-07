<?php

namespace App\Http\Controllers\Admin\Settings\Clinic;

use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Mail\EmailController as BaseEmailController;

class EmailController extends BaseEmailController
{
    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        // Get clinic_id from route parameter
        $clinicId = request()->route('id');

        // Ensure clinic_id is set from route parameter
        if ($clinicId) {
            request()->merge(['clinic_id' => $clinicId]);
        }

        $response = json_decode(parent::store()->getContent(), true);

        return response()->json([
            'data'    => $this->transformToActivity($response['data']),
            'message' => $response['message'],
        ]);
    }

    /**
     * Detach email from clinic.
     *
     * @return \Illuminate\Http\Response
     */
    public function detach(int $id)
    {
        Event::dispatch('email.update.before', request()->input('email_id'));

        $email = $this->emailRepository->update([
            'clinic_id' => null,
        ], request()->input('email_id'));

        Event::dispatch('email.update.after', $email);

        return response()->json([
            'message' => trans('admin::app.mail.update-success'),
        ]);
    }
}
