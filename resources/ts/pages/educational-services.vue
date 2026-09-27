<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import Api, { type EducationalBonusSource, type EducationalService, type EducationalServiceInput, type EducationalServiceOptions, type EducationalServiceType } from '@/api/educationalServices'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate, translateBackendValue } from '@/utils/localization'

const toast = useToast()
const { t, n } = useI18n()
const type = ref<EducationalServiceType>('scholarships')
const rows = ref<EducationalService[]>([])
const bonusRows = ref<EducationalBonusSource[]>([])
const options = ref<EducationalServiceOptions>({ employees: [], applicants: [], currencies: [], package_types: [], services: [], countries: [], education_levels: [], languages: [], contracts: [] })
const loading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(25)
const total = ref(0)
const dialog = ref(false)
const bonusDialog = ref(false)
const editing = ref<EducationalService | null>(null)
const selectedBonus = ref<EducationalBonusSource | null>(null)
const contractId = ref<number | null>(null)
const contractAmount = ref<number | null>(null)
const bonusForm = ref({ bonus_amount: 0, rule_source: '' })

const emptyForm = (): EducationalServiceInput => ({
  applicant_id: null, employee_id: options.value.employees.length === 1 ? options.value.employees[0].id : null,
  currency_id: options.value.currencies.find(currency => currency.is_base)?.id ?? null,
  service_id: null, package_type_id: null, country_id: null, education_level_id: null, language_id: null, date: new Date().toISOString().slice(0, 10), fee: null, status: 'active', bonus_amount: 0, bonus_rule_source: '', extend: true,
})
const form = ref<EducationalServiceInput>(emptyForm())
const canManageServices = computed(() => canAccess('manage_educational_services'))
const canViewBonuses = computed(() => canAccess('view_bonuses'))
const canManageBonuses = computed(() => canAccess('manage_educational_bonuses'))
const applicantOptions = computed(() => options.value.applicants.map(applicant => ({ title: applicant.name, value: applicant.id })))
const contractOptions = computed(() => options.value.contracts.map(contract => ({ title: t('educational.contractOption', { number: contract.contract_number ?? `#${contract.id}`, name: contract.name ?? t('educational.customerContract') }), value: contract.id })))
const selectedService = computed(() => options.value.services.find(row => row.id === form.value.service_id) ?? null)
const sourceLabel = (row: EducationalBonusSource) => row.source_type.includes('Contract') ? t('educational.contract') : row.source_type.includes('Scholarship') ? t('educational.scholarshipService') : row.source_type.includes('TestOffer') ? t('educational.testOffer') : t('educational.service')
const money = (amount: number | string, currency?: EducationalService['currency']) => `${currency?.symbol ?? ''}${n(Number(amount), 'decimal')}${currency?.code ? ` ${currency.code}` : ''}`
const formatDate = (value: string) => formatLocalizedDate(value, { year: 'numeric', month: 'short', day: 'numeric' })

async function load() {
  loading.value = true
  try {
    const [services, formOptions, bonuses] = await Promise.all([
      Api.list(type.value, page.value, perPage.value),
      Api.options(),
      canViewBonuses.value ? Api.listBonusSources() : Promise.resolve([] as EducationalBonusSource[]),
    ])
    rows.value = services.data
    page.value = services.current_page
    lastPage.value = services.last_page
    perPage.value = services.per_page
    total.value = services.total
    options.value = formOptions
    if (!form.value.employee_id && formOptions.employees.length === 1)
      form.value.employee_id = formOptions.employees[0].id
    bonusRows.value = bonuses
  }
  catch {
    toast.error(t('educational.servicesUi.loadFailed'))
  }
  finally { loading.value = false }
}

function openCreate() { editing.value = null; form.value = emptyForm(); dialog.value = true }

