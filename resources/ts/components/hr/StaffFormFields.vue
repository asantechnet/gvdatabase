<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import ErpField from '@/components/erp/ErpField.vue'
import StaffCommissionFields from '@/components/StaffCommissionFields.vue'
import StaffProfileImageUpload from './StaffProfileImageUpload.vue'
import type { Department } from '@/api/department'
import type { EmployeeAccount } from '@/api/employee'
import type { Office } from '@/api/office'

export interface StaffFormData {
  user_id: number | null
  name: string
  father_name: string
  contact: string
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  id_number: string | null
  bank_account_number: string | null
  guarantor_name: string | null
  guarantor_id_number: string | null
  guarantor_phone: string | null
  guarantor_address: string | null
  guarantor_licence: string | null
  education_lvl: string
  position: string
  status: 'active' | 'inactive'
  email: string
  office_id: number | null
  department_ids?: number[]
  salary: number | null
  addr: string
  join_date: string
  off_day_allowance_days: number | null
  is_commission_based: boolean
  service_commissions?: Array<{ service: string; percentage: number }>
  avatar?: string | null
}

const model = defineModel<StaffFormData>({ required: true })
const { t } = useI18n()
const props = defineProps<{
  step: 1 | 2 | 3
  errors: Record<string, string>
  educationOptions: string[]
  positionOptions: string[]
  offices: Office[]
  departments: Department[]
  accounts: EmployeeAccount[]
  isPositionsLoading: boolean
  isAccountsLoading: boolean
  canAssignDepartments: boolean
  commissionOptions: Array<{ value: string; title: string }>
  commissionRate: (service: string) => number | null
  avatarFile: File | null
  avatarRemoved: boolean
}>()

const emit = defineEmits<{
  positionChange: [value: string]
  accountChange: [value: number | null]
  commissionEnabled: [value: boolean]
  commissionRateChange: [service: string, value: unknown]
  avatarSelect: [file: File]
  avatarRemove: []
}>()

const departmentOptions = () => props.departments.map(department => ({
  title: department.name,
  value: department.id,
}))

function dockDatePickerCalendar(_: unknown, __: unknown, instance: any) {
  requestAnimationFrame(() => {
    const calendar = instance?.calendarContainer as HTMLElement | undefined

    if (!calendar)
      return

    calendar.style.transform = 'translateY(0) scale(0.86)'
    calendar.style.transformOrigin = 'top left'
    calendar.style.zIndex = '2500'
  })
}
</script>

