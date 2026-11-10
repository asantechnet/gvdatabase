<script setup lang="ts">
import { computed, ref } from 'vue'
import type { AssetCondition, AssetItem } from '@/api/asset'
import type { Currency } from '@/api/currency'
import type { Employee } from '@/api/employee'
import ErpField from '@/components/erp/ErpField.vue'
import { formatActiveMoney, translate } from '@/utils/localization'

const props = defineProps<{
  modelValue: boolean
  asset: AssetItem | null
  mode: 'create' | 'edit'
  currencies: Currency[]
  offices: Array<{ title: string; value: number; active: boolean }>
  employees: Employee[]
  employeesLoading: boolean
  conditions: Array<{ title: string; value: AssetCondition }>
  saving?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean]; save: []; addAllocation: []; removeAllocation: [index: number] }>()
const form = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const currencyOptions = computed(() => props.currencies.map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))
const selectedCurrency = computed(() => props.currencies.find(currency => currency.id === props.asset?.currency_id))
const total = computed(() => Number(props.asset?.quantity ?? 0) * Number(props.asset?.price ?? 0))
const allocated = computed(() => props.asset?.allocations.reduce((sum, allocation) => sum + Number(allocation.quantity || 0), 0) ?? 0)
const isBlank = (value: unknown) => value === null || value === undefined || String(value).trim() === ''
const required = (value: unknown) => !isBlank(value) || translate('common.validation.required')
const wholeNumber = (minimum: number) => (value: unknown) => (Number.isInteger(Number(value)) && Number(value) >= minimum) || translate('pages.finance.asset.asset.wholeNumberAtLeast', { min: minimum })
const nonNegative = (value: unknown) => (!isBlank(value) && Number.isFinite(Number(value)) && Number(value) >= 0) || translate('pages.finance.asset.asset.nonNegativeNumber')
const optional = (rule: (value: unknown) => true | string) => (value: unknown) => isBlank(value) ? true : rule(value)

const dockCalendar = (_selectedDates: Date[], _dateString: string, instance: any) => {
  window.setTimeout(() => {
    const calendar = instance?.calendarContainer as HTMLElement | undefined
    if (!calendar) return
    calendar.style.transform = 'translateY(0) scale(0.86)'
    calendar.style.transformOrigin = 'top left'
    calendar.style.zIndex = '3100'
  })
}

const submit = async () => {
  const result = await form.value?.validate()
  if (result && !result.valid) return
  emit('save')
}
</script>

