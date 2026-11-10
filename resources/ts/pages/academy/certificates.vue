<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AcademyCertificateDialog from '@/components/academy/AcademyCertificateDialog.vue'
import AcademyCertificatePrinter from '@/components/academy/AcademyCertificatePrinter.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { AcademyClassesApi, type AcademyClassRecord } from '@/api/academyClasses'
import { AcademyCertificatesApi, type AcademyCertificateRecord, type AcademyCertificateStatus, type AcademyCertificateTemplate } from '@/api/academyCertificates'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate } from '@/utils/localization'

const { t } = useI18n()
const certificates = ref<AcademyCertificateRecord[]>([]); const classes = ref<AcademyClassRecord[]>([]); const templates = ref<AcademyCertificateTemplate[]>([])
const loading = ref(true); const failed = ref(false); const search = ref(''); const page = ref(1); const filterOpen = ref(false)
const status = ref<AcademyCertificateStatus | null>(null); const classId = ref<number | null>(null)
const filterDraft = ref<{ status: AcademyCertificateStatus | null; classId: number | null }>({ status: null, classId: null })
const meta = ref({ current_page: 1, last_page: 1, total: 0 }); const dialogOpen = ref(false); const dialogMode = ref<'issue' | 'bulk' | 'revoke' | 'template'>('issue')
const selectedCertificate = ref<AcademyCertificateRecord | null>(null); const selectedTemplate = ref<AcademyCertificateTemplate | null>(null); const printerOpen = ref(false); const printCertificate = ref<AcademyCertificateRecord | null>(null)
const canIssue = computed(() => canAccess('issue_academy_certificates')); const firstTemplate = computed(() => templates.value.find(row => row.is_active) ?? templates.value[0] ?? null)
const hasFilters = computed(() => Boolean(status.value || classId.value))
const headers = computed(() => [{ title: t('academy.certificates.number'), key: 'number' }, { title: t('academy.fields.student'), key: 'student' }, { title: t('academy.fields.class'), key: 'class' }, { title: t('academy.fields.completedOn'), key: 'completed_on' }, { title: t('academy.fields.status'), key: 'status' }, { title: t('common.fields.actions'), key: 'actions', sortable: false, align: 'end' }])

async function load() { loading.value = true; failed.value = false; try { const response = await AcademyCertificatesApi.list({ status: status.value ?? undefined, class_id: classId.value ?? undefined, search: search.value.trim() || undefined, page: page.value }); certificates.value = response.data; meta.value = response.meta } catch { failed.value = true } finally { loading.value = false } }
async function loadOptions() { try { const [classRows, templateRows] = await Promise.all([AcademyClassesApi.list(), AcademyCertificatesApi.templates()]); classes.value = classRows; templates.value = templateRows } catch { /* API notifier owns errors. */ } }
function syncDraft() { filterDraft.value = { status: status.value, classId: classId.value } }
function applyFilters() { status.value = filterDraft.value.status; classId.value = filterDraft.value.classId; page.value = 1; filterOpen.value = false; void load() }
function resetFilters() { status.value = null; classId.value = null; syncDraft(); page.value = 1; filterOpen.value = false; void load() }
function openIssue() { dialogMode.value = 'issue'; selectedCertificate.value = null; dialogOpen.value = true }
function openBulk() { dialogMode.value = 'bulk'; selectedCertificate.value = null; dialogOpen.value = true }
function openRevoke(row: AcademyCertificateRecord) { dialogMode.value = 'revoke'; selectedCertificate.value = row; dialogOpen.value = true }
function openTemplate() { if (!firstTemplate.value) return; dialogMode.value = 'template'; selectedTemplate.value = firstTemplate.value; dialogOpen.value = true }
function openPrint(row: AcademyCertificateRecord) { printCertificate.value = row; printerOpen.value = true }
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, () => { page.value = 1; clearTimeout(searchTimer); searchTimer = setTimeout(() => void load(), 250) })
watch(page, () => void load()); watch(filterOpen, open => { if (open) syncDraft() })
onMounted(async () => { await Promise.all([loadOptions(), load()]) })
</script>

