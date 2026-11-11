<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class AcademyCertificate extends Model
{
    use Auditable, HasDocumentNumber, SoftDeletes;

    public const STATUSES = ['issued', 'revoked'];

    protected $fillable = [
        'certificate_number', 'academy_enrollment_id', 'applicant_id', 'class_id',
        'contract_id', 'applicant_contract_id', 'academy_certificate_template_id',
        'issued_at', 'issued_by', 'snapshot', 'status', 'revoked_at',
        'revoked_by', 'revocation_reason',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
        'snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $certificate): void {
            if ($certificate->isDirty('snapshot') && ! empty($certificate->getOriginal('snapshot'))) {
                throw new LogicException('Certificate snapshots are immutable.');
            }
        });
    }

    protected function documentNumberColumn(): string
    {
        return 'certificate_number';
    }

    protected function documentNumberPrefix(): string
    {
        return 'CERT';
    }

    protected function documentNumberFormat(): string
    {
        return '%s-%s-%03d';
    }

    protected function documentNumberYearFormat(): string
    {
        return 'y';
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(AcademyEnrollment::class, 'academy_enrollment_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function academyClass(): BelongsTo
    {
        return $this->belongsTo(AcademyClass::class, 'class_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AcademyCertificateTemplate::class, 'academy_certificate_template_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
