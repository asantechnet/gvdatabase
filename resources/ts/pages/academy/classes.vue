<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import AcademyClassDialog from '@/components/academy/AcademyClassDialog.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { AcademyClassesApi, type AcademyClassRecord, type AcademyOption } from '@/api/academyClasses'
import { AcademyTeachersApi, type AcademyTeacher } from '@/api/academyTeachers'
import MasterDataApi from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

const { t } = useI18n()
const toast = useToast()
const classes = ref<AcademyClassRecord[]>([])
const programs = ref<AcademyOption[]>([])
const teachers = ref<AcademyTeacher[]>([])
const loading = ref(true)
const failed = ref(false)
const dialogOpen = ref(false)
const editing = ref<AcademyClassRecord | null>(null)
const rosterOpen = ref(false)
const rosterLoading = ref(false)
const roster = ref<NonNullable<AcademyClassRecord['roster']>>([])
const selectedClass = ref<AcademyClassRecord | null>(null)
const canManage = computed(() => canAccess('manage_academy_classes'))
const userData = useCookie<{ employee?: { id?: number } | null } | null>('userData')
const ownEmployeeId = computed(() => userData.value?.employee?.id ?? null)
const canComplete = (row: AcademyClassRecord) => canManage.value
  || (ownEmployeeId.value !== null && row.teachers.some(teacher => teacher.id === ownEmployeeId.value))
const weekdays = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

