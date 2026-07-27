<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\Location;
use App\Models\NtbCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function institutions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.institutions'), 403);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'institution_name' => ['required', 'string', 'max:255'],
                'institution_type' => ['nullable', 'string', 'max:100'],
                'country_code' => ['nullable', 'string', 'max:10'],
                'contact_email' => ['nullable', 'email'],
                'contact_phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string'],
                'status' => ['nullable', 'string', 'max:20'],
            ]);

            $institution = Institution::query()->create($data);

            return response()->json(['institution' => $institution], 201);
        }

        return response()->json(Institution::query()->orderBy('institution_name')->paginate(50));
    }

    public function updateInstitution(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.institutions'), 403);
        $institution = Institution::query()->findOrFail($id);
        $data = $request->validate([
            'institution_name' => ['sometimes', 'string', 'max:255'],
            'institution_type' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
        $institution->update($data);

        return response()->json(['institution' => $institution]);
    }

    public function categories(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'code' => ['required', 'string', 'max:20'],
                'name_en' => ['required', 'string', 'max:255'],
                'name_fr' => ['required', 'string', 'max:255'],
                'default_sla_days' => ['required', 'integer', 'min:1'],
                'responsible_institution_id' => ['nullable', 'exists:institutions,id'],
                'is_active' => ['boolean'],
            ]);

            $category = NtbCategory::query()->create($data);

            return response()->json(['category' => $category], 201);
        }

        return response()->json(NtbCategory::query()->with('responsibleInstitution')->orderBy('code')->paginate(50));
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.categories'), 403);
        $category = NtbCategory::query()->findOrFail($id);
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20'],
            'name_en' => ['sometimes', 'string', 'max:255'],
            'name_fr' => ['sometimes', 'string', 'max:255'],
            'default_sla_days' => ['sometimes', 'integer', 'min:1'],
            'responsible_institution_id' => ['nullable', 'exists:institutions,id'],
            'is_active' => ['boolean'],
        ]);
        $category->update($data);

        return response()->json(['category' => $category]);
    }

    public function corridors(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.corridors'), 403);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'corridor_code' => ['required', 'string', 'max:50'],
                'corridor_name' => ['required', 'string', 'max:255'],
                'name_en' => ['nullable', 'string', 'max:255'],
                'name_fr' => ['nullable', 'string', 'max:255'],
                'origin_country' => ['required', 'string', 'max:100'],
                'destination_country' => ['required', 'string', 'max:100'],
                'active' => ['boolean'],
            ]);

            $corridor = Corridor::query()->create($data);

            return response()->json(['corridor' => $corridor], 201);
        }

        return response()->json(Corridor::query()->orderBy('corridor_code')->paginate(50));
    }

    public function locations(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.locations'), 403);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'location_name' => ['required', 'string', 'max:255'],
                'name_en' => ['nullable', 'string', 'max:255'],
                'name_fr' => ['nullable', 'string', 'max:255'],
                'country' => ['required', 'string', 'max:100'],
                'region' => ['nullable', 'string', 'max:100'],
                'latitude' => ['nullable', 'numeric'],
                'longitude' => ['nullable', 'numeric'],
                'border_post' => ['boolean'],
                'corridor_id' => ['nullable', 'exists:corridors,id'],
            ]);

            $location = Location::query()->create($data);

            return response()->json(['location' => $location], 201);
        }

        return response()->json(Location::query()->with('corridor')->orderBy('location_name')->paginate(50));
    }
}
