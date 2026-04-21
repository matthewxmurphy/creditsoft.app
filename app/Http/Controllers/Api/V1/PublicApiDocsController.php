<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiDocsHostService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicApiDocsController extends Controller
{
    public function __invoke(Request $request, ApiDocsHostService $docsHost): View
    {
        abort_unless($docsHost->shouldServeDocsAtRoot($request), 404);

        return view('api-docs-public', $docsHost->docsMeta($request));
    }
}
