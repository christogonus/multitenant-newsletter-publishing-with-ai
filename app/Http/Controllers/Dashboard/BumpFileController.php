<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BumpOffer;
use App\Models\BumpOfferFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Serves order bump files to the buyers who own the bump (and to admins), from the private disk.
 */
class BumpFileController extends Controller
{
    /**
     * One file. Images can be shown inline (?inline=1) so the downloads page can preview them.
     */
    public function show(Request $request, BumpOfferFile $file): StreamedResponse
    {
        $this->authorizeOwner($request, $file->bumpOffer);
        $disk = Storage::disk(BumpOfferFile::DISK);
        abort_unless($disk->exists($file->path), 404);

        if ($request->boolean('inline') && $file->isImage()) {
            return $disk->response($file->path, $file->name, ['Content-Type' => $file->mime_type, 'Cache-Control' => 'private, max-age=3600']);
        }

        return $disk->download($file->path, $file->name, ['Content-Type' => $file->mime_type]);
    }

    /**
     * Every file of the bump in one zip, for bumps made of many images.
     */
    public function zip(Request $request, BumpOffer $bumpOffer): BinaryFileResponse
    {
        $this->authorizeOwner($request, $bumpOffer);
        $files = $bumpOffer->files;
        abort_if($files->isEmpty(), 404);

        $disk = Storage::disk(BumpOfferFile::DISK);
        $zipPath = tempnam(sys_get_temp_dir(), 'bump');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $used = [];

        foreach ($files as $file) {
            if (! $disk->exists($file->path)) {
                continue;
            }

            // Two files with the same name would overwrite each other inside the zip.
            $name = $file->name;
            for ($copy = 2; isset($used[Str::lower($name)]); $copy++) {
                $name = pathinfo($file->name, PATHINFO_FILENAME)." ({$copy}).".pathinfo($file->name, PATHINFO_EXTENSION);
            }
            $used[Str::lower($name)] = true;

            $zip->addFile($disk->path($file->path), $name);
        }

        $zip->close();

        return response()->download($zipPath, Str::slug($bumpOffer->name).'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    private function authorizeOwner(Request $request, BumpOffer $bumpOffer): void
    {
        $user = $request->user();

        abort_unless($user->is_admin || $bumpOffer->isOwnedBy($user), 403);
    }
}
