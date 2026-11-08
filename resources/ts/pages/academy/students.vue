<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AcademyEnrollmentDialog from '@/components/academy/AcademyEnrollmentDialog.vue'
import AcademyStudentCard from '@/components/academy/AcademyStudentCard.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { AcademyClassesApi, type AcademyClassRecord, type AcademyOption } from '@/api/academyClasses'
import { AcademyEnrollmentsApi, type AcademyEnrollmentRecord, type AcademyEnrollmentStatus } from '@/api/academyEnrollments'
import MasterDataApi from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

const { t } = useI18n()
const classes = ref<AcademyClassRecord[]>([])
const teachers = ref<AcademyOption[]>([])
const enrollments = ref<AcademyEnrollmentRecord[]>([])
const loading = ref(true)
const failed = ref(false)
const classId = ref<number | null>(null)
const status = ref<AcademyEnrollmentStatus | null>(null)
const ending = ref(false)
const teacherId = ref<number | null>(null)
const search = ref('')
const page = ref(1)
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const detailOpen = ref(false)
const editing = ref<AcademyEnrollmentRecord | null>(null)
const cardOpen = ref(false)
const cardEnrollments = ref<AcademyEnrollmentRecord[]>([])
const cardKey = ref<number | null>(null)
const canManage = computed(() => canAccess('manage_academy_classes'))
const statusItems = computed(() => ['active', 'completed', 'withdrawn', 'cancelled'].map(value => ({ title: t(`academy.status.${value}`), value })))

async function load() {
  loading.value = true
  failed.value = false
  try {
    const response = await AcademyEnrollmentsApi.list({
      class_id: classId.value ?? undefined,
      status: status.value ?? undefined,
      ending: ending.value || undefined,
      teacher_id: teacherId.value ?? undefined,
      search: search.value.trim() || undefined,
      page: page.value,
      per_page: 25,
    })
    enrollments.value = response.data
    meta.value = response.meta
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const [classRows, teacherRows] = await Promise.all([AcademyClassesApi.list(), MasterDataApi.list('teachers')])
    classes.value = classRows
    teachers.value = teacherRows
  }
  catch { /* Global notifier owns the API error. */ }
}

function openDetail(row: AcademyEnrollmentRecord) {
  editing.value = row
  detailOpen.value = true
}

async function openCard(row: AcademyEnrollmentRecord) {
  cardKey.value = row.student?.applicant_id ?? row.applicant?.id ?? row.student?.id ?? row.student_id
  cardEnrollments.value = []
  cardOpen.value = true
  try {
    const response = await AcademyEnrollmentsApi.list({
      search: row.student?.student_number ?? row.student?.name ?? row.applicant?.name ?? undefined,
      per_page: 100,
    })
    cardEnrollments.value = response.data.filter(item =>
      (item.student?.applicant_id ?? item.applicant?.id ?? item.student?.id ?? item.student_id) === cardKey.value,
    )
  }
  catch { /* Global notifier owns the API error. */ }
}

const teacherNames = (row: AcademyEnrollmentRecord) => row.class?.teachers.map(teacher => teacher.name).join(', ') || '—'
const statusColor = (value: AcademyEnrollmentStatus) => ({ active: 'success', completed: 'info', withdrawn: 'warning', cancelled: 'error' }[value])

watch([classId, status, ending, teacherId], () => {
  page.value = 1
  void load()
})
watch(page, () => void load())
onMounted(async () => {
  await Promise.all([loadOptions(), load()])
})
</script>

<template>
  <div class="academy-page pa-4 pa-sm-6">
    <ErpPageHeader :title="t('academy.students.title')" :description="t('academy.students.description')" section="academy" />

    <VCard class="mb-4">
      <VCardText class="d-flex flex-wrap ga-3 align-center">
        <VSelect v-model="classId" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" clearable density="compact" hide-details style="min-width: 180px" />
        <VSelect v-model="status" :items="statusItems" :label="t('academy.fields.status')" clearable density="compact" hide-details style="min-width: 160px" />
        <VSelect v-model="teacherId" :items="teachers" item-title="name" item-value="id" :label="t('academy.fields.teacher')" clearable density="compact" hide-details style="min-width: 180px" />
        <VSwitch v-model="ending" :label="t('academy.students.endingSoon')" hide-details density="compact" />
        <VTextField v-model="search" :label="t('common.actions.search')" prepend-inner-icon="tabler-search" density="compact" hide-details clearable style="min-width: 220px" @keyup.enter="load" />
        <VBtn variant="tonal" prepend-icon="tabler-search" @click="load">{{ t('common.actions.search') }}</VBtn>
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <TableLoadingSkeleton v-if="loading" :columns="8" :label="t('common.messages.loading')" />
        <VAlert v-else-if="failed" type="error" variant="tonal">{{ t('academy.students.loadFailed') }}</VAlert>
        <TableEmptyState
          v-else-if="enrollments.length === 0"
          icon="tabler-user-off"
          :title="t('academy.students.emptyTitle')"
          :description="t('academy.students.emptyDescription')"
        />
        <div v-else class="table-scroll">
          <VTable density="compact" class="text-no-wrap">
            <thead>
              <tr>
                <th>{{ t('academy.fields.student') }}</th>
                <th>{{ t('academy.fields.class') }}</th>
                <th>{{ t('academy.fields.teacher') }}</th>
                <th>{{ t('academy.fields.term') }}</th>
                <th>{{ t('academy.fields.status') }}</th>
                <th>{{ t('academy.fields.fee') }}</th>
                <th>{{ t('academy.fields.balance') }}</th>
                <th class="text-end">{{ t('common.fields.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in enrollments" :key="row.id">
                <td>
                  <button class="student-link" type="button" @click="openCard(row)">{{ row.student?.name ?? row.applicant?.name ?? '—' }}</button>
                  <div class="text-caption text-medium-emphasis">{{ row.student?.student_number ?? row.student_number ?? '—' }} · {{ row.student?.contact ?? row.applicant?.contact ?? '—' }}</div>
                </td>
                <td>{{ row.class?.name ?? '—' }}</td>
                <td>{{ teacherNames(row) }}</td>
                <td>{{ formatActiveDate(row.started_on) }} — {{ formatActiveDate(row.ends_on) }}</td>
                <td><VChip size="small" :color="statusColor(row.status)">{{ t(`academy.status.${row.status}`) }}</VChip></td>
                <td>{{ row.line ? formatActiveMoney(row.line.fee, row.line.currency) : '—' }}</td>
                <td>{{ row.line ? formatActiveMoney(row.line.balance, row.line.currency) : '—' }}</td>
                <td class="text-end">
                  <VBtn icon="tabler-eye" size="small" variant="text" :aria-label="t('academy.students.detailTitle')" @click="openDetail(row)" />
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

    <AcademyEnrollmentDialog v-model="detailOpen" :record="editing" :can-manage="canManage" @saved="load" />
    <AcademyStudentCard v-model="cardOpen" :enrollments="cardEnrollments" />
  </div>
</template>

<style scoped>
.table-scroll { overflow-x: auto; }
.student-link { background: none; border: 0; padding: 0; color: rgb(var(--v-theme-primary)); font: inherit; text-align: start; cursor: pointer; }
.student-link:focus-visible { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 2px; }
</style>
