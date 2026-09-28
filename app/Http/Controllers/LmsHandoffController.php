<?php

namespace App\Http\Controllers;

use App\Services\Sso\CrossAppHandoffService;
use App\Support\CustomerSync\LmsHub;
use App\Support\Sso\SsoHandoffLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LmsHandoffController extends Controller
{
    public function __invoke(Request $request, CrossAppHandoffService $handoff): RedirectResponse
    {
        $hubUrl = LmsHub::configuredUrl();

        if ($hubUrl === null) {
            SsoHandoffLog::refused('lms', 'hub_not_configured', $request);

            return redirect()->away('/login?error=unavailable');
        }

        return redirect()->away($handoff->redirectUrl($request, $hubUrl, 'lms') ?? $hubUrl);
    }
}
