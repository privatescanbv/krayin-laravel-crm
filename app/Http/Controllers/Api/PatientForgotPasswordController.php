<?php

namespace App\Http\Controllers\Api;

use App\Actions\Patient\SendForgotPasswordMailAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientForgotPasswordController extends Controller
{
    public function __construct(
        private readonly SendForgotPasswordMailAction $sendForgotPasswordMail,
    ) {}

    /**
     * Trigger a patient password-reset e-mail.
     *
     * Always returns 200 with the same message regardless of whether the address
     * is known, to prevent email enumeration.
     *
     * Protected by X-API-KEY (service-to-service).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->sendForgotPasswordMail->execute($validated['email']);

        return response()->json([
            'message' => 'Als dit e-mailadres bij ons bekend is, hebt u een e-mail ontvangen met instructies om uw wachtwoord te resetten.',
        ]);
    }
}
