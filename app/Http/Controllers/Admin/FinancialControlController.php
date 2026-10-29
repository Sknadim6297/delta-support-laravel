<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialControl;
use App\Services\FinancialControls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialControlController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.financial', [
            'controls' => FinancialControl::current(),
            'flags' => FinancialControls::FLAGS,
        ]);
    }

    public function update(Request $request, FinancialControls $controls): RedirectResponse
    {
        $flags = [];
        foreach (FinancialControls::FLAGS as $flag) {
            $flags[$flag] = $request->boolean($flag);
        }
        $controls->update($flags);

        return redirect()
            ->route('admin.settings.financial')
            ->with('status', 'Financial controls saved. Historical records were not changed.');
    }
}
