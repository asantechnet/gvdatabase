<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AttendanceApi, { type Attendance, type DailyReport } from '@/api/attendance'

const loading = ref(false)
const records = ref<Attendance[]>([])
const report = ref<DailyReport | null>(null)
const reportDialog = ref(false)
const month = ref(new Date().toISOString().slice(0, 7))
const headers = [{ title: 'Date', key: 'date' }, { title: 'Status', key: 'status' }, { title: 'Check in', key: 'check_in_time' }, { title: 'Check out', key: 'check_out_time' }, { title: 'Worked', key: 'worked' }, { title: 'Report', key: 'report', sortable: false }]
const range = computed(() => ({ start_date: `${month.value}-01`, end_date: new Date(Number(month.value.slice(0, 4)), Number(month.value.slice(5)), 0).toISOString().slice(0, 10) }))
const duration = (minutes: number | null) => minutes === null ? '—' : `${Math.floor(minutes / 60)}h ${minutes % 60}m`
async function load() {
  loading.value = true; try { records.value = await AttendanceApi.getMine(range.value) }
  finally { loading.value = false }
}
async function viewReport(item: Attendance) { report.value = await AttendanceApi.getDailyReport(item.id); reportDialog.value = true }
onMounted(load)
</script>

<template>
  <VCard>
    <VCardItem
      title="My work history"
      subtitle="Your attendance and daily reports in one place."
    >
      <template #append>
        <VTextField
          data-account-guide="work-history-filter"
          v-model="month"
          type="month"
          density="compact"
          hide-details
          style="inline-size: 170px"
          @update:model-value="load"
        />
      </template>
    </VCardItem>
    <VDataTable
      data-account-guide="work-history-table"
      :headers="headers"
      :items="records"
      :loading="loading"
    >
      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="item.status === 'present' ? 'success' : 'warning'"
        >
          {{ item.status }}
        </VChip>
      </template>
      <template #item.worked="{ item }">
        {{ duration(item.net_worked_minutes) }}
      </template>
      <template #item.report="{ item }">
        <VBtn
          v-if="item.daily_report_exists"
          size="small"
          variant="tonal"
          prepend-icon="tabler-notebook"
          @click="viewReport(item)"
        >
          View report
        </VBtn><span
          v-else
          class="text-disabled"
        >No report</span>
      </template>
    </VDataTable>
  </VCard>
  <VDialog
    v-model="reportDialog"
    max-width="720"
  >
    <VCard v-if="report">
      <VCardTitle>Daily report · {{ report.reportDate }}</VCardTitle><VCardText>
        <div class="text-subtitle-2 mb-1">
          Summary
        </div><p>{{ report.summary }}</p><template
          v-for="section in [{ title: 'Achievements', value: report.achievements }, { title: 'Challenges', value: report.challenges }, { title: 'Next-day plan', value: report.nextDayPlan }, { title: 'Support needed', value: report.supportNeeded }]"
          :key="section.title"
        >
          <div
            v-if="section.value"
            class="text-subtitle-2 mb-1"
          >
            {{ section.title }}
          </div><p v-if="section.value">
            {{ section.value }}
          </p>
        </template>
      </VCardText>
    </VCard>
  </VDialog>
</template>
