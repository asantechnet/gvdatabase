<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class WorthyEmployeeResource extends JsonResource {
 public function toArray(Request $request): array { return ['id'=>(string)$this->id,'employeeId'=>(string)$this->employee_id,'employeeName'=>$this->employee->name,'employeeEmail'=>$this->employee->user?->email,'department'=>$this->employee->department,'performanceScore'=>(float)$this->performance_score,'eligibilityReason'=>$this->eligibility_reason,'achievementDetails'=>$this->achievement_details,'suggestedAmount'=>(float)$this->suggested_amount,'selectedAmount'=>(float)$this->selected_amount,'selectedType'=>$this->selected_type,'isGranted'=>(bool)$this->is_granted,'grantedAt'=>$this->granted_at?->toISOString(),'grantedBy'=>$this->granted_by,'grantedAmount'=>$this->granted_amount === null ? null : (float)$this->granted_amount,'isAutomatic'=>(bool)$this->is_automatic,'calculationKey'=>$this->calculation_key,'calculationScore'=>$this->calculation_score === null ? null : (float)$this->calculation_score,'calculationInputs'=>$this->calculation_inputs,'ruleSnapshot'=>$this->rule_snapshot,'calculatedAt'=>$this->calculated_at?->toISOString(),'bonusMonth'=>$this->bonus_month->format('Y-m')]; }
}
