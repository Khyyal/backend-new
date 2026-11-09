<?php

namespace Modules\Support\Http\Controllers\Docs;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Support\Http\Requests\Docs\DocsLoginRequest;
use Modules\Support\Services\SystemUserAuthService;

/**
 * Session login for the API documentation, restricted to active system users.
 */
class DocsLoginController extends Controller
{
    public function __construct(private readonly SystemUserAuthService $authService) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::guard('system_user_web')->check()) {
            return redirect()->route('scribe');
        }

        return view('support::docs.login');
    }

    public function login(DocsLoginRequest $request): RedirectResponse
    {
        $user = $this->authService->attempt($request->validated('email'), $request->validated('password'));

        Auth::guard('system_user_web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('scribe'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('system_user_web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('docs.login');
    }
}
