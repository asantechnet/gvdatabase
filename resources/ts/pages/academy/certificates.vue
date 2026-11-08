<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import AcademyCertificateDialog from '@/components/academy/AcademyCertificateDialog.vue'
import AcademyCertificatePrinter from '@/components/academy/AcademyCertificatePrinter.vue'
import { AcademyClassesApi, type AcademyClassRecord } from '@/api/academyClasses'
import { AcademyCertificatesApi, type AcademyCertificateRecord, type AcademyCertificateStatus, type AcademyCertificateTemplate } from '@/api/academyCertificates'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate } from '@/utils/localization'

const { t } = useI18n()
const certificates = ref<AcademyCertificateRecord[]>([])
const classes = ref<AcademyClassRecord[]>([])
const templates = ref<AcademyCertificateTemplate[]>([])
const loading = ref(true)
const failed = ref(false)
const status = ref<AcademyCertificateStatus | null>(null)
const classId = ref<number | null>(null)
const search = ref('')
const page = ref(1)
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const dialogOpen = ref(false)
const dialogMode = ref<'issue' | 'bulk' | 'revoke' | 'template'>('issue')
const selectedCertificate = ref<AcademyCertificateRecord | null>(null)
const selectedTemplate = ref<AcademyCertificateTemplate | null>(null)
const printerOpen = ref(false)
const printCertificate = ref<AcademyCertificateRecord | null>(null)
const canIssue = computed(() => canAccess('issue_academy_certificates'))
const firstTemplate = computed(() => templates.value.find(template => template.is_active) ?? templates.value[0] ?? null)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const response = await AcademyCertificatesApi.list({
      status: status.value ?? undefined,
      class_id: classId.value ?? undefined,
      search: search.value.trim() || undefined,
      page: page.value,
    })
    certificates.value = response.data
    meta.value = response.meta
  }
  catch { failed.value = true }
  finally { loading.value = false }
}

async function loadOptions() {
  try {
    const [classRows, templateRows] = await Promise.all([AcademyClassesApi.list(), AcademyCertificatesApi.templates()])
    classes.value = classRows
    templates.value = templateRows
  }
  catch { /* Global notifier owns the API error. */ }
}

function openIssue() {
  dialogMode.value = 'issue'
  selectedCertificate.value = null
  dialogOpen.value = true
}

function openBulk() {
  dialogMode.value = 'bulk'
  selectedCertificate.value = null
  dialogOpen.value = true
}

function openRevoke(row: AcademyCertificateRecord) {
  dialogMode.value = 'revoke'
  selectedCertificate.value = row
  dialogOpen.value = true
}

function openTemplate() {
  if (!firstTemplate.value) return
  dialogMode.value = 'template'
  selectedTemplate.value = firstTemplate.value
  dialogOpen.value = true
}

function openPrint(row: AcademyCertificateRecord) {
  printCertificate.value = row
  printerOpen.value = true
}

watch([status, classId], () => { page.value = 1; void load() })
watch(page, () => void load())
onMounted(async () => { await Promise.all([loadOptions(), load()]) })
</script>

<template>
  <div class="academy-page pa-4 pa-sm-6">
    <ErpPageHeader :title="t('academy.certificates.title')" :description="t('academy.certificates.description')" section="academy">
      <template #actions>
        <VBtn v-if="canIssue && firstTemplate" variant="tonal" prepend-icon="tabler-file-settings" @click="openTemplate">{{ t('academy.certificates.editTemplate') }}</VBtn>
        <VBtn v-if="canIssue" variant="tonal" prepend-icon="tabler-copy" @click="openBulk">{{ t('academy.certificates.bulkIssue') }}</VBtn>
        <VBtn v-if="canIssue" color="primary" prepend-icon="tabler-certificate" @click="openIssue">{{ t('academy.certificates.issue') }}</VBtn>
      </template>
    </ErpPageHeader>

    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap ga-3 align-center">
        <VSelect v-model="status" :items="[{ title: t('academy.certificates.issued'), value: 'issued' }, { title: t('academy.certificates.revoked'), value: 'revoked' }]" :label="t('academy.fields.status')" clearable density="compact" hide-details style="min-width: 170px" />
        <VSelect v-model="classId" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" clearable density="compact" hide-details style="min-width: 200px" />
        <VTextField v-model="search" :label="t('academy.certificates.search')" prepend-inner-icon="tabler-search" density="compact" hide-details clearable style="min-width: 240px" @keyup.enter="load" />
        <VBtn variant="tonal" prepend-icon="tabler-search" @click="load">{{ t('common.actions.search') }}</VBtn>
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <TableLoadingSkeleton v-if="loading" :columns="6" :label="t('common.messages.loading')" />
        <VAlert v-else-if="failed" type="error" variant="tonal">{{ t('academy.certificates.loadFailed') }}</VAlert>
        <TableEmptyState v-else-if="certificates.length === 0"          icon="tabler-certificate" :title="t('academy.certificates.emptyTitle')" :description="t('academy.certificates.emptyDescription')" />
        <div v-else class="table-scroll">
          <VTable density="compact">
            <thead><tr><th>{{ t('academy.certificates.number') }}</th><th>{{ t('academy.fields.student') }}</th><th>{{ t('academy.fields.class') }}</th><th>{{ t('academy.fields.completedOn') }}</th><th>{{ t('academy.fields.status') }}</th><th class="text-end">{{ t('common.fields.actions') }}</th></tr></thead>
            <tbody>
              <tr v-for="row in certificates" :key="row.id">
                <td class="text-no-wrap">{{ row.certificate_number }}</td>
                <td><strong>{{ row.student.name }}</strong><div class="text-caption">{{ row.student.id }}</div></td>
                <td>{{ row.class ?? '—' }}</td>
                <td>{{ formatActiveDate(row.completed_on) }}</td>
                <td><VChip size="small" :color="row.status === 'issued' ? 'success' : 'error'">{{ t(`academy.certificates.${row.status}`) }}</VChip></td>
                <td class="text-end text-no-wrap">
                  <VBtn icon="tabler-printer" size="small" variant="text" :aria-label="t('academy.certificates.print')" @click="openPrint(row)" />
                  <VBtn v-if="canIssue && row.status === 'issued'" icon="tabler-ban" size="small" variant="text" color="error" :aria-label="t('academy.certificates.revoke')" @click="openRevoke(row)" />
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
        <div v-if="meta.last_page > 1" class="d-flex justify-end align-center ga-2 mt-4">
          <VBtn size="small" variant="text" :disabled="meta.current_page <= 1" @click="page--">{{ t('common.actions.previous') }}</VBtn>
          <span class="text-body-2">{{ meta.current_page }} / {{ meta.last_page }}</span>
          <VBtn size="small" variant="text" :disabled="meta.current_page >= meta.last_page" @click="page++">{{ t('common.actions.next') }}</VBtn>
        </div>
      </VCardText>
    </VCard>

    <AcademyCertificateDialog v-model="dialogOpen" :mode="dialogMode" :certificate="selectedCertificate" :template="selectedTemplate" @saved="load" />
    <AcademyCertificatePrinter v-model="printerOpen" :certificate="printCertificate" />
  </div>
</template>

<style scoped>
.table-scroll { overflow-x: auto; }
</style>
