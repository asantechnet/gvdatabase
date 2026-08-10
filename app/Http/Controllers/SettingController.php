<?php

namespace App\Http\Controllers;

use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => SettingResource::collection(Setting::latest('id')->get()),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'navigation_clr' => 'required|string|max:20',
            'cards_clr' => 'required|string|max:20',
            'footer_clr' => 'required|string|max:20',
            'website_title' => 'required|string|max:255',
            'website_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
            'website_address' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'physical_address' => 'required|string|max:1000',
            'short_company_name' => 'required|string|max:255',
            'original_company_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('website_logo')) {
            $data['website_logo'] = $request->file('website_logo')->store('logos', 'public');
        }

        $setting = Setting::create($data);

        return response()->json([
            'success' => true,
            'data' => new SettingResource($setting),
        ], 201);
    }

    public function show(string $id)
    {
        $setting = Setting::find($id);

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new SettingResource($setting),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $setting = Setting::find($id);

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'navigation_clr' => 'sometimes|string|max:20',
            'cards_clr' => 'sometimes|string|max:20',
            'footer_clr' => 'sometimes|string|max:20',
            'website_title' => 'sometimes|string|max:255',
            'website_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
            'website_address' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'physical_address' => 'sometimes|string|max:1000',
            'short_company_name' => 'sometimes|string|max:255',
            'original_company_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('website_logo')) {
            if ($setting->website_logo) {
                Storage::disk('public')->delete($setting->website_logo);
            }

            $data['website_logo'] = $request->file('website_logo')->store('logos', 'public');
        }

        $setting->update($data);

        return response()->json([
            'success' => true,
            'data' => new SettingResource($setting->fresh()),
        ]);
    }

    public function destroy(string $id)
    {
        $setting = Setting::find($id);

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        if ($setting->website_logo) {
            Storage::disk('public')->delete($setting->website_logo);
        }

        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Setting deleted successfully',
        ]);
    }
}
