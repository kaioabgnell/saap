<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\ClinicLogoUploader;
use App\Support\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ImageUploader $images,
        private readonly ClinicLogoUploader $clinicLogos,
    ) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except(['photo', 'clinic_logo', 'remove_clinic_logo']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('photo')) {
            // Foto de perfil do psicólogo: disco público, sem sensibilidade
            // equivalente à foto de aprendiz — ver ImageUploader e F2.
            $path = $this->images->store($request->file('photo'), 'public', "perfis/{$user->id}", $user->photo_path);
            $user->photo_path = $path;
        }

        if ($request->hasFile('clinic_logo')) {
            $user->clinic_logo_path = $this->clinicLogos->store($request->file('clinic_logo'), $user->clinic_logo_path);
        } elseif ($request->boolean('remove_clinic_logo') && $user->clinic_logo_path !== null) {
            // Remoção explícita: sem logo própria, o relatório volta a usar a
            // do sistema — ver BuildReportPayload::logoEmBase64().
            $this->clinicLogos->delete($user->clinic_logo_path);
            $user->clinic_logo_path = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
