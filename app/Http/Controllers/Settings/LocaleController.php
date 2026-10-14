<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Met à jour la préférence de langue de l'utilisateur connecté.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:fr,en'],
        ]);

        $request->user()->update(['locale' => $validated['locale']]);

        return redirect()->back();
    }
}
