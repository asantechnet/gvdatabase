<?php
namespace App\Models;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class ApplicantAttachment extends Model { use Auditable, SoftDeletes; protected $fillable=['applicant_id','uploaded_by','label','category','disk','path','original_name','mime_type','size']; protected $casts=['size'=>'integer']; public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); } public function uploader(): BelongsTo { return $this->belongsTo(User::class,'uploaded_by'); } }
