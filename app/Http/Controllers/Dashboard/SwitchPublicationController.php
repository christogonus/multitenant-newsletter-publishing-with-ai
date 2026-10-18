<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchPublicationController extends Controller
{
    public function __invoke(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $this->authorize('update', $newsletter);

        $request->user()->forceFill(['current_newsletter_id' => $newsletter->id])->save();

        return redirect()->route('dashboard')
            ->with('toast', ['message' => 'Now working on '.$newsletter->name.'.', 'tone' => 'success']);
    }
}
