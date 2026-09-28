<?php

namespace App\Http\Controllers;

use App\Support\Ui\UiColorScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UserAppearanceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.appearance', [
            'schemes' => UiColorScheme::options(),
            'selectedScheme' => $request->user()->ui_color_scheme ?: UiColorScheme::DEFAULT,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ui_color_scheme' => ['required', 'string', Rule::in(array_keys(UiColorScheme::options()))],
        ]);

        $scheme = $validated['ui_color_scheme'];
        $request->user()->forceFill([
            'ui_color_scheme' => $scheme === UiColorScheme::DEFAULT ? null : $scheme,
        ])->save();

        return back()->with('success', 'Appearance settings updated.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['ui_color_scheme' => null])->save();

        return back()->with('success', 'Appearance reset to the original default.');
    }
}
