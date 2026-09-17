<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import Api, { type EducationalBonusSource, type EducationalService, type EducationalServiceInput, type EducationalServiceOptions, type EducationalServiceType } from '@/api/educationalServices'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const type = ref<EducationalServiceType>('scholarships')
const rows = ref<EducationalService[]>([])
const bonusRows = ref<EducationalBonusSource[]>([])
const options = ref<EducationalServiceOptions>({ customers: [], employees: [], applicants: [], currencies: [], package_types: [], contracts: [] })
const loading = ref(false)
const dialog = ref(false)
const bonusDialog = ref(false)
const editing = ref<EducationalService | null>(null)
const selectedBonus = ref<EducationalBonusSource | null>(null)
const contractId = ref<number | null>(null)
const contractAmount = ref<number | null>(null)
const bonusForm = ref({ bonus_amount: 0, rule_source: '' })

const emptyForm = (): EducationalServiceInput => ({
  customer_id: null, applicant_id: null, employee_id: null,
  currency_id: options.value.currencies.find(currency => currency.is_base)?.id ?? null,
  package_type_id: null, date: new Date().toISOString().slice(0, 10), fee: null, status: 'active', bonus_amount: 0, bonus_rule_source: '',
})
const form = ref<EducationalServiceInput>(emptyForm())
const canManageServices = computed(() => canAccess('manage_customer_services'))
const canViewBonuses = computed(() => canAccess('view_bonuses'))
const canManageBonuses = computed(() => canAccess('manage_commission_settlements'))
const applicantOptions = computed(() => options.value.applicants.filter(applicant => !form.value.customer_id || applicant.customer_id === form.value.customer_id).map(applicant => ({ title: `Case #${applicant.id} — ${applicant.customer?.name ?? 'Customer'}`, value: applicant.id })))
const contractOptions = computed(() => options.value.contracts.map(contract => ({ title: `${contract.contract_number ?? `Contract #${contract.id}`} — ${contract.name ?? 'Customer contract'}`, value: contract.id })))
const sourceLabel = (row: EducationalBonusSource) => row.source_type.split('\\').pop()?.replace(/([a-z])([A-Z])/g, '$1 $2') ?? 'Service'
const money = (amount: number | string, currency?: EducationalService['currency']) => `${currency?.symbol ?? ''}${Number(amount).toLocaleString()}${currency?.code ? ` ${currency.code}` : ''}`

async function load() {
  loading.value = true
  try {
    const [services, formOptions, bonuses] = await Promise.all([
      Api.list(type.value),
      Api.options(),
      canViewBonuses.value ? Api.listBonusSources() : Promise.resolve([] as EducationalBonusSource[]),
    ])
    rows.value = services
    options.value = formOptions
    bonusRows.value = bonuses
  }
  catch {
    toast.error('Unable to load educational services.')
  }
  finally { loading.value = false }
}

function openCreate() { editing.value = null; form.value = emptyForm(); dialog.value = true }
function openEdit(row: EducationalService) {
  editing.value = row
  form.value = { customer_id: row.customer_id, applicant_id: row.applicant_id ?? null, employee_id: row.employee_id, currency_id: row.currency_id, package_type_id: row.package_type_id ?? null, date: row.date, fee: Number(row.fee), status: row.status, bonus_amount: Number(row.bonus_source?.bonus_amount ?? 0), bonus_rule_source: row.bonus_source?.rule_source ?? '' }
  dialog.value = true
}
async function save() {
  try {
    if (editing.value) await Api.update(type.value, editing.value.id, form.value)
    else await Api.create(type.value, form.value)
    dialog.value = false
    await load()
    toast.success(editing.value ? 'Service sale updated.' : 'Service sale recorded with its bonus source.')
  }
  catch { toast.error('Unable to save. Check the required customer, employee, currency and case relationship.') }
}
async function remove(row: EducationalService) {
  if (!confirm(`Void this ${type.value === 'scholarships' ? 'scholarship' : 'test offer'} sale? Its bonus source will also be voided.`)) return
  try { await Api.remove(type.value, row.id); await load(); toast.success('Service sale voided.') }
  catch { toast.error('Unable to void this sale.') }
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
    toast.success(selectedBonus.value ? 'Bonus source updated.' : 'Contract bonus source created.')
  }
  catch { toast.error('Unable to save the bonus source. Each source can only be attributed once.') }
}
async function setBonusStatus(row: EducationalBonusSource, status: EducationalBonusSource['status']) {
  try { await Api.updateBonus(row.id, { status }); await load(); toast.success(`Bonus marked ${status}.`) }
  catch { toast.error('This bonus status change is not allowed.') }
}

watch(type, load)
onMounted(load)
</script>

<template>
  <VCard>
    <VCardItem title="Educational services" subtitle="Sales stay linked to the responsible employee, customer, case and a reusable bonus source.">
      <template #append><VBtn v-if="canManageServices" color="primary" prepend-icon="tabler-plus" @click="openCreate">Record sale</VBtn></template>
    </VCardItem>
    <VProgressLinear v-if="loading" indeterminate />
    <VCardText>
      <VTabs v-model="type" class="mb-4"><VTab value="scholarships">Scholarship services</VTab><VTab value="test-offers">Test offers</VTab></VTabs>
      <VTable>
        <thead><tr><th>Customer / case</th><th>Employee</th><th>Package</th><th>Fee</th><th>Date</th><th>Bonus</th><th>Status</th><th v-if="canManageServices" /></tr></thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id"><td><div>{{ row.customer?.name ?? 'Unknown customer' }}</div><small class="text-medium-emphasis">{{ row.applicant ? `Case #${row.applicant.id}` : 'No linked case' }}</small></td><td>{{ row.employee?.name ?? 'Unassigned' }}</td><td>{{ row.package_type?.name ?? '—' }}</td><td>{{ money(row.fee, row.currency) }}</td><td>{{ row.date }}</td><td>{{ row.bonus_source ? `${Number(row.bonus_source.bonus_amount).toLocaleString()} (${row.bonus_source.status})` : '—' }}</td><td class="text-capitalize">{{ row.status }}</td><td v-if="canManageServices" class="text-end"><VBtn size="small" variant="text" @click="openEdit(row)">Edit</VBtn><VBtn size="small" color="error" variant="text" @click="remove(row)">Void</VBtn></td></tr>
          <tr v-if="!loading && !rows.length"><td :colspan="canManageServices ? 8 : 7" class="text-center text-medium-emphasis py-8">No service sales recorded.</td></tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <VCard v-if="canViewBonuses" class="mt-6">
    <VCardItem title="Bonus sources" subtitle="The same source structure supports contracts, scholarships and test offers.">
      <template #append><VBtn v-if="canManageBonuses" prepend-icon="tabler-plus" @click="openBonusCreate">Add contract source</VBtn></template>
    </VCardItem>
    <VCardText><VTable><thead><tr><th>Source</th><th>Employee</th><th>Customer / case</th><th>Source amount</th><th>Bonus</th><th>Rule</th><th>Status</th><th v-if="canManageBonuses" /></tr></thead><tbody>
      <tr v-for="row in bonusRows" :key="row.id"><td>{{ sourceLabel(row) }} #{{ row.source_id }}</td><td>{{ row.employee?.name ?? '—' }}</td><td><div>{{ row.customer?.name ?? '—' }}</div><small class="text-medium-emphasis">{{ row.applicant ? `Case #${row.applicant.id}` : 'No linked case' }}</small></td><td>{{ Number(row.source_amount).toLocaleString() }}</td><td>{{ Number(row.bonus_amount).toLocaleString() }}</td><td>{{ row.rule_source ?? '—' }}</td><td class="text-capitalize">{{ row.status }}</td><td v-if="canManageBonuses" class="text-end"><VBtn size="small" variant="text" :disabled="row.status === 'paid'" @click="openBonusEdit(row)">Edit</VBtn><VBtn v-if="row.status === 'pending'" size="small" color="success" variant="text" @click="setBonusStatus(row, 'approved')">Approve</VBtn><VBtn v-if="row.status === 'approved'" size="small" color="primary" variant="text" @click="setBonusStatus(row, 'paid')">Mark paid</VBtn><VBtn v-if="row.status !== 'paid' && row.status !== 'void'" size="small" color="error" variant="text" @click="setBonusStatus(row, 'void')">Void</VBtn></td></tr>
      <tr v-if="!loading && !bonusRows.length"><td :colspan="canManageBonuses ? 8 : 7" class="text-center text-medium-emphasis py-8">No bonus sources recorded.</td></tr>
    </tbody></VTable></VCardText>
  </VCard>

  <VDialog v-model="dialog" max-width="640"><VCard :title="editing ? 'Edit service sale' : 'Record service sale'"><VCardText><VRow><VCol cols="12" md="6"><VSelect v-model="form.customer_id" :items="options.customers.map(row => ({ title: row.name, value: row.id }))" label="Customer" /></VCol><VCol cols="12" md="6"><VSelect v-model="form.applicant_id" :items="applicantOptions" label="Applicant / case" clearable /></VCol><VCol cols="12" md="6"><VSelect v-model="form.employee_id" :items="options.employees.map(row => ({ title: row.name, value: row.id }))" label="Selling employee" /></VCol><VCol cols="12" md="6"><VSelect v-model="form.currency_id" :items="options.currencies.map(row => ({ title: `${row.code} — ${row.name}`, value: row.id }))" label="Currency" /></VCol><VCol cols="12" md="6"><VSelect v-model="form.package_type_id" :items="options.package_types.map(row => ({ title: row.name, value: row.id }))" label="Package type" clearable /></VCol><VCol cols="12" md="6"><VSelect v-model="form.status" :items="['active', 'completed', 'cancelled']" label="Sale status" /></VCol><VCol cols="12" md="6"><VTextField v-model="form.date" type="date" label="Date" /></VCol><VCol cols="12" md="6"><VTextField v-model.number="form.fee" type="number" min="0.000001" label="Service fee" /></VCol><VCol cols="12" md="6"><VTextField v-model.number="form.bonus_amount" type="number" min="0" label="Bonus amount" /></VCol><VCol cols="12" md="6"><VTextField v-model="form.bonus_rule_source" label="Bonus rule / source" hint="For example: September referral policy" persistent-hint /></VCol></VRow></VCardText><VCardActions><VSpacer/><VBtn @click="dialog = false">Cancel</VBtn><VBtn color="primary" :disabled="!form.customer_id || !form.employee_id || !form.currency_id || !form.date || !form.fee || form.fee <= 0" @click="save">Save</VBtn></VCardActions></VCard></VDialog>
  <VDialog v-model="bonusDialog" max-width="560"><VCard :title="selectedBonus ? 'Edit bonus source' : 'Add contract bonus source'"><VCardText><template v-if="!selectedBonus"><VSelect v-model="contractId" :items="contractOptions" label="Contract" /><VTextField v-model.number="contractAmount" type="number" min="0.000001" label="Contract source amount" /></template><VTextField v-model.number="bonusForm.bonus_amount" type="number" min="0" label="Bonus amount" /><VTextField v-model="bonusForm.rule_source" label="Bonus rule / source" /></VCardText><VCardActions><VSpacer/><VBtn @click="bonusDialog = false">Cancel</VBtn><VBtn color="primary" :disabled="!selectedBonus && (!contractId || !contractAmount || contractAmount <= 0)" @click="saveBonus">Save</VBtn></VCardActions></VCard></VDialog>
</template>
