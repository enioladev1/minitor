<?php

namespace App\Http\Responses\Auth;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Renders the exact same response whether or not the requested email
 * address belongs to a user, so the "forgot password" endpoint can't be
 * used to enumerate registered accounts.
 */
class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, Responsable, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        $message = trans('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
