<?php

namespace App\Http\Resources\Concerns;

use App\Models\Attachment;

/**
 * The attachment list shared by the resources that use the polymorphic
 * attachment store (expenses, incomes, staff). Emitted only when the relation
 * is loaded so resources stay cheap when attachments are not requested.
 */
trait PresentsAttachments
{
    /** @return array<int, array<string, mixed>>|\Illuminate\Http\Resources\MissingValue */
    protected function attachmentsPayload(string $type, int|string $id)
    {
        return $this->whenLoaded('attachments', fn () => $this->attachments
            ->map(fn (Attachment $attachment) => [
                'id' => (string) $attachment->id,
                'section' => $attachment->section,
                'name' => $attachment->original_name,
                'mimeType' => $attachment->mime_type,
                'size' => (int) $attachment->size,
                'uploadedAt' => $attachment->created_at?->toISOString(),
                'downloadUrl' => "/api/attachments/{$type}/{$id}/{$attachment->id}/download",
            ])
            ->values()
            ->all());
    }
}