// Picking a service pre-fills its catalogue price and currency; the price
// stays editable per customer (that is the business rule). It also tells the
// user which contract template the sale will generate.
watch(() => form.value.service_id, serviceId => {
  if (editing.value || !serviceId)
    return

  const service = options.value.services.find(row => row.id === serviceId)
  if (!service)
    return

  if (form.value.fee === null || form.value.fee === 0)
    form.value.fee = Number(service.fee)
  if (!form.value.currency_id)
    form.value.currency_id = service.currency_id
})
function openEdit(row: EducationalService) {
  editing.value = row
  form.value = { service_id: row.service_id, applicant_id: row.applicant_id ?? null, country_id: row.country_id ?? null, education_level_id: row.education_level_id ?? null, language_id: row.language_id ?? null, employee_id: row.employee_id, currency_id: row.currency_id, package_type_id: row.package_type_id ?? null, date: row.date, fee: Number(row.fee), status: row.status, bonus_amount: Number(row.bonus_source?.bonus_amount ?? 0), bonus_rule_source: row.bonus_source?.rule_source ?? '' }
  dialog.value = true
}
async function save() {
  try {
    if (editing.value) await Api.update(type.value, editing.value.id, form.value)
    else await Api.create(type.value, form.value)
    dialog.value = false
    await load()
    toast.success(t(editing.value ? 'educational.servicesUi.updated' : 'educational.servicesUi.created'))
  }
  catch { toast.error(t('educational.servicesUi.saveFailed')) }
}
async function remove(row: EducationalService) {
  if (!confirm(t('educational.servicesUi.voidConfirm', { service: t(type.value === 'scholarships' ? 'educational.scholarshipService' : 'educational.testOffer') }))) return
  try { await Api.remove(type.value, row.id); await load(); toast.success(t('educational.servicesUi.voided')) }
  catch { toast.error(t('educational.servicesUi.voidFailed')) }
}
function openBonusCreate() { contractId.value = null; contractAmount.value = null; bonusForm.value = { bonus_amount: 0, rule_source: '' }; selectedBonus.value = null; bonusDialog.value = true }
function openBonusEdit(row: EducationalBonusSource) { selectedBonus.value = row; bonusForm.value = { bonus_amount: Number(row.bonus_amount), rule_source: row.rule_source ?? '' }; bonusDialog.value = true }
async function saveBonus() {
  try {
    if (selectedBonus.value) await Api.updateBonus(selectedBonus.value.id, { ...bonusForm.value })
    else if (contractId.value && contractAmount.value && contractAmount.value > 0) await Api.createContractBonus({ source_type: 'contract', source_id: contractId.value, source_amount: contractAmount.value, ...bonusForm.value })
    else return
    bonusDialog.value = false
    await load()
    toast.success(t(selectedBonus.value ? 'educational.servicesUi.bonusUpdated' : 'educational.servicesUi.bonusCreated'))
  }
  catch { toast.error(t('educational.servicesUi.bonusSaveFailed')) }
}
async function setBonusStatus(row: EducationalBonusSource, status: EducationalBonusSource['status']) {
  try { await Api.updateBonus(row.id, { status }); await load(); toast.success(t('educational.servicesUi.bonusMarked', { status: translateBackendValue(status) })) }
  catch { toast.error(t('educational.servicesUi.bonusStatusFailed')) }
}

watch(type, () => { page.value = 1; load() })
onMounted(load)
</script>

