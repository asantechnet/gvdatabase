<script setup lang="ts">
import { translate } from '@/utils/localization'
import { useToast } from 'vue-toastification'
import { useRouter } from 'vue-router'
import PositionApi, { type Position, type PositionPayload } from '@/api/position'
import CurrencyApi, { type Currency } from '@/api/currency'
import PositionForm from '@/components/forms/PositionForm.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'

const toast = useToast()
const router = useRouter()
const { t } = useI18n()
const positions = ref<Position[]>([])
const currencies = ref<Currency[]>([])
const search = ref('')
const statusFilter = ref<Position['status'] | null>(null)
const statusFilterDraft = ref<Position['status'] | null>(null)
const filterMenuOpen = ref(false)
const isLoading = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editingPosition = ref<Position | null>(null)
const deletingPosition = ref<Position | null>(null)

const blankForm = (): PositionPayload => ({ title: '', description: '', salary: 0, currency_id: null, rate: null, status: 'active' })
const form = ref<PositionPayload>(blankForm())
const headers = computed(() => [
  { title: t('common.messages.position'), key: 'title' },
  { title: t('pages.settings.positions.defaultSalary'), key: 'salary' },
  { title: t('common.fields.status'), key: 'status' },
  { title: t('pages.settings.positions.assigned'), key: 'employee_count' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])
const filteredPositions = computed(() => {
  const query = search.value.trim().toLowerCase()
  return positions.value.filter(position => (!query || position.title.toLowerCase().includes(query)) && (!statusFilter.value || position.status === statusFilter.value))
})
const hasActiveFilters = computed(() => Boolean(statusFilter.value))
const assignedLabel = (position: Position) => t('pages.settings.positions.assignedPeople', { staff: position.employee_count, users: position.user_count })
const currencyFor = (id: number | null) => currencies.value.find(currency => currency.id === id)
const formatSalary = (position: Position) => {
  const precision = position.currency?.decimalPlaces ?? 2
  const value = formatLocalizedNumber(Number(position.salary || 0), { minimumFractionDigits: precision, maximumFractionDigits: precision })
  return [value, position.currency?.code].filter(Boolean).join(' ')
}

async function loadPage() {
  isLoading.value = true
  try {
    const [positionResponse, currencyResponse] = await Promise.all([PositionApi.getAll(), CurrencyApi.getAll()])
    positions.value = positionResponse.data.data
    currencies.value = currencyResponse.data
  } catch (error: any) {
    toast.error(error.message || t('pages.settings.positions.couldNotLoad'))
  } finally { isLoading.value = false }
}
function openCreate() {
  editingPosition.value = null
  const baseCurrency = currencies.value.find(currency => currency.isBase) ?? currencies.value.find(currency => currency.isActive)
  form.value = { ...blankForm(), currency_id: baseCurrency?.id ?? null, rate: baseCurrency?.rate ?? null }
  dialogOpen.value = true
}
function openEdit(position: Position) {
  editingPosition.value = position
  const fallbackCurrency = currencyFor(position.currencyId) ?? currencies.value.find(currency => currency.isBase) ?? currencies.value.find(currency => currency.isActive)
  form.value = { title: position.title, description: position.description ?? '', salary: Number(position.salary), currency_id: fallbackCurrency?.id ?? null, rate: position.currency?.rate ?? fallbackCurrency?.rate ?? null, status: position.status }
  dialogOpen.value = true
}
function editPositionContract(position: Position) { router.push({ name: 'contract-documents', query: { tab: 'templates', positionId: position.id } }) }
async function save() {
  if (isSaving.value) return
  if (!form.value.title.trim()) return toast.error(translate('pages.settings.positions.positionTitleIsRequired'))
  if (!Number.isFinite(Number(form.value.salary)) || Number(form.value.salary) < 0) return toast.error(translate('pages.settings.positions.defaultSalaryMustBeZeroOrGreater'))
  if (!form.value.currency_id) return toast.error(translate('common.messages.selectACurrency'))
  if (!Number.isFinite(Number(form.value.rate)) || Number(form.value.rate) <= 0) return toast.error(translate('common.messages.exchangeRate'))
  isSaving.value = true
  try {
    const payload = { ...form.value, title: form.value.title.trim(), salary: Number(form.value.salary), rate: Number(form.value.rate) }
    const response = editingPosition.value ? await PositionApi.update(editingPosition.value.id, payload) : await PositionApi.create(payload)
    const saved = response.data.data as Position
    const index = positions.value.findIndex(position => position.id === saved.id)
    if (index === -1) positions.value.unshift(saved)
    else positions.value[index] = saved
    dialogOpen.value = false
    toast.success(translate(editingPosition.value ? 'pages.settings.positions.updated' : 'pages.settings.positions.created'))
  } catch (error: any) {
    toast.error(error.errors?.title?.[0] || error.errors?.currency_id?.[0] || error.errors?.rate?.[0] || error.message || t('pages.settings.positions.couldNotSave'))
  } finally { isSaving.value = false }
}
function applyFilters() { statusFilter.value = statusFilterDraft.value; filterMenuOpen.value = false }
function resetFilters() { statusFilterDraft.value = null; statusFilter.value = null; filterMenuOpen.value = false }
function askDelete(position: Position) { deletingPosition.value = position; deleteDialogOpen.value = true }
async function confirmDelete() {
  if (!deletingPosition.value || isDeleting.value) return
  isDeleting.value = true
  try {
    await PositionApi.delete(deletingPosition.value.id)
    positions.value = positions.value.filter(position => position.id !== deletingPosition.value?.id)
    deleteDialogOpen.value = false
    toast.success(translate('pages.settings.positions.positionDeleted'))
  } catch (error: any) { toast.error(error.message || t('pages.settings.positions.couldNotDelete')) }
  finally { isDeleting.value = false }
}
onMounted(loadPage)
</script>

<template>
  <div class="erp-workspace positions-workspace">
    <ErpPageHeader :title="$t('common.navigation.positions')" section="hr" />
    <VCard class="positions-table-card">
      <VCardText>
        <VRow align="center" class="table-toolbar">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="$t('pages.settings.positions.searchPositions')" /></VCol>
          <VCol cols="12" md="6" lg="7" class="positions-toolbar-actions">
            <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" :show-reset="false">
              <ErpField v-model="statusFilterDraft" kind="select" :items="['active', 'inactive']" :item-title="translateBackendValue" :label="$t('common.fields.status')" clearable />
              <template #actions><VBtn class="positions-filter-reset" variant="flat" @click="resetFilters">{{ $t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" class="positions-filter-apply" data-filter-apply @click="applyFilters">{{ $t('educational.apply') }}</VBtn></template>
            </DataTableFilterMenu>
            <VBtn color="primary" @click="openCreate"><VIcon icon="tabler-plus" class="me-1" />{{ $t('pages.settings.positions.addPosition') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <ResponsiveDataTable :headers="headers" :items="filteredPositions" :loading="isLoading" :skeleton-rows="5" :sticky-key-column="false" :sticky-action-column="false" item-value="id" mobile-cards mobile-expand-all mobile-actions-as-menu mobile-title-key="title" mobile-action-key="actions" :mobile-fields="['salary', 'status', 'employee_count']" class="positions-responsive-table">
        <template #item.title="{ item }"><div class="font-weight-medium">{{ item.title }}</div></template>
        <template #item.salary="{ item }"><span class="font-weight-medium text-no-wrap">{{ formatSalary(item) }}</span></template>
        <template #item.status="{ item }"><VChip size="small" label :color="item.status === 'active' ? 'success' : 'secondary'">{{ translateBackendValue(item.status) }}</VChip></template>
        <template #item.employee_count="{ item }">{{ assignedLabel(item) }}</template>
        <template #item.actions="{ item }"><div class="d-flex justify-end"><VMenu location="bottom end"><template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" :aria-label="$t('common.messages.actions')" /></template><VList density="compact" min-width="220" class="positions-actions-menu"><VListItem prepend-icon="tabler-file-pencil" :title="$t('pages.settings.positions.editContract')" @click="editPositionContract(item)" /><VListItem prepend-icon="tabler-pencil" :title="$t('pages.settings.positions.editPosition')" @click="openEdit(item)" /><VDivider class="my-1" /><VListItem prepend-icon="tabler-trash" class="positions-actions-menu__delete" :title="$t('common.messages.deletePosition')" @click="askDelete(item)" /></VList></VMenu></div></template>
        <template #no-data><TableEmptyState icon="tabler-briefcase-off" :title="$t('pages.settings.positions.noPositionsMatchTheCurrentFilters')" :min-height="0" /></template>
      </ResponsiveDataTable>
    </VCard>
  </div>
  <VDialog v-model="dialogOpen" :persistent="isSaving" max-width="760" scrollable content-class="positions-form-dialog"><VCard class="positions-form-dialog__card"><VCardTitle class="positions-form-dialog__header d-flex align-center justify-space-between px-5 py-4"><span>{{ editingPosition ? $t('pages.settings.positions.editPosition') : $t('pages.settings.positions.addPosition') }}</span><VBtn class="positions-form-dialog__close" icon="tabler-x" variant="flat" size="small" :aria-label="$t('common.actions.close')" :disabled="isSaving" @click="dialogOpen = false" /></VCardTitle><VCardText class="pa-5"><PositionForm :form="form" :currencies="currencies" :loading="isSaving" @submit="save" /></VCardText><VCardActions class="positions-form-dialog__footer px-5 py-4"><VBtn class="positions-dialog-cancel" :disabled="isSaving" @click="dialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" variant="flat" :loading="isSaving" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
  <VDialog v-model="deleteDialogOpen" :persistent="isDeleting" max-width="460" content-class="positions-delete-dialog"><VCard class="positions-delete-dialog__card"><VCardText class="positions-delete-dialog__content"><div class="positions-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div><h2>{{ $t('common.messages.confirmDelete') }}</h2><p>{{ $t('pages.settings.positions.deleteConfirmation', { position: deletingPosition?.title }) }}</p><p class="text-caption text-medium-emphasis">{{ $t('common.confirmations.thisActionCannotBeUndone') }}</p></VCardText><VCardActions class="positions-delete-dialog__actions px-5 pb-5"><VBtn class="positions-dialog-cancel" :disabled="isDeleting" @click="deleteDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn class="positions-delete-dialog__delete" :loading="isDeleting" @click="confirmDelete">{{ $t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>
</template>

<style scoped>
.positions-toolbar-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; }
.positions-filter-reset { background: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }
.positions-filter-reset:hover, .positions-filter-reset:focus-visible, .positions-filter-apply, .positions-filter-apply:hover, .positions-filter-apply:focus-visible { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
:deep(.positions-responsive-table .v-data-table__thead > tr > th) { background: rgba(var(--v-theme-primary), .1) !important; color: rgb(var(--v-theme-on-surface)); font-weight: 700; }
.positions-actions-menu__delete { color: rgb(var(--v-theme-error)); }
.positions-delete-dialog__card { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, .18) !important; text-align: center; }
.positions-delete-dialog__content { padding: 2rem 2rem 1rem; text-align: center; }
.positions-delete-dialog__content h2 { margin-block: 0 .75rem; font-size: 1.25rem; }
.positions-delete-dialog__content p { margin-block: 0 .5rem; }
.positions-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin: 0 auto 1.25rem; border-radius: 50%; place-items: center; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); }
.positions-delete-dialog__actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.positions-dialog-cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.positions-dialog-cancel:hover:not(:disabled) { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.positions-delete-dialog__actions .v-btn { min-block-size: 2.85rem; }
.positions-delete-dialog__delete { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
@media (max-width: 599px) { .positions-toolbar-actions { justify-content: space-between; } .positions-toolbar-actions :deep(.v-btn:last-child) { flex: 1; } }
</style>
<style>
body:has(.positions-form-dialog) .v-overlay__scrim, body:has(.positions-delete-dialog) .v-overlay__scrim { backdrop-filter: blur(4px); }
.positions-form-dialog__header, .positions-form-dialog__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.positions-form-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); font-weight: 700; }
.positions-form-dialog__footer { border-block-start: 1px solid rgba(var(--v-border-color), .5); justify-content: flex-end; }
.positions-form-dialog__close { background: rgba(var(--v-theme-error), .13) !important; color: rgb(var(--v-theme-error)) !important; }
.positions-form-dialog__close:hover:not(:disabled) { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
</style>
