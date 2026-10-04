<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Support\ThemePalette;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingController extends Controller
{
    private const FILES = [
        'website_logo' => 'logos',
        'company_signature' => 'signatures',
        'contract_letterhead' => 'contract-letterheads',
        'contract_cover_design' => 'contract-cover-designs',
        'receipt_letterhead' => 'receipt-letterheads',
        'invoice_letterhead' => 'invoice-letterheads',
        'payslip_letterhead' => 'payslip-letterheads',
    ];

    private const REMOVABLE_FILES = [
        'contract_letterhead',
        'contract_cover_design',
        'receipt_letterhead',
        'invoice_letterhead',
        'payslip_letterhead',
    ];

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SettingResource::collection(Setting::latest('id')->get()),
        ]);
    }

    public function profile(): JsonResponse
    {
        $setting = Setting::latest('id')->first();

        return response()->json([
            'success' => true,
            'data' => $setting ? new SettingResource($setting) : null,
        ]);
    }

    public function store(UpsertSettingRequest $request): JsonResponse
    {
        abort_if(Setting::query()->exists(), 409, 'business.company_profile_already_exists');
        $data = Arr::except($request->validated(), [
            'remove_contract_letterhead',
            'remove_contract_cover_design',
            'remove_receipt_letterhead',
            'remove_invoice_letterhead',
            'remove_payslip_letterhead',
            'clear_mail_password',
        ]);
        $data = $this->withThemeDefaults($data);
        $storedPaths = [];

        try {
            foreach (self::FILES as $field => $directory) {
                if ($request->hasFile($field)) {
                    $data[$field] = $request->file($field)->store($directory, 'public');
                    $storedPaths[] = $data[$field];
                }
            }

            $setting = Setting::create($data);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => new SettingResource($setting),
        ], 201);
    }

    public function show(Setting $setting): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new SettingResource($setting),
        ]);
    }

    public function update(UpsertSettingRequest $request, Setting $setting): JsonResponse
    {
        $data = Arr::except($request->validated(), [
            'remove_contract_letterhead',
            'remove_contract_cover_design',
            'remove_receipt_letterhead',
            'remove_invoice_letterhead',
            'remove_payslip_letterhead',
            'clear_mail_password',
        ]);
        $data = $this->withThemeDefaults($data);
        $storedPaths = [];
        $supersededPaths = [];

        if ($request->boolean('clear_mail_password')) {
            $data['mail_password'] = null;
        } elseif (blank($data['mail_password'] ?? null)) {
            unset($data['mail_password']);
        }

        try {
            foreach (self::FILES as $field => $directory) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store($directory, 'public');
                    $storedPaths[] = $path;
                    if ($setting->{$field}) {
                        $supersededPaths[] = $setting->{$field};
                    }
                    $data[$field] = $path;
                }
            }

            foreach (self::REMOVABLE_FILES as $field) {
                $removeField = 'remove_'.$field;
                if ($request->boolean($removeField) && ! $request->hasFile($field)) {
                    if ($setting->{$field}) {
                        $supersededPaths[] = $setting->{$field};
                    }
                    $data[$field] = null;
                }
            }

            DB::transaction(fn () => $setting->update($data));
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        Storage::disk('public')->delete(array_values(array_unique($supersededPaths)));

        return response()->json([
            'success' => true,
            'data' => new SettingResource($setting->fresh()),
        ]);
    }

    public function destroy(Setting $setting): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'business.company_profile_cannot_be_deleted',
        ], 405);
    }

    /**
     * A null palette field is an intentional "restore Vuexy default" command,
     * not an unusable empty theme token.
     */
    private function withThemeDefaults(array $data): array
    {
        foreach (ThemePalette::fields() as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                $data[$field] = ThemePalette::DEFAULTS[$field];
            }
        }

        return $data;
    }
}