<template>
  <div class="education-workspace">
  <VCard class="education-panel">
    <VCardItem :title="$t('educational.servicesUi.title')" :subtitle="$t('educational.servicesUi.subtitle')">
      <template #append><VBtn v-if="canManageServices" color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $t('educational.servicesUi.recordSale') }}</VBtn></template>
    </VCardItem>
    <VProgressLinear v-if="loading" indeterminate />
    <VCardText>
      <VTabs v-model="type" class="mb-4"><VTab value="scholarships">{{ $t('educational.scholarshipServices') }}</VTab><VTab value="test-offers">{{ $t('educational.testOffers') }}</VTab></VTabs>
      <div class="education-table-scroll"><VTable density="compact" class="education-table">
        <thead><tr><th>{{ $t('educational.customerCase') }}</th><th>{{ $t('educational.employee') }}</th><th>{{ $t('educational.package') }}</th><th>{{ $t('educational.contract') }}</th><th>{{ $t('educational.fee') }}</th><th>{{ $t('educational.date') }}</th><th>{{ $t('educational.bonus') }}</th><th>{{ $t('educational.status') }}</th><th v-if="canManageServices" /></tr></thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id"><td><div>{{ row.applicant?.name ?? $t('educational.unknownCustomer') }}</div><small class="text-medium-emphasis">{{ row.applicant ? $t('educational.caseNumber', { id: row.applicant.id }) : $t('educational.noLinkedCase') }}</small></td><td>{{ row.employee?.name ?? $t('educational.unassigned') }}</td><td>{{ row.package_type?.name ?? '—' }}</td><td><div v-if="row.contract_number">{{ row.contract_number }}</div><VChip v-if="row.contract_role === 'extension'" size="x-small" color="primary" variant="tonal">{{ $t('educational.extension') }}</VChip><small v-else-if="row.contract_number" class="text-medium-emphasis">{{ $t('educational.primaryContract') }}</small><span v-else>—</span></td><td>{{ money(row.fee, row.currency) }}</td><td>{{ formatDate(row.date) }}</td><td>{{ row.bonus_source ? `${n(Number(row.bonus_source.bonus_amount), 'decimal')} (${translateBackendValue(row.bonus_source.status)})` : '—' }}</td><td class="text-capitalize">{{ translateBackendValue(row.status) }}</td><td v-if="canManageServices" class="text-end"><VBtn size="small" variant="text" @click="openEdit(row)">{{ $t('common.actions.edit') }}</VBtn><VBtn size="small" color="error" variant="text" @click="remove(row)">{{ $t('common.actions.void') }}</VBtn></td></tr>
          <tr v-if="!loading && !rows.length"><td :colspan="canManageServices ? 9 : 8" class="text-center text-medium-emphasis py-8">{{ $t('educational.servicesUi.noSales') }}</td></tr>
        </tbody>
      </VTable></div>
    </VCardText>
    <VCardText class="d-flex align-center gap-4"><VPagination v-model="page" :length="Math.max(lastPage, 1)" size="small" @update:model-value="load"/><VSelect v-model="perPage" :items="[10, 25, 50, 100]" :label="$t('educational.perPage')" style="max-inline-size: 140px" @update:model-value="page = 1; load()"/><span class="text-caption text-medium-emphasis">{{ $t('educational.resultCount', { count: total }) }}</span></VCardText>
  </VCard>

  <VCard v-if="canViewBonuses" class="education-panel mt-6">
    <VCardItem :title="$t('educational.servicesUi.bonusSources')" :subtitle="$t('educational.servicesUi.bonusSubtitle')">
      <template #append><VBtn v-if="canManageBonuses" prepend-icon="tabler-plus" @click="openBonusCreate">{{ $t('educational.servicesUi.addContractSource') }}</VBtn></template>
    </VCardItem>
    <VCardText><div class="education-table-scroll"><VTable density="compact" class="education-table"><thead><tr><th>{{ $t('educational.source') }}</th><th>{{ $t('educational.employee') }}</th><th>{{ $t('educational.customerCase') }}</th><th>{{ $t('educational.sourceAmount') }}</th><th>{{ $t('educational.bonus') }}</th><th>{{ $t('educational.rule') }}</th><th>{{ $t('educational.status') }}</th><th v-if="canManageBonuses" /></tr></thead><tbody>
      <tr v-for="row in bonusRows" :key="row.id"><td>{{ sourceLabel(row) }} #{{ row.source_id }}</td><td>{{ row.employee?.name ?? '—' }}</td><td><div>{{ row.applicant?.name ?? '—' }}</div><small class="text-medium-emphasis">{{ row.applicant ? $t('educational.caseNumber', { id: row.applicant.id }) : $t('educational.noLinkedCase') }}</small></td><td>{{ n(Number(row.source_amount), 'decimal') }}</td><td>{{ n(Number(row.bonus_amount), 'decimal') }}</td><td>{{ row.rule_source ?? '—' }}</td><td class="text-capitalize">{{ translateBackendValue(row.status) }}</td><td v-if="canManageBonuses" class="text-end"><VBtn size="small" variant="text" :disabled="row.status === 'paid'" @click="openBonusEdit(row)">{{ $t('common.actions.edit') }}</VBtn><VBtn v-if="row.status === 'pending'" size="small" color="success" variant="text" @click="setBonusStatus(row, 'approved')">{{ $t('common.actions.approve') }}</VBtn><VBtn v-if="row.status === 'approved'" size="small" color="primary" variant="text" @click="setBonusStatus(row, 'paid')">{{ $t('educational.markPaid') }}</VBtn><VBtn v-if="row.status !== 'paid' && row.status !== 'void'" size="small" color="error" variant="text" @click="setBonusStatus(row, 'void')">{{ $t('common.actions.void') }}</VBtn></td></tr>
      <tr v-if="!loading && !bonusRows.length"><td :colspan="canManageBonuses ? 8 : 7" class="text-center text-medium-emphasis py-8">{{ $t('educational.servicesUi.noBonuses') }}</td></tr>
    </tbody></VTable></div></VCardText>
  </VCard>
  </div>

  <VDialog v-model="dialog" max-width="640">
    <VCard :title="$t(editing ? 'educational.servicesUi.editSale' : 'educational.servicesUi.recordSale')">
      <VCardText>
        <VAlert type="info" variant="tonal" class="mb-4">{{ $t('educational.servicesUi.applicantServiceHint') }}</VAlert>
        <VAlert v-if="!editing && selectedService" type="success" variant="tonal" density="compact" class="mb-4">{{ selectedService.contract_template ? $t('educational.servicesUi.contractTemplateNotice', { template: selectedService.contract_template.name }) : $t('educational.servicesUi.contractTemplateAuto') }}</VAlert>
        <VRow>
          <VCol cols="12"><VSelect v-model="form.applicant_id" :items="applicantOptions" :label="$t('educational.applicantCase')" clearable /></VCol>
          <VCol cols="12" md="6"><VSelect v-model="form.service_id" :items="options.services.filter(row => row.category === (type === 'scholarships' ? 'scholarship' : 'test_offer')).map(row => ({ title: row.type, value: row.id }))" :label="$t('educational.service')" :disabled="!!form.package_type_id || !!editing" clearable /></VCol>
          <VCol cols="12" md="6"><VSelect v-model="form.package_type_id" :items="options.package_types.map(row => ({ title: row.name, value: row.id }))" :label="$t('educational.package')" :disabled="!!form.service_id || !!editing" clearable /></VCol>
          <VCol cols="12" md="6"><VSelect v-model="form.employee_id" :items="options.employees.map(row => ({ title: row.name, value: row.id }))" :label="$t('educational.sellingEmployee')" /></VCol>
          <VCol cols="12" md="6"><VSelect v-model="form.currency_id" :items="options.currencies.map(row => ({ title: `${row.code} — ${row.name}`, value: row.id }))" :label="$t('common.fields.currency')" /></VCol>
          <VCol cols="12" md="6"><VSelect v-model="form.status" :items="['active', 'completed', 'cancelled'].map(value => ({ value, title: translateBackendValue(value) }))" :label="$t('educational.saleStatus')" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.date" type="date" :label="$t('educational.date')" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model.number="form.fee" type="number" min="0" :label="$t('educational.serviceFee')" :hint="$t('educational.servicesUi.editableFeeHint')" persistent-hint /></VCol>
          <VCol v-if="!editing && form.service_id" cols="12" md="6"><VSwitch v-model="form.extend" color="primary" inset :label="$t('educational.servicesUi.allowExtension')" :hint="$t('educational.servicesUi.allowExtensionHint')" persistent-hint density="compact" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model.number="form.bonus_amount" type="number" min="0" :label="$t('educational.bonusAmount')" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.bonus_rule_source" :label="$t('educational.bonusRuleSource')" :hint="$t('educational.bonusRuleHint')" persistent-hint /></VCol>
        </VRow>
      </VCardText>
      <VCardActions><VSpacer/><VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="!form.applicant_id || !form.employee_id || !form.currency_id || !form.date || (!editing && !form.service_id && !form.package_type_id)" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="bonusDialog" max-width="640">
    <VCard :title="$t(selectedBonus ? 'educational.servicesUi.editBonus' : 'educational.servicesUi.addContractSource')">
      <VCardText class="pt-5">
        <VRow class="bonus-source-form">
          <template v-if="!selectedBonus">
            <VCol cols="12">
              <VSelect v-model="contractId" :items="contractOptions" :label="$t('educational.contract')" :hint="$t('educational.customerContract')" persistent-hint />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField v-model.number="contractAmount" type="number" min="0.000001" :label="$t('educational.contractSourceAmount')" />
            </VCol>
          </template>
          <VCol cols="12" sm="6">
            <VTextField v-model.number="bonusForm.bonus_amount" type="number" min="0" :label="$t('educational.bonusAmount')" />
          </VCol>
          <VCol cols="12">
            <VTextField v-model="bonusForm.rule_source" :label="$t('educational.bonusRuleSource')" :hint="$t('educational.bonusRuleHint')" persistent-hint />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="px-6 pb-5 pt-1">
        <VSpacer />
        <VBtn @click="bonusDialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :disabled="!selectedBonus && (!contractId || !contractAmount || contractAmount <= 0)" @click="saveBonus">{{ $t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
