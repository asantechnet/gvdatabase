<?php
namespace App\Models; use App\Models\Concerns\Auditable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class ContractTemplateVersion extends Model {
    use Auditable, SoftDeletes;

    protected $fillable = ['contract_template_id', 'country_id', 'language_id', 'version_number', 'name', 'content', 'status', 'is_current', 'current_scope_key', 'created_by', 'activated_by', 'activated_at', 'archived_by', 'archived_at'];
    protected $casts = ['is_current' => 'boolean', 'activated_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime'];

    public function template(){ return $this->belongsTo(ContractTemplate::class, 'contract_template_id'); }
    public function country(){ return $this->belongsTo(Country::class); }
    public function language(){ return $this->belongsTo(Language::class); }
    public function creator(){ return $this->belongsTo(User::class, 'created_by'); }
    public function activator(){ return $this->belongsTo(User::class, 'activated_by'); }
    public function archiver(){ return $this->belongsTo(User::class, 'archived_by'); }
    public function contracts(){ return $this->hasMany(Contract::class, 'contract_template_version_id'); }

    public function scopeKey(): string
    {
        return implode(':', [$this->contract_template_id, $this->country_id, $this->language_id]);
    }
}