<template>
  <VDialog :model-value="modelValue" max-width="1000px" class="asset-form-overlay" content-class="asset-form-dialog" @update:model-value="emit('update:modelValue', $event)">
    <VCard v-if="asset" class="asset-dialog app-form-dialog">
      <VCardTitle class="asset-dialog__header"><div class="d-flex align-center ga-3"><VAvatar color="primary" variant="tonal" size="38"><VIcon :icon="mode === 'create' ? 'tabler-package-import' : 'tabler-pencil'" /></VAvatar><span class="text-h5 font-weight-bold">{{ mode === 'create' ? $t('pages.finance.asset.asset.addNewAssetItem') : $t('pages.finance.asset.asset.editAssetItem') }}</span></div><VBtn icon size="small" data-confirm-form-cancel class="asset-dialog__close" :aria-label="$t('common.actions.close')" @click="emit('update:modelValue', false)"><VIcon icon="tabler-x" /></VBtn></VCardTitle>
      <VForm ref="form" class="app-form" @submit.prevent="submit"><VCardText class="pt-5"><VRow>
        <VCol cols="12" md="4"><ErpField v-model="asset.serial_number" :label="$t('common.messages.serialNumber')" :placeholder="$t('common.messages.serialNumber')" :rules="[required]" required /></VCol>
        <VCol cols="12" md="4"><ErpField v-model="asset.item" :label="$t('common.messages.itemName')" :placeholder="$t('common.messages.itemName')" :rules="[required]" required /></VCol>
        <VCol cols="12" md="4"><ErpField v-model.number="asset.quantity" type="number" min="1" :label="$t('common.messages.quantity')" :placeholder="$t('common.messages.quantity')" :rules="[required, wholeNumber(1)]" required /></VCol>
        <VCol cols="12" md="4"><ErpField v-model.number="asset.price" type="number" min="0" :label="$t('pages.finance.asset.asset.unitPrice')" :placeholder="$t('pages.finance.asset.asset.unitPrice')" :rules="[required, nonNegative]" required /></VCol>
        <VCol cols="12" md="4"><ErpField v-model="asset.currency_id" kind="select" :items="currencyOptions" :label="$t('common.fields.currency')" :placeholder="$t('common.fields.currency')" :rules="[required]" required /></VCol>
        <VCol cols="12" md="4"><ErpField v-model="asset.acquisition_date" kind="date" :label="$t('pages.finance.asset.asset.acquisitionDate')" :placeholder="$t('pages.finance.asset.asset.acquisitionDate')" :rules="[required]" required :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockCalendar }" /></VCol>
        <VCol cols="12" md="6"><ErpField v-model.number="asset.useful_life_years" type="number" min="1" :label="$t('pages.finance.asset.asset.usefulLifeYears')" :placeholder="$t('pages.finance.asset.asset.usefulLifeYears')" :rules="[optional(wholeNumber(1))]" /></VCol>
        <VCol cols="12" md="6"><ErpField v-model.number="asset.salvage_value" type="number" min="0" :label="$t('pages.finance.asset.asset.salvageValue')" :placeholder="$t('pages.finance.asset.asset.salvageValue')" :rules="[optional(nonNegative)]" /></VCol>
        <VCol cols="12"><div class="asset-value-summary"><VAvatar color="primary" variant="tonal" rounded="lg"><VIcon icon="tabler-calculator" /></VAvatar><div><span class="asset-value-summary__label">{{ $t('pages.finance.asset.asset.totalValue') }}</span><strong>{{ formatActiveMoney(total, selectedCurrency ? { ...selectedCurrency, decimalPlaces: selectedCurrency.decimalPlaces, rate: 1 } : undefined) }}</strong><span class="text-caption text-medium-emphasis">{{ $t('pages.finance.asset.asset.valuationAfterSave') }}</span></div></div></VCol>
        <VCol cols="12" md="4"><ErpField v-model="asset.model" :label="$t('common.messages.model')" :placeholder="$t('common.messages.model')" :rules="[required]" required /></VCol>
        <VCol cols="12" md="4"><ErpField v-model="asset.office_id" kind="select" :items="offices" :label="$t('common.fields.office')" :placeholder="$t('common.fields.office')" :rules="[required]" required /></VCol>
        <VCol cols="12" md="4"><ErpField v-model="asset.condition" kind="select" :items="conditions" :label="$t('common.messages.condition')" :placeholder="$t('common.messages.condition')" :rules="[required]" required /></VCol>
        <VCol cols="12"><div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3"><span class="text-subtitle-2">{{ $t('pages.finance.asset.asset.employeeAllocations') }}</span><VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="emit('addAllocation')">{{ $t('pages.finance.asset.asset.addEmployee') }}</VBtn></div><div class="asset-allocation-summary mb-3"><VIcon icon="tabler-users" /><span>{{ mode === 'create' ? $t('pages.finance.asset.asset.allocationTotalCannotExceed', { allocated, quantity: asset.quantity }) : $t('pages.finance.asset.asset.allocationReduceBeforeLowering', { allocated, quantity: asset.quantity }) }}</span></div>
          <VRow v-for="(allocation, index) in asset.allocations" :key="index"><VCol cols="12" md="6"><ErpField v-model="allocation.employee_id" kind="autocomplete" :items="employees" item-title="name" item-value="id" :loading="employeesLoading" :label="$t('pages.finance.asset.asset.employee')" :placeholder="$t('pages.finance.asset.asset.employee')" /></VCol><VCol cols="9" md="5"><ErpField v-model.number="allocation.quantity" type="number" min="1" :max="asset.quantity" :label="$t('common.messages.quantity')" :placeholder="$t('common.messages.quantity')" /></VCol><VCol cols="3" md="1" class="d-flex align-center"><VBtn icon="tabler-trash" variant="text" color="error" :aria-label="$t('pages.finance.asset.asset.removeAllocation')" @click="emit('removeAllocation', index)" /></VCol></VRow>
        </VCol>
      </VRow></VCardText><VCardActions class="asset-dialog__footer"><VSpacer /><VBtn data-confirm-form-cancel class="asset-dialog__cancel" @click="emit('update:modelValue', false)">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" variant="flat" type="submit" :loading="saving">{{ $t('common.actions.save') }}</VBtn></VCardActions></VForm>
    </VCard>
  </VDialog>
</template>

<style scoped>
.asset-dialog { overflow: hidden; }.asset-dialog__header { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.5rem;background:color-mix(in srgb,rgb(var(--v-theme-surface)) 94%,rgb(var(--v-theme-on-surface)) 6%);border-block-end:1px solid rgba(var(--v-theme-on-surface),.07); }.asset-dialog__footer { padding:1rem 1.5rem;background:color-mix(in srgb,rgb(var(--v-theme-surface)) 96%,rgb(var(--v-theme-on-surface)) 4%);border-block-start:1px solid rgba(var(--v-theme-on-surface),.07); }.asset-dialog__close { min-inline-size:2.25rem!important;block-size:2.25rem!important;border-radius:999px!important;background:rgba(var(--v-theme-error),.14)!important;color:rgb(var(--v-theme-error))!important; }.asset-dialog__close :deep(.v-btn__overlay){opacity:0!important}.asset-dialog__cancel { border:1px solid rgba(var(--v-theme-secondary),.35)!important;background:rgba(var(--v-theme-secondary),.13)!important;color:rgb(var(--v-theme-secondary))!important;box-shadow:none!important }.asset-dialog__cancel:hover{background:rgb(var(--v-theme-secondary))!important;color:rgb(var(--v-theme-on-secondary))!important}.asset-value-summary,.asset-allocation-summary { display:flex;align-items:center;gap:.75rem;padding:1rem;border:1px solid rgba(var(--v-theme-primary),.13);border-radius:10px;background:rgba(var(--v-theme-primary),.055) }.asset-value-summary>div{display:grid;gap:.125rem}.asset-value-summary__label{font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:rgba(var(--v-theme-on-surface),.62)}.asset-value-summary strong{font-size:1.05rem}.asset-allocation-summary{background:rgba(var(--v-theme-on-surface),.035);border-color:rgba(var(--v-theme-on-surface),.08)}:global(.asset-form-overlay .v-overlay__scrim),:global(.v-overlay:has(.asset-form-dialog) .v-overlay__scrim){background:rgba(var(--v-theme-on-surface),.3)!important;backdrop-filter:blur(7px);opacity:1!important}@media(max-width:600px){.asset-dialog__header,.asset-dialog__footer{padding-inline:1rem}}
</style>
