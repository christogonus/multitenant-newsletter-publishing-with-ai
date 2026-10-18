<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BumpOffer;
use App\Models\BumpOfferFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Adds files to an order bump. A plain form post rather than a Livewire upload, so large PDFs are not held
 * to Livewire's temporary upload limit, which applies to every buyer-facing upload too.
 */
class BumpFileUploadController extends Controller
{
    public const MAX_FILE_KILOBYTES = 102400;

    public function __invoke(Request $request, BumpOffer $bumpOffer): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $request->validate([
            'files' => ['required', 'array', 'max:50'],
            'files.*' => ['file', 'mimes:pdf,png,jpg,jpeg,webp,gif,zip', 'max:'.self::MAX_FILE_KILOBYTES],
        ], [
            'files.required' => 'Choose at least one file.',
            'files.*.mimes' => 'Files must be PDFs, images (PNG, JPG, WebP, GIF) or zip files.',
            'files.*.max' => 'Each file must be smaller than 100 MB.',
        ]);

        $sort = (int) $bumpOffer->files()->max('sort');

        foreach ($request->file('files') as $upload) {
            $bumpOffer->files()->create([
                'name' => mb_substr($upload->getClientOriginalName(), 0, 200),
                'path' => $upload->store('bumps/'.$bumpOffer->id, BumpOfferFile::DISK),
                'mime_type' => (string) $upload->getMimeType(),
                'size' => (int) $upload->getSize(),
                'sort' => ++$sort,
            ]);
        }

        return redirect()->route('admin.bumps', ['editing' => $bumpOffer->id])
            ->with('status', count($request->file('files')).' file(s) added to '.$bumpOffer->name.'.');
    }
}
