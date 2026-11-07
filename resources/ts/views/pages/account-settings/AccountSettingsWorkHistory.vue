<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import SettingApi from '@/api/setting'
import AttendanceApi, { type Attendance, type DailyReport } from '@/api/attendance'
import ErpMonthPicker from '@/components/erp/ErpMonthPicker.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { calendarDate } from '@/utils/calendarDate'

const loading = ref(false)
const records = ref<Attendance[]>([])
const report = ref<DailyReport | null>(null)
const reportDialog = ref(false)
const month = ref(new Date().toISOString().slice(0, 7))
const headers = [
  { title: 'Date', key: 'date' },
  { title: 'Status', key: 'status' },
  { title: 'Check in', key: 'check_in_time' },
  { title: 'Check out', key: 'check_out_time' },
  { title: 'Worked', key: 'worked' },
  { title: 'Report', key: 'report', sortable: false },
]
const range = computed(() => ({
  start_date: `${month.value}-01`,
  end_date: new Date(Date.UTC(Number(month.value.slice(0, 4)), Number(month.value.slice(5)), 0)).toISOString().slice(0, 10),
}))
const duration = (minutes: number | null) => minutes === null ? '—' : `${Math.floor(minutes / 60)}h ${minutes % 60}m`

async function load() {
  loading.value = true
  try { records.value = await AttendanceApi.getMine(range.value) }
  finally { loading.value = false }
}

async function viewReport(item: Attendance) {
  report.value = await AttendanceApi.getOwnDailyReport(item.id)
  reportDialog.value = true
}

onMounted(async () => {
  const setting = (await SettingApi.getCompanyProfile()).data

  month.value = calendarDate(setting?.timezone || 'UTC').slice(0, 7)
  await load()
})
</script>

<template>
  <VCard>
    <VCardItem title="My work history" subtitle="Your attendance and daily reports in one place.">
      <template #append>
        <ErpMonthPicker v-model="month" data-account-guide="work-history-filter" label="Month" style="inline-size: 11rem" @update:model-value="load" />
      </template>
    </VCardItem>

    <ResponsiveDataTable
      data-account-guide="work-history-table"
      :headers="headers"
      :items="records"
      :loading="loading"
      item-value="id"
      mobile-cards
      mobile-expand-all
      mobile-title-key="date"
      mobile-action-key="report"
      :mobile-fields="['status', 'check_in_time', 'check_out_time', 'worked']"
      :skeleton-rows="5"
      :show-scroll-affordance="false"
      class="work-history-table"
    >
      <template #item.status="{ item }">
        <VChip size="small" :color="item.status === 'present' ? 'success' : 'warning'">{{ item.status }}</VChip>
      </template>
      <template #item.worked="{ item }">{{ duration(item.net_worked_minutes) }}</template>
      <template #item.report="{ item }">
        <VBtn v-if="item.daily_report_exists" size="small" variant="tonal" prepend-icon="tabler-notebook" @click="viewReport(item)">View report</VBtn>
        <span v-else class="text-disabled">No report</span>
      </template>
      <template #no-data>
        <TableEmptyState
          icon="tabler-calendar-off"
          :title="'No work history for this month'"
          :description="'Choose another month to view your attendance and daily reports.'"
          :min-height="260"
        />
      </template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog v-model="reportDialog" max-width="720">
    <VCard v-if="report">
      <VCardTitle>Daily report · {{ report.reportDate }}</VCardTitle>
      <VCardText>
        <div class="text-subtitle-2 mb-1">Summary</div><p>{{ report.summary }}</p>
        <template v-for="section in [{ title: 'Achievements', value: report.achievements }, { title: 'Challenges', value: report.challenges }, { title: 'Next-day plan', value: report.nextDayPlan }, { title: 'Support needed', value: report.supportNeeded }]" :key="section.title">
          <div v-if="section.value" class="text-subtitle-2 mb-1">{{ section.title }}</div><p v-if="section.value">{{ section.value }}</p>
        </template>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.work-history-table :deep(.v-table__wrapper) { scrollbar-width: none; }
.work-history-table :deep(.v-table__wrapper::-webkit-scrollbar) { display: none; }
</style>
