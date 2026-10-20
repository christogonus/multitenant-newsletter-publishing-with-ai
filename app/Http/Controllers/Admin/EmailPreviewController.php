<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\UpgradeUnlocked;
use App\Notifications\WelcomeBuyer;
use Illuminate\Http\Response;

/**
 * Shows buyer emails in the browser exactly as they would be sent, for checking the wording. Nothing is sent.
 * The buttons in a preview are live links, so the welcome email's set-password link works for that account.
 */
class EmailPreviewController extends Controller
{
    public function welcome(User $user): Response
    {
        return $this->preview((new WelcomeBuyer)->toMail($user)->render());
    }

    public function upgrade(Purchase $purchase): Response
    {
        $purchase = Purchase::query()->with('user')->standing()->findOrFail($purchase->id);

        return $this->preview((new UpgradeUnlocked($purchase->transaction_id))->toMail($purchase->user)->render());
    }

    private function preview(string $html): Response
    {
        return response($html)->header('Cache-Control', 'no-store');
    }
}
