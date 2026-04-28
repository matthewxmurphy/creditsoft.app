<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class OpenApiDocumentController extends Controller
{
    public function __invoke(): Response
    {
        $path = (string) config('creditsoft.api.docs_path');

        abort_unless($path !== '' && File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }
}
