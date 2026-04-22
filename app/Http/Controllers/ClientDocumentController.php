<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientDocumentController extends Controller
{
    public function download(Request $request, Client $client, ClientDocument $document): BinaryFileResponse
    {
        $user = $request->user();
        $canViewCustomerDocuments = $user
            && ! $user->isReadOnlyDemo()
            && $user->hasAnyRole(['owner_admin', 'admin', 'manager', 'case_manager']);

        abort_unless($canViewCustomerDocuments, 403, 'This account cannot open customer documents.');
        abort_unless($document->client_id === $client->getKey(), 404);
        abort_unless(filled($document->file_path) && File::exists($document->file_path), 404);

        $mimeType = $document->mime_type ?: File::mimeType($document->file_path);
        $fileName = $document->file_name ?: basename((string) $document->file_path);
        $forcePreview = $request->boolean('preview');
        $disposition = $forcePreview || Str::startsWith((string) $mimeType, 'image/') || $mimeType === 'application/pdf'
            ? 'inline'
            : 'attachment';

        return response()->file(
            $document->file_path,
            array_filter([
                'Content-Type' => $mimeType,
                'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, addcslashes($fileName, '"\\')),
            ]),
        );
    }
}
