<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Actions\RedeemLicenseCode;
use App\Enums\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The white-label sign-up page where a reseller's buyers redeem their code (OTO 12).
 */
class RedeemCodeController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, ?User $reseller = null): View
    {
        abort_if($reseller && ! $reseller->owns(Product::Oto12Reseller), 404);

        return view('auth.redeem', [
            'reseller' => $reseller,
            'brand' => $reseller?->reseller_brand ?: config('app.name'),
            'code' => (string) $request->query('code', ''),
        ]);
    }

    public function store(Request $request, RedeemLicenseCode $redeemLicenseCode, ?User $reseller = null): RedirectResponse
    {
        abort_if($reseller && ! $reseller->owns(Product::Oto12Reseller), 404);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ]);

        $user = $redeemLicenseCode->handle($validated['code'], $validated['name'], $validated['email'], $validated['password'], $reseller);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
