<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true])) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }
        $r->session()->regenerate();
        AuditLog::record('auth.login', 'user', auth()->id());

        return $r->user();
    }

    public function logout(Request $r)
    {
        AuditLog::record('auth.logout', 'user', auth()->id());
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->noContent();
    }
}
