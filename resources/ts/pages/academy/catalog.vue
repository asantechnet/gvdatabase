<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'
import { printDocument } from '@/utils/printDocument'

const { t } = useI18n()
const toast = useToast()
const rows = ref<MasterDataRecord[]>([])
const loading = ref(true)
const failed = ref(false)
const search = ref('')
const canManage = computed(() => canAccess('manage_academy_classes'))
const dialogOpen = ref(false)
const editing = ref<MasterDataRecord | null>(null)
const deleteOpen = ref(false)
const deleteTarget = ref<MasterDataRecord | null>(null)
const form = ref<Partial<MasterDataRecord>>({ name: '', description: '', is_active: true })
const headers = computed(() => [
  { title: t('common.fields.name'), key: 'name' },
  { title: t('common.fields.details'), key: 'description' },
  { title: t('common.fields.status'), key: 'status' },
  { title: t('common.fields.actions'), key: 'actions', sortable: false, align: 'end' },
])
const filteredRows = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase()
  return rows.value.filter(row => !needle || [row.name, row.description].some(value => String(value ?? '').toLocaleLowerCase().includes(needle)))
})
const printRows = computed(() => filteredRows.value.map(row => [row.name, row.description || '—', t(row.is_active ? 'common.statuses.active' : 'common.statuses.inactive')]))

async function load() {
  loading.value = true; failed.value = false
  try { rows.value = await MasterDataApi.list('programs') }
  catch { failed.value = true }
  finally { loading.value = false }
}
function open(row?: MasterDataRecord) {
  editing.value = row ?? null
  form.value = row ? { name: row.name, description: row.description, is_active: row.is_active } : { name: '', description: '', is_active: true }
  dialogOpen.value = true
}
async function save() {
  if (!form.value.name?.trim()) return
  try {
    if (editing.value) await MasterDataApi.update('programs', editing.value.id, form.value)
    else await MasterDataApi.create('programs', form.value)
    dialogOpen.value = false; toast.success(t('academy.catalog.saved')); await load()
  }
  catch { /* Global API notifier owns errors. */ }
}
function requestDelete(row: MasterDataRecord) { deleteTarget.value = row; deleteOpen.value = true }
async function confirmDelete(confirmed: boolean) {
  if (!confirmed || !deleteTarget.value) return
  try { await MasterDataApi.remove('programs', deleteTarget.value.id); toast.success(t('academy.catalog.removed')); await load() }
  catch { /* Global API notifier owns errors. */ }
  finally { deleteTarget.value = null }
}
async function printTable() { await printDocument({ bodyClass: 'printing-table-academy-programs', pageStyle: 'size: A4 portrait; margin: 12mm;' }) }
onMounted(load)
</script>

