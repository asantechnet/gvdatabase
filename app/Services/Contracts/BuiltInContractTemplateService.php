<?php

namespace App\Services\Contracts;

use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Support\BuiltInService;
use Illuminate\Support\Facades\DB;

class BuiltInContractTemplateService
{
    public function ensure(string $service, ?int $createdBy = null): ContractTemplate
    {
        abort_unless(BuiltInService::isValid($service), 404);

        return DB::transaction(function () use ($service, $createdBy) {
            $type = ContractType::where('slug', 'customer')->firstOrFail();
            // The unique service key also reserves archived templates, so
            // first use restores the editable document rather than duplicating it.
            $template = ContractTemplate::withTrashed()->firstOrCreate(['service' => $service], [
                'name' => BuiltInService::label($service),
                'contract_type_id' => $type->id,
                'content' => '',
                'cover_text' => '',
                'is_active' => false,
                'created_by' => $createdBy,
            ]);
            if ($template->trashed()) {
                $template->restore();
            }

            return $template;
        });
    }
}