async function load() {
  loading.value = true
  failed.value = false
  try {
    const [classRows, programRows, teacherRows] = await Promise.all([
      AcademyClassesApi.list(),
      canManage.value ? MasterDataApi.list('programs') : Promise.resolve<AcademyOption[]>([]),
      canManage.value ? AcademyTeachersApi.list() : Promise.resolve<AcademyTeacher[]>([]),
    ])
    classes.value = classRows
    programs.value = programRows
    teachers.value = teacherRows
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  dialogOpen.value = true
}

function openEdit(row: AcademyClassRecord) {
  editing.value = row
  dialogOpen.value = true
}

async function remove(row: AcademyClassRecord) {
  if (!confirm(t('academy.classes.removeConfirm', { name: row.name })))
    return
  try {
    await AcademyClassesApi.remove(row.id)
    toast.success(t('academy.classes.removed'))
    await load()
  }
  catch { /* Global notifier owns the API error. */ }
}

async function complete(row: AcademyClassRecord) {
  if (!confirm(t('academy.classes.completeConfirm', { name: row.name })))
    return
  try {
    await AcademyClassesApi.complete(row.id)
    toast.success(t('academy.classes.completed'))
    await load()
  }
  catch { /* Global notifier owns the API error. */ }
}

async function viewRoster(row: AcademyClassRecord) {
  selectedClass.value = row
  roster.value = []
  rosterOpen.value = true
  rosterLoading.value = true
  try {
    const detail = await AcademyClassesApi.get(row.id)
    roster.value = detail.roster ?? []
  }
  catch { /* Global notifier owns the API error. */ }
  finally {
    rosterLoading.value = false
  }
}

const seats = (row: AcademyClassRecord) => `${row.seats_taken ?? 0} / ${row.capacity ?? '∞'}`
const schedule = (row: AcademyClassRecord) => row.weekdays.map(day => weekdays[day]).filter(Boolean).join(', ') || '—'

onMounted(load)
</script>

<template>
  <div class="academy-page pa-4 pa-sm-6">
    <ErpPageHeader :title="t('academy.classes.title')" :description="t('academy.classes.description')" section="academy">
      <template #actions>
        <VBtn v-if="canManage" color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ t('academy.classes.create') }}</VBtn>
      </template>
    </ErpPageHeader>

    <VCard>
      <VCardText>
        <TableLoadingSkeleton v-if="loading" :columns="7" :label="t('common.messages.loading')" />
        <VAlert v-else-if="failed" type="error" variant="tonal">{{ t('academy.classes.loadFailed') }}</VAlert>
        <TableEmptyState v-else-if="classes.length === 0" icon="tabler-school" :title="t('academy.classes.emptyTitle')" :description="t('academy.classes.emptyDescription')" />
        <div v-else class="table-scroll">
          <VTable density="compact" class="text-no-wrap">
            <thead>
              <tr>
                <th>{{ t('academy.fields.class') }}</th>
                <th>{{ t('academy.fields.program') }}</th>
                <th>{{ t('academy.fields.schedule') }}</th>
                <th>{{ t('academy.fields.term') }}</th>
                <th>{{ t('academy.fields.seats') }}</th>
                <th>{{ t('academy.fields.teachers') }}</th>
                <th>{{ t('academy.fields.status') }}</th>
                <th class="text-end">{{ t('common.fields.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in classes" :key="row.id">
                <td>
                  <div class="font-weight-medium">{{ row.name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatActiveMoney(row.fee) }}</div>
                </td>
                <td>{{ row.program?.name ?? '—' }}</td>
                <td>
                  <div>{{ schedule(row) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ row.starts_at ?? '—' }} — {{ row.ends_at ?? '—' }}</div>
                </td>
                <td>
                  <div>{{ formatActiveDate(row.starts_on) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatActiveDate(row.ends_on) }}</div>
                </td>
                <td>{{ seats(row) }}</td>
                <td>{{ row.teachers.map(teacher => teacher.name).join(', ') || '—' }}</td>
                <td>
                  <VChip :color="row.completed_at ? 'info' : row.is_active ? 'success' : 'secondary'" size="small">
                    {{ t(row.completed_at ? 'academy.status.completed' : row.is_active ? 'common.statuses.active' : 'common.statuses.inactive') }}
                  </VChip>
                </td>
                <td class="text-end">
                  <VBtn icon="tabler-list-details" size="small" variant="text" :aria-label="t('academy.classes.roster')" @click="viewRoster(row)" />
                  <VBtn v-if="canComplete(row) && !row.completed_at" icon="tabler-check" size="small" variant="text" color="success" :aria-label="t('academy.classes.complete')" @click="complete(row)" />
                  <VBtn v-if="canManage" icon="tabler-pencil" size="small" variant="text" :aria-label="t('common.actions.edit')" @click="openEdit(row)" />
                  <VBtn v-if="canManage" icon="tabler-trash" size="small" variant="text" color="error" :aria-label="t('common.actions.delete')" @click="remove(row)" />
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>
    </VCard>

    <AcademyClassDialog v-model="dialogOpen" :record="editing" :programs="programs" :teachers="teachers" @saved="load" />

    <VDialog v-model="rosterOpen" max-width="960" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>{{ t('academy.classes.rosterTitle', { name: selectedClass?.name ?? '' }) }}</span>
          <VBtn icon="tabler-x" variant="text" :aria-label="t('common.actions.close')" @click="rosterOpen = false" />
        </VCardTitle>
        <VCardText>
          <TableLoadingSkeleton v-if="rosterLoading" :columns="6" :label="t('common.messages.loading')" />
          <VAlert v-else-if="roster.length === 0" type="info" variant="tonal">{{ t('academy.classes.emptyRoster') }}</VAlert>
          <VTable v-else density="compact">
            <thead>
              <tr>
                <th>{{ t('academy.fields.student') }}</th>
                <th>{{ t('academy.fields.contact') }}</th>
                <th>{{ t('academy.fields.term') }}</th>
                <th>{{ t('academy.fields.status') }}</th>
                <th>{{ t('academy.fields.balance') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in roster" :key="row.id">
                <td>{{ row.student?.name ?? row.applicant?.name ?? '—' }}<div class="text-caption">{{ row.student?.student_number ?? row.student_number ?? '—' }}</div></td>
                <td>{{ row.student?.contact ?? row.applicant?.contact ?? '—' }}</td>
                <td>{{ formatActiveDate(row.started_on) }} — {{ formatActiveDate(row.ends_on) }}</td>
                <td><VChip size="small" :color="row.status === 'active' ? 'success' : 'secondary'">{{ t(`academy.status.${row.status}`) }}</VChip></td>
                <td>{{ row.line ? formatActiveMoney(row.line.balance, row.line.currency) : '—' }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.table-scroll { overflow-x: auto; }
</style>
