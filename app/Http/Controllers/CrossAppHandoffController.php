<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrossAppHandoffRequest;
use App\Services\Sso\CrossAppHandoffService;
use Illuminate\Http\RedirectResponse;

class CrossAppHandoffController extends Controller
{
    public function __invoke(CrossAppHandoffRequest $request, CrossAppHandoffService $handoff): RedirectResponse
    {
        $redirectUrl = $handoff->redirectUrl($request, (string) $request->validated('to'), 'go');

        abort_if($redirectUrl === null, 404);

        return redirect()->away($redirectUrl);
    }
}
