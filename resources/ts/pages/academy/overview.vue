<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { AcademyOverviewApi, type AcademyOverview } from '@/api/academyOverview'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import { formatActiveCount, formatActiveMoney } from '@/utils/localization'

const { t } = useI18n()
const loading = ref(true)
const failed = ref(false)
const overview = ref<AcademyOverview | null>(null)

const kpis = computed(() => overview.value ? [
  { key: 'activeStudents', value: formatActiveCount(overview.value.active_students), icon: 'tabler-users', color: 'primary' },
  { key: 'activeClasses', value: formatActiveCount(overview.value.classes), icon: 'tabler-school', color: 'info' },
  { key: 'seats', value: `${formatActiveCount(overview.value.seats_filled)} / ${formatActiveCount(overview.value.capacity)}`, icon: 'tabler-users-group', color: 'success' },
  { key: 'endingSoon', value: formatActiveCount(overview.value.ending_soon), icon: 'tabler-calendar-event', color: 'warning' },
  { key: 'outstanding', value: formatActiveMoney(overview.value.outstanding), icon: 'tabler-cash', color: 'error' },
  { key: 'completed', value: formatActiveCount(overview.value.completed_this_month), icon: 'tabler-certificate', color: 'tertiary' },
] : [])

async function load() {
  loading.value = true
  failed.value = false
  try {
    overview.value = await AcademyOverviewApi.get()
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="academy-page pa-4 pa-sm-6">
    <ErpPageHeader :title="t('academy.overview.title')" :description="t('academy.overview.description')" section="academy" />

    <VProgressLinear v-if="loading" indeterminate class="mb-6" />
    <VAlert v-else-if="failed" type="error" variant="tonal" class="mb-6">
      {{ t('academy.overview.loadFailed') }}
    </VAlert>

    <VRow v-if="overview">
      <VCol v-for="kpi in kpis" :key="kpi.key" cols="12" sm="6" lg="4">
        <VCard class="h-100">
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-2 text-medium-emphasis">{{ t(`academy.overview.kpis.${kpi.key}`) }}</div>
              <div class="text-h5 font-weight-bold mt-1">{{ kpi.value }}</div>
            </div>
            <VAvatar :color="kpi.color" variant="tonal" rounded>
              <VIcon :icon="kpi.icon" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard v-if="overview" class="mt-6">
      <VCardTitle>{{ t('academy.overview.todayClasses') }}</VCardTitle>
      <VCardText>
        <VAlert v-if="overview.today_classes.length === 0" type="info" variant="tonal">
          {{ t('academy.overview.noTodayClasses') }}
        </VAlert>
        <VTable v-else density="compact">
          <thead>
            <tr>
              <th>{{ t('academy.fields.class') }}</th>
              <th>{{ t('academy.fields.program') }}</th>
              <th>{{ t('academy.fields.time') }}</th>
              <th>{{ t('academy.fields.teachers') }}</th>
              <th>{{ t('academy.fields.seats') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in overview.today_classes" :key="row.id">
              <td>{{ row.name }}</td>
              <td>{{ row.program?.name ?? '—' }}</td>
              <td>{{ row.starts_at ?? '—' }} — {{ row.ends_at ?? '—' }}</td>
              <td>{{ row.teachers.map(teacher => teacher.name).join(', ') || '—' }}</td>
              <td>{{ row.seats_taken ?? 0 }} / {{ row.capacity ?? '∞' }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <VCard v-if="overview" class="mt-6">
      <VCardTitle>{{ t('academy.overview.terms') }}</VCardTitle>
      <VCardText class="text-body-2 text-medium-emphasis">
        {{ t('academy.overview.termHint', { count: formatActiveCount(overview.active_enrollments) }) }}
      </VCardText>
    </VCard>
  </div>
</template>