<template>
  <div class="academy-page">
    <ErpPageHeader :title="t('academy.catalog.title')" section="academy" />
    <VCard>
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="t('academy.catalog.title')" /></VCol>
          <VCol cols="12" md="6" lg="7" class="d-flex justify-end ga-2"><VBtn variant="tonal" class="programs-toolbar__print" :disabled="loading || !filteredRows.length" :aria-label="t('common.actions.print')" @click="printTable"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('common.actions.print') }}</VTooltip></VBtn><VBtn v-if="canManage" color="primary" :disabled="loading" @click="open()"><VIcon icon="tabler-plus" class="me-1" />{{ t('academy.catalog.add') }}</VBtn></VCol>
        </VRow>
      </VCardText>
      <VAlert v-if="failed" type="error" variant="tonal" class="ma-4 mt-0">{{ t('academy.catalog.loadFailed') }}</VAlert>
      <ResponsiveDataTable v-else :headers="headers" :items="filteredRows" :loading="loading" :skeleton-rows="5" item-value="id" class="programs-table" mobile-cards mobile-expand-all mobile-actions-as-menu mobile-title-key="name" mobile-action-key="actions" :mobile-fields="['description', 'status']" :sticky-key-column="false" :sticky-action-column="false" :show-scroll-affordance="false">
        <template #item.name="{ item }"><strong>{{ item.name }}</strong></template>
        <template #item.description="{ item }">{{ item.description || '—' }}</template>
        <template #item.status="{ item }"><VChip size="small" variant="tonal" :color="item.is_active ? 'success' : 'secondary'">{{ t(item.is_active ? 'common.statuses.active' : 'common.statuses.inactive') }}</VChip></template>
        <template #item.actions="{ item }"><div class="d-flex justify-end" @click.stop><VMenu location="bottom end"><template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" /></template><VList density="compact" min-width="180"><VListItem prepend-icon="tabler-pencil" :title="t('common.actions.edit')" @click="open(item)" /><VListItem prepend-icon="tabler-trash" class="programs-delete" :title="t('common.actions.delete')" @click="requestDelete(item)" /></VList></VMenu></div></template>
        <template #no-data><div class="programs-empty"><TableEmptyState icon="tabler-books" :min-height="0" :title="t('academy.catalog.emptyTitle')" :description="t('academy.catalog.emptyDescription')" /><VBtn v-if="canManage" color="primary" variant="tonal" @click="open()"><VIcon icon="tabler-plus" class="me-1" />{{ t('academy.catalog.add') }}</VBtn></div></template>
      </ResponsiveDataTable>
    </VCard>
    <PrintableListReport :title="t('academy.catalog.title')" :columns="headers.slice(0, -1).map(header => header.title)" :rows="printRows" />

    <VDialog v-model="dialogOpen" max-width="720" content-class="program-dialog" persistent>
      <VCard class="program-dialog-card academy-program-dialog-card"><VForm class="app-form" @submit.prevent="save">
        <VCardTitle class="program-dialog-header px-5 py-4"><div class="text-h5 font-weight-bold">{{ t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: t('academy.catalog.programs') }) }}</div><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="program-dialog-close" @click="dialogOpen = false" /></VCardTitle>
        <VCardText class="pa-5 pa-sm-6"><VRow><VCol cols="12" md="6"><ErpField v-model="form.name" :label="t('common.fields.name')" :placeholder="t('common.fields.name')" required /></VCol><VCol cols="12" md="6"><ErpField v-model="form.description" :label="t('common.fields.description')" :placeholder="t('common.fields.description')" /></VCol><VCol cols="12"><VSwitch v-model="form.is_active" color="primary" :label="t('common.statuses.active')" /></VCol></VRow></VCardText>
        <VCardActions class="program-dialog-footer px-5 py-4"><VSpacer /><VBtn class="program-cancel" @click="dialogOpen = false">{{ t('common.actions.cancel') }}</VBtn><VBtn color="primary" variant="flat" class="program-save" type="submit" :disabled="!form.name?.trim()">{{ t('common.actions.save') }}</VBtn></VCardActions>
      </VForm></VCard>
    </VDialog>
    <ConfirmDialog v-model:is-dialog-visible="deleteOpen" tone="error" icon="tabler-trash" :confirmation-question="t('common.messages.confirmDelete')" :confirmation-message="t('academy.catalog.removeConfirm', { name: deleteTarget?.name ?? '' })" :confirm-text="t('common.actions.delete')" :cancel-text="t('common.actions.cancel')" :confirm-title="t('common.actions.delete')" confirm-msg="" :cancel-title="t('common.actions.cancel')" cancel-msg="" @confirm="confirmDelete" />
  </div>
</template>

<style scoped lang="scss">
.programs-table :deep(thead th) { background: color-mix(in srgb, rgb(var(--v-theme-primary)) 8%, rgb(var(--v-theme-surface)) 92%) !important; color: rgb(var(--v-theme-on-surface)) !important; font-size: .75rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }.programs-toolbar__print { min-inline-size:2.25rem!important;inline-size:2.25rem!important;padding-inline:0!important;border-radius:999px!important;font-size:.75rem!important; }
.programs-delete { color: rgb(var(--v-theme-error)) !important; } .programs-delete:hover { background: rgb(var(--v-theme-error)) !important; color: white !important; }
.programs-empty { display: flex; flex-direction: column; align-items: center; min-block-size: 17rem; padding: 1.25rem; }
.program-dialog-header, .program-dialog-footer { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.program-dialog-header { border-block-end: 1px solid rgba(var(--v-border-color), .45); } .program-dialog-footer { border-block-start: 1px solid rgba(var(--v-border-color), .45); }
.program-dialog-close { color: rgb(var(--v-theme-error)) !important; border: 1px solid rgba(var(--v-theme-error), .35); }
.program-cancel { background: rgba(var(--v-theme-secondary), .12) !important; color: rgb(var(--v-theme-secondary)) !important; } .program-cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: white !important; }.program-save{color:rgb(var(--v-theme-on-primary))!important}
:global(.v-overlay:has(.academy-program-dialog-card) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
</style>
