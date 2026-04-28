<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientDocumentController extends Controller
{
    public function download(Request $request, Client $client, ClientDocument $document): BinaryFileResponse
    {
        abort_unless($document->client_id === $client->getKey(), 404);
        abort_unless(filled($document->file_path) && File::exists($document->file_path), 404);

        if ($request->boolean('preview')) {
            $fileName = str_replace(['"', '\\', "\r", "\n"], '', $document->file_name ?: basename($document->file_path));

            return response()->file(
                $document->file_path,
                array_filter([
                    'Content-Type' => $document->mime_type,
                    'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                    'X-Content-Type-Options' => 'nosniff',
                ]),
            );
        }

        return response()->download(
            $document->file_path,
            $document->file_name,
            array_filter([
                'Content-Type' => $document->mime_type,
            ]),
        );
    }
}
