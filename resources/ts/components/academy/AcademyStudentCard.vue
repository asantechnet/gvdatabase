<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AcademyEnrollmentRecord } from '@/api/academyEnrollments'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

const props = defineProps<{
  modelValue: boolean
  enrollments: AcademyEnrollmentRecord[]
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const open = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})
const student = computed(() => props.enrollments[0]?.student ?? null)
</script>

<template>
  <VDialog v-model="open" max-width="820" scrollable>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ t('academy.students.cardTitle') }}</span>
        <VBtn icon="tabler-x" variant="text" :aria-label="t('common.actions.close')" @click="open = false" />
      </VCardTitle>
      <VCardText>
        <div class="mb-4">
          <div class="text-h6">{{ student?.name ?? enrollments[0]?.applicant?.name ?? '—' }}</div>
          <div class="text-body-2 text-medium-emphasis">
            {{ student?.student_number ?? enrollments[0]?.student_number ?? '—' }}
            · {{ student?.contact ?? enrollments[0]?.applicant?.contact ?? '—' }}
          </div>
        </div>
        <VAlert v-if="enrollments.length === 0" type="info" variant="tonal">
          {{ t('academy.students.emptyCourses') }}
        </VAlert>
        <VRow>
          <VCol v-for="enrollment in enrollments" :key="enrollment.id" cols="12" md="6">
            <VCard variant="outlined" class="h-100">
              <VCardText>
                <div class="d-flex justify-space-between align-start ga-2 mb-2">
                  <div>
                    <div class="text-subtitle-1 font-weight-bold">{{ enrollment.class?.name ?? '—' }}</div>
                    <div class="text-body-2 text-medium-emphasis">
                      {{ enrollment.class?.program?.name ?? '—' }} · {{ enrollment.duration?.name ?? '—' }}
                    </div>
                  </div>
                  <VChip size="small" :color="enrollment.status === 'active' ? 'success' : 'secondary'">
                    {{ t(`academy.status.${enrollment.status}`) }}
                  </VChip>
                </div>
                <div class="text-body-2 mb-1">
                  {{ formatActiveDate(enrollment.started_on) }} — {{ formatActiveDate(enrollment.ends_on) }}
                </div>
                <div class="text-body-2 mb-2">{{ enrollment.contract?.contract_number ?? '—' }}</div>
                <div v-if="enrollment.line" class="d-flex justify-space-between text-body-2">
                  <span>{{ t('academy.fields.balance') }}</span>
                  <strong>{{ formatActiveMoney(enrollment.line.balance, enrollment.line.currency) }}</strong>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </VDialog>
</template>
