<?php
namespace App\Models;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ServiceVariant extends Model {
    use Auditable, SoftDeletes;
    protected $fillable = ['service_id', 'name', 'code', 'fee'];
    protected $casts = ['fee' => 'decimal:2'];
    public function service() { return $this->belongsTo(Service::class); }
    public function contractServices() { return $this->hasMany(ContractService::class, 'service_variant_id'); }
}