<template>
  <div class="academy-page">
    <ErpPageHeader :title="t('academy.certificates.title')" section="academy" />
    <VCard>
      <VCardText><VRow align="center"><VCol cols="12" md="5"><ErpSearchField v-model="search" :placeholder="t('academy.certificates.search')" /></VCol><VCol cols="12" md="7" class="academy-certificates-toolbar d-flex flex-wrap justify-end ga-2">
        <DataTableFilterMenu v-model="filterOpen" :active="hasFilters" :show-reset="false"><ErpField v-model="filterDraft.status" kind="select" :items="[{ title: t('academy.certificates.issued'), value: 'issued' }, { title: t('academy.certificates.revoked'), value: 'revoked' }]" :label="t('academy.fields.status')" :placeholder="t('academy.fields.status')" clearable /><ErpField v-model="filterDraft.classId" kind="select" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" :placeholder="t('academy.fields.class')" clearable /><template #actions><VBtn class="academy-certificates-reset" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn><VBtn color="primary" @click="applyFilters">{{ t('educational.apply') }}</VBtn></template></DataTableFilterMenu>
        <VBtn v-if="canIssue && firstTemplate" variant="tonal" @click="openTemplate"><VIcon icon="tabler-file-settings" class="me-1" />{{ t('academy.certificates.editTemplate') }}</VBtn><VBtn v-if="canIssue" variant="tonal" @click="openBulk"><VIcon icon="tabler-copy" class="me-1" />{{ t('academy.certificates.bulkIssue') }}</VBtn><VBtn v-if="canIssue" color="primary" @click="openIssue"><VIcon icon="tabler-certificate" class="me-1" />{{ t('academy.certificates.issue') }}</VBtn>
      </VCol></VRow></VCardText>
      <VAlert v-if="failed" type="error" variant="tonal" class="ma-4 mt-0">{{ t('academy.certificates.loadFailed') }}</VAlert>
      <ResponsiveDataTable v-else :headers="headers" :items="certificates" :loading="loading" :skeleton-rows="6" item-value="id" class="academy-certificates-table" mobile-cards mobile-expand-all mobile-actions-as-menu mobile-title-key="number" mobile-action-key="actions" :mobile-fields="['student','class','completed_on','status']" :sticky-key-column="false" :sticky-action-column="false" :show-scroll-affordance="false">
        <template #item.number="{ item }"><strong>{{ item.certificate_number }}</strong></template><template #item.student="{ item }"><div><strong>{{ item.student.name || '—' }}</strong><div class="text-caption text-medium-emphasis">{{ item.student.id || '—' }}</div></div></template><template #item.class="{ item }">{{ item.class || '—' }}</template><template #item.completed_on="{ item }">{{ formatActiveDate(item.completed_on) }}</template><template #item.status="{ item }"><VChip size="small" variant="tonal" :color="item.status === 'issued' ? 'success' : 'error'">{{ t(`academy.certificates.${item.status}`) }}</VChip></template><template #item.actions="{ item }"><div class="d-flex justify-end ga-1" @click.stop><VBtn icon="tabler-printer" size="small" variant="tonal" class="academy-certificates-print" :aria-label="t('academy.certificates.print')" @click="openPrint(item)" /><VMenu v-if="canIssue && item.status === 'issued'" location="bottom end"><template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" size="small" variant="tonal" /></template><VList density="compact"><VListItem prepend-icon="tabler-ban" base-color="error" :title="t('academy.certificates.revoke')" @click="openRevoke(item)" /></VList></VMenu></div></template>
        <template #no-data><div class="academy-certificates-empty"><TableEmptyState icon="tabler-certificate" :min-height="0" :title="t('academy.certificates.emptyTitle')" :description="t('academy.certificates.emptyDescription')" /><VBtn v-if="canIssue" color="primary" variant="tonal" @click="openIssue"><VIcon icon="tabler-certificate" class="me-1" />{{ t('academy.certificates.issue') }}</VBtn></div></template>
      </ResponsiveDataTable>
      <div v-if="meta.last_page > 1" class="d-flex justify-end align-center ga-2 pa-4"><VBtn size="small" variant="text" :disabled="meta.current_page <= 1" @click="page--">{{ t('common.actions.previous') }}</VBtn><span class="text-body-2">{{ meta.current_page }} / {{ meta.last_page }}</span><VBtn size="small" variant="text" :disabled="meta.current_page >= meta.last_page" @click="page++">{{ t('common.actions.next') }}</VBtn></div>
    </VCard>
    <AcademyCertificateDialog v-model="dialogOpen" :mode="dialogMode" :certificate="selectedCertificate" :template="selectedTemplate" @saved="load" /><AcademyCertificatePrinter v-model="printerOpen" :certificate="printCertificate" />
  </div>
</template>

<style scoped lang="scss">
.academy-certificates-table :deep(thead th) { background: color-mix(in srgb, rgb(var(--v-theme-primary)) 8%, rgb(var(--v-theme-surface)) 92%); font-size: .75rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }.academy-certificates-print { border-radius: 999px !important; }.academy-certificates-reset { background: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }.academy-certificates-empty { display: flex; flex-direction: column; align-items: center; min-block-size: 17rem; padding: 1.25rem; }@media(max-width:599px){.academy-certificates-toolbar{justify-content:flex-start!important}.academy-certificates-toolbar .v-btn{flex:1}}
</style>
