<?php
namespace App\Services;
use App\Models\Applicant; use Illuminate\Validation\ValidationException;
class ApplicantDocumentReadiness { public function ready(Applicant $a): bool { return $a->attachments()->exists(); } public function require(Applicant $a,string $field='applicant_id'): void { if(!$this->ready($a)) throw ValidationException::withMessages([$field=>['business.applicant_document_required']]); } }
