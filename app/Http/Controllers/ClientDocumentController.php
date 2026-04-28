<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientDocumentController extends Controller
{
    public function download(Client $client, ClientDocument $document): BinaryFileResponse
    {
        abort_unless($document->client_id === $client->getKey(), 404);
        abort_unless(filled($document->file_path) && File::exists($document->file_path), 404);

        return response()->download(
            $document->file_path,
            $document->file_name,
            array_filter([
                'Content-Type' => $document->mime_type,
            ]),
        );
    }
}