<template>
  <VRow class="staff-profile-fields">
    <template v-if="props.step === 1">
      <VCol cols="12" md="4">
        <ErpField v-model="model.name" :label="t('common.messages.fullName')" placeholder="e.g. Amina Rahimi" :error-messages="props.errors.name" required :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.father_name" :label="t('common.messages.fatherName')" placeholder="Father or guardian name" :error-messages="props.errors.father_name" required :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.contact" dir="ltr" inputmode="tel" :label="t('common.messages.contactPhone')" placeholder="0700 000 000" :error-messages="props.errors.contact" required :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.id_number" :label="t('common.messages.governmentIDNumber')" placeholder="Government ID number" :error-messages="props.errors.id_number" required :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.email" type="email" prepend-inner-icon="tabler-mail" :label="t('common.fields.email')" placeholder="name@company.com" :error-messages="props.errors.email" :required="!model.user_id" :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.education_lvl" kind="select" :items="props.educationOptions" :label="t('common.messages.educationLevel')" placeholder="Select education level" :error-messages="props.errors.education_lvl" required :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.emergency_contact_name" :label="t('pages.settings.staffList.staffList.emergencyContactName')" placeholder="Emergency contact name" clearable />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.emergency_contact_phone" dir="ltr" inputmode="tel" :label="t('pages.settings.staffList.staffList.emergencyContactPhone')" placeholder="Emergency contact phone" clearable />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.user_id" kind="select" :items="props.accounts" item-title="name" item-value="id" :label="t('common.accountPicker.label')" :placeholder="t('common.accountPicker.placeholder')" clearable :loading="props.isAccountsLoading" @update:model-value="emit('accountChange', $event)" />
      </VCol>
    </template>

    <template v-else-if="props.step === 2">
      <VCol cols="12" md="4">
        <ErpField v-model="model.position" kind="select" :items="props.positionOptions" :label="t('common.messages.position')" placeholder="Select a position" :error-messages="props.errors.position" :loading="props.isPositionsLoading" required :show-required-rule="false" @update:model-value="emit('positionChange', $event)" />
      </VCol>
      <VCol cols="12" md="4">
        <div class="erp-field">
          <label class="erp-field-label">{{ t('common.messages.joinDate') }} <span class="erp-field-required">*</span></label>
          <AppDateTimePicker v-model="model.join_date" class="erp-field-control" placeholder="Select joining date" persistent-placeholder :error-messages="props.errors.join_date" :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDatePickerCalendar }" />
        </div>
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.office_id" kind="select" :items="props.offices" item-title="name" item-value="id" :label="t('common.fields.office')" placeholder="Select office" clearable />
      </VCol>
      <VCol cols="12" md="6">
        <ErpField v-model="model.salary" type="number" min="0" :label="t('common.messages.salary')" placeholder="Monthly base salary" :error-messages="props.errors.salary" required :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="6">
        <ErpField v-model="model.department_ids" kind="autocomplete" :items="departmentOptions()" item-title="title" item-value="value" :label="t('common.fields.departments')" placeholder="Select departments" :disabled="!props.canAssignDepartments" :error-messages="props.errors.department_ids" multiple chips closable-chips clearable />
      </VCol>

      <VCol cols="12" md="6">
        <ErpField v-model="model.off_day_allowance_days" type="number" min="0" step="0.5" :label="t('pages.settings.staffList.staffList.offDayAllowanceDays')" placeholder="Annual days" />
      </VCol>
      <VCol cols="12" md="6">
        <ErpField v-model="model.status" kind="select" :items="[{ title: t('common.statuses.active'), value: 'active' }, { title: t('common.statuses.inactive'), value: 'inactive' }]" :label="t('common.fields.status')" />
      </VCol>
      <StaffCommissionFields :enabled="model.is_commission_based" :options="props.commissionOptions" :rate="props.commissionRate" @update:enabled="emit('commissionEnabled', $event)" @update:rate="(service, value) => emit('commissionRateChange', service, value)" />
    </template>

    <template v-else>
      <VCol cols="12" md="4">
        <ErpField v-model="model.addr" :label="t('common.fields.address')" placeholder="Street, area, city" :error-messages="props.errors.addr" required :show-required-rule="false" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.bank_account_number" :label="t('common.ui.bankAccountNumber')" placeholder="Bank account number" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.guarantor_name" :label="t('common.messages.guarantorName')" placeholder="Guarantor full name" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.guarantor_id_number" :label="t('common.messages.guarantorGovernmentID')" placeholder="Guarantor ID number" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.guarantor_phone" dir="ltr" inputmode="tel" :label="t('common.messages.guarantorPhone')" placeholder="Guarantor phone number" />
      </VCol>
      <VCol cols="12" md="4">
        <ErpField v-model="model.guarantor_licence" :label="t('common.messages.guarantorLicence')" placeholder="Licence or reference number" />
      </VCol>
      <VCol cols="12">
        <ErpField v-model="model.guarantor_address" :label="t('common.messages.guarantorAddress')" placeholder="Guarantor address" />
      </VCol>
      <VCol cols="12">
        <StaffProfileImageUpload
          :name="model.name"
          :avatar="props.avatarRemoved ? null : model.avatar"
          :file="props.avatarFile"
          @select="emit('avatarSelect', $event)"
          @remove="emit('avatarRemove')"
        />
      </VCol>
    </template>
  </VRow>
</template>
