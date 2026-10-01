<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class DeveloperDocumentationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $manifestPath = resource_path('developer-docs/manifest.json');
        abort_unless(File::exists($manifestPath), 404, 'Developer documentation manifest missing.');

        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        return response()->json([
            'base_urls' => [
                'web_api' => url('/api/v1/public'),
                'mobile_api' => url('/api/v1/mobile'),
                'ussd_api' => url('/api/v1/channels/ussd'),
                'sms_api' => url('/api/v1/public/sms'),
                'partner_api' => url('/api/v1/partner'),
            ],
            'documents' => $manifest['documents'] ?? [],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $path = resource_path('developer-docs/'.$slug.'.md');
        abort_unless(File::exists($path), 404, 'Documentation page not found.');

        return response()->json([
            'slug' => $slug,
            'title' => $this->titleFromSlug($slug),
            'format' => 'markdown',
            'content' => File::get($path),
            'updated_at' => date('c', File::lastModified($path)),
        ]);
    }

    private function titleFromSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
