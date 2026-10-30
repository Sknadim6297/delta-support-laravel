<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\RootAdminBootstrap;
use App\Services\SuperAdminProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class RootAdminBootstrapController extends Controller
{
    public function create(Request $request): View
    {
        $this->refuseWhenAdminExists();

        return view('admin.bootstrap.create', [
            'gate' => (string) $request->route('gate'),
            'confirmation' => RootAdminBootstrap::BACKUP_CONFIRMATION,
        ]);
    }

    public function store(Request $request, RootAdminBootstrap $bootstrap, SuperAdminProvisioner $provisioner): RedirectResponse
    {
        $this->refuseWhenAdminExists();

        $validated = $request->validate([
            'bootstrap_token' => ['required', 'string', 'max:43'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200', 'confirmed', new StrongPassword],
            'backup_confirmation' => ['required', 'string', 'max:40'],
        ], [
            'bootstrap_token.required' => 'The bootstrap token was not accepted.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if (! hash_equals(RootAdminBootstrap::BACKUP_CONFIRMATION, (string) $validated['backup_confirmation'])) {
            return back()
                ->withErrors(['backup_confirmation' => 'Confirm the verified database backup before creating the admin.'])
                ->onlyInput(['email', 'backup_confirmation']);
        }

        if (! $bootstrap->tokenMatches((string) $validated['bootstrap_token'])) {
            return back()
                ->withErrors(['bootstrap_token' => 'The bootstrap token was not accepted.'])
                ->onlyInput(['email', 'backup_confirmation']);
        }

        try {
            $provisioner->create((string) $validated['email'], (string) $validated['password']);
        } catch (RuntimeException $exception) {
            $this->refuseWhenAdminExists();

            return back()
                ->withErrors(['email' => $exception->getMessage()])
                ->onlyInput(['email', 'backup_confirmation']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['email' => 'The Root Admin could not be created. No password was written to the log.'])
                ->onlyInput(['email', 'backup_confirmation']);
        }

        $bootstrap->consume();

        return redirect()
            ->route('login')
            ->with('success', 'Root Admin ST666666 was created. Sign in with that Member ID and change the password.');
    }

    private function refuseWhenAdminExists(): void
    {
        if (User::query()->where('is_admin', true)->exists()) {
            abort(404);
        }
    }
}
