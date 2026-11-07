<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ServiceCaseApi, { type ServiceCase, type ServiceCaseHistory, type ServiceCaseTask } from '@/api/serviceCases'
import type { ApplicantStage } from '@/api/applicants'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate, translateApplicantStage, translateBackendValue } from '@/utils/localization'

const route = useRoute()
const toast = useToast()
const { t, n } = useI18n()

const line = ref<ServiceCase | null>(null)
const loading = ref(false)
const moving = ref(false)
const selected = ref<number | string | null>(null)
const note = ref('')
const terminalDialog = ref(false)
const pendingTerminal = ref<ApplicantStage | null>(null)

const lineId = computed(() => Number(route.params.line))
const canMoveStage = computed(() => canAccess('change_applicant_stage'))
const canViewFinance = computed(() => canAccess('view_applicant_financials'))
const serviceLabel = computed(() => translateBackendValue(line.value?.built_in_service ?? ''))
const pageTitle = computed(() => {
  if (!line.value)
    return t('educational.caseUi.serviceCases')

  return [line.value.applicant?.name, line.value.contract_number].filter(Boolean).join(' · ')
})

// The server returns only this line's legal targets. A terminal current stage
// has none; a line with no stage or an archived stage gets the service list.
const transitions = computed(() => line.value?.allowed_next_stages ?? [])
const openTransitions = computed(() => transitions.value.filter(stage => !stage.is_terminal))
const terminalTransitions = computed(() => transitions.value.filter(stage => stage.is_terminal))
const transitionItems = computed<Array<Record<string, unknown>>>(() => {
  const items: Array<Record<string, unknown>> = [...openTransitions.value]

  if (openTransitions.value.length && terminalTransitions.value.length)
    items.push({ type: 'divider' })

  if (terminalTransitions.value.length)
    items.push({ type: 'subheader', title: t('educational.caseUi.finalOutcomes') })

  return [...items, ...terminalTransitions.value]
})
const transitionItemProps = (item: Record<string, unknown>) => item.is_terminal === true
  ? { prependIcon: 'tabler-flag', subtitle: t('educational.caseUi.terminalStageOption') }
  : {}
const selectedStage = computed(() => transitions.value.find(stage => stage.id === Number(selected.value)) ?? null)
const caseClosed = computed(() => line.value?.current_stage?.is_terminal === true)
const history = computed(() => line.value?.stage_history ?? [])
const tasks = computed(() => line.value?.tasks ?? [])
const incomes = computed(() => line.value?.incomes ?? [])
const movingStage = computed(() => line.value?.current_stage ? translateApplicantStage(line.value.current_stage) : t('educational.currentStage'))

const money = (value: number | string | null | undefined) => n(Number(value || 0), 'decimal')
const formatDate = (value?: string | null) => value ? formatLocalizedDate(value, 'short') : ''
const formatDateTime = (value?: string | null) => value ? formatLocalizedDate(value, 'long') : t('common.messages.notAvailable')

const historySubtitle = (row: ServiceCaseHistory) => [
  formatDateTime(row.changed_at),
  row.changer?.name ?? '',
  row.note?.trim() ?? '',
].filter(Boolean).join(' · ')

const taskSubtitle = (task: ServiceCaseTask) => t('educational.caseUi.taskSummary', {
  progress: n(task.progress),
  priority: task.priority ? translateBackendValue(task.priority) : t('common.messages.notAvailable'),
  date: task.due_date ? formatDate(task.due_date) : t('common.messages.notAvailable'),
})

async function load() {
  if (!Number.isSafeInteger(lineId.value))
    return

  loading.value = true
  try {
    line.value = await ServiceCaseApi.get(lineId.value)
  }
  catch {
    // The shared API notifier explains a 403/404; the page renders its empty state.
    line.value = null
  }
  finally {
    loading.value = false
  }
}

async function move() {
  const target = selectedStage.value

  if (!target)
    return

  // Closing a case is irreversible and needs a written reason; confirm it with
  // the consequence stated, exactly like the applicant-level exit dialog.
  if (target.is_terminal) {
    if (!note.value.trim()) {
      toast.error(t('educational.caseUi.terminalNoteRequired'))

      return
    }
    pendingTerminal.value = target
    terminalDialog.value = true

    return
  }

  await recordStage(target)
}

function cancelTerminalMove() {
  terminalDialog.value = false
  pendingTerminal.value = null
}

async function confirmTerminalMove() {
  if (!pendingTerminal.value)
    return

  await recordStage(pendingTerminal.value)
}

async function recordStage(target: ApplicantStage) {
  moving.value = true
  try {
    await ServiceCaseApi.changeStage(lineId.value, target.id, note.value.trim() || undefined)
    terminalDialog.value = false
    pendingTerminal.value = null
    selected.value = null
    note.value = ''
    await load()
    toast.success(t('educational.caseUi.stageRecorded'))
  }
  catch {
    // The shared API notifier shows the server's specific validation or
    // workflow message; a second generic toast would contradict it.
  }
  finally {
    moving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="education-workspace erp-workspace">
    <ErpPageHeader :title="pageTitle" section="applications" />
    <VCard v-if="loading && !line" class="service-case-card service-case-loading">
      <VCardText><section class="service-case-hero"><div class="d-flex align-center ga-3"><VSkeletonLoader type="avatar" /><div><VSkeletonLoader type="heading" width="220" /><VSkeletonLoader type="text" width="150" /></div></div><VSkeletonLoader type="chip" /></section><div class="service-case-grid mt-5"><VSkeletonLoader type="article" /><VSkeletonLoader type="article" /><VSkeletonLoader type="article" /><VSkeletonLoader type="article" /></div></VCardText>
    </VCard>
    <VCard v-else-if="line" class="service-case-card">
      <VCardText>
        <section class="service-case-hero">
          <div class="d-flex align-center flex-wrap ga-3"><VAvatar color="primary" variant="tonal" size="60"><VIcon icon="tabler-briefcase" size="30" /></VAvatar><div><VBtn size="small" variant="text" class="pa-0 mb-1" prepend-icon="tabler-arrow-left" :to="{ name: 'applicant-case', params: { id: line.applicant_id } }">{{ t('educational.caseUi.caseWorkspaceBack') }}</VBtn><RouterLink class="case-party-link d-block" :to="{ name: 'applicant-case', params: { id: line.applicant_id } }">{{ line.applicant?.name ?? `#${line.applicant_id}` }}</RouterLink><div class="d-flex flex-wrap ga-2 mt-1"><VChip size="x-small" variant="tonal">{{ serviceLabel }}</VChip><VChip v-if="line.contract_number" size="x-small" variant="tonal">{{ line.contract_number }}</VChip><VChip v-if="line.is_extension" size="x-small" variant="tonal" color="info">{{ t('serviceContracts.extension') }}</VChip></div></div></div>
          <div class="d-flex flex-wrap ga-2"><VChip size="small" :color="line.payment_status === 'paid' ? 'success' : line.payment_status === 'partially_paid' ? 'warning' : 'secondary'">{{ translateBackendValue(line.payment_status) }}</VChip><VChip size="small" :color="line.current_stage?.is_terminal ? 'secondary' : 'primary'">{{ movingStage }}</VChip></div>
        </section>
        <div class="service-case-grid mt-5">
        <section class="case-section service-case-stage">
          <div class="case-section__heading"><div><p class="case-section__eyebrow">{{ t('educational.stage') }}</p><h3>{{ movingStage }}</h3></div></div>
          <VAlert v-if="!canMoveStage" type="info" variant="tonal" density="compact">{{ t('educational.stageMovePermission') }}</VAlert>
          <VAlert v-else-if="caseClosed && !transitions.length" type="warning" variant="tonal" density="compact">{{ t('educational.caseUi.caseClosedFinal', { stage: movingStage }) }}</VAlert>
          <VAlert v-else-if="!transitions.length" type="info" variant="tonal" density="compact">{{ t('educational.caseUi.caseNoNextStage') }}</VAlert>
          <template v-else>
            <ErpField v-model="selected" kind="select" :items="transitions.map(stage => ({ title: translateApplicantStage(stage), value: stage.id }))" :label="t('educational.caseUi.moveStage')" :placeholder="t('educational.caseUi.moveStage')" class="mb-4" />
            <ErpField v-model="note" kind="textarea" :label="t('educational.caseUi.stageNote')" :placeholder="t('educational.caseUi.stageNote')" rows="3" class="mb-4" />
            <VBtn color="primary" variant="flat" :loading="moving" :disabled="!selectedStage" @click="move">{{ t('educational.caseUi.recordStage') }}</VBtn>
          </template>
        </section>

          <section class="case-section case-section--history">
            <div class="case-section__heading"><h3>{{ t('educational.caseUi.caseHistory') }}</h3></div>
            <VList v-if="history.length" density="compact">
              <VListItem
                v-for="row in history"
                :key="row.id"
                :title="t('educational.caseUi.stageTransition', { from: row.old_stage ? translateApplicantStage(row.old_stage) : t('educational.caseUi.started'), to: translateApplicantStage(row.new_stage) })"
                :subtitle="historySubtitle(row)"
              />
            </VList>
            <p v-else class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.caseNoHistory') }}</p>
          </section>

          <section class="case-section case-section--tasks">
            <div class="case-section__heading"><h3>{{ t('educational.caseUi.tasks') }}</h3></div>
            <VList v-if="tasks.length" density="compact">
              <VListItem v-for="task in tasks" :key="task.id" :title="task.title" :subtitle="taskSubtitle(task)">
                <template #append><VChip size="x-small" variant="tonal">{{ n(task.progress) }}%</VChip></template>
              </VListItem>
            </VList>
            <p v-else class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.caseNoTasks') }}</p>
          </section>

          <section v-if="canViewFinance" class="case-section case-section--finance">
            <div class="case-section__heading"><h3>{{ t('educational.caseUi.finance') }}</h3></div>
            <VList density="compact">
              <VListItem :title="t('educational.caseUi.serviceBalance', { fee: money(line.payable_total), paid: money(line.amount_paid), remaining: money(line.remaining_total), status: translateBackendValue(line.payment_status) })" />
              <VListItem
                v-for="row in incomes"
                :key="row.id"
                :title="row.receipt_number ?? t('educational.caseUi.incomePosted')"
                :subtitle="t('educational.caseUi.postedSummary', { amount: money(row.amount), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), description: row.description || t('common.messages.notAvailable') })"
              />
            </VList>
            <p v-if="!incomes.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.caseNoIncomes') }}</p>
          </section>
        </div>
      </VCardText>
    </VCard>
    <VCard v-else>
      <VCardText>{{ t('educational.caseUi.caseNotFound') }}</VCardText>
    </VCard>

    <VDialog v-model="terminalDialog" max-width="520">
      <VCard :title="t('educational.caseUi.terminalConfirmTitle')">
        <VCardText>
          <VAlert :type="pendingTerminal?.key === 'completed' ? 'success' : 'warning'" variant="tonal" density="compact" class="mb-4">
            {{ pendingTerminal?.key === 'completed'
              ? t('educational.caseUi.completedMoveNote', { stage: translateApplicantStage(pendingTerminal) })
              : t('educational.caseUi.exitMoveNote', { stage: translateApplicantStage(pendingTerminal) }) }}
          </VAlert>
          <p class="mb-0">{{ t('educational.caseUi.terminalConfirm', { stage: translateApplicantStage(pendingTerminal) }) }}</p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="cancelTerminalMove">{{ t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" :loading="moving" @click="confirmTerminalMove">{{ t('educational.caseUi.recordStage') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.service-case-card {
  overflow: hidden;
}

.service-case-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding: clamp(1rem, 2vw, 1.5rem);
  border: 1px solid rgba(var(--v-theme-primary), .18);
  border-radius: .75rem;
  background: linear-gradient(145deg, rgba(var(--v-theme-primary), .08), rgb(var(--v-theme-surface)) 55%);
}

.case-party-link {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  text-decoration: none;
}

.case-section {
  background: rgb(var(--v-theme-on-surface) / 0.035);
  border: 1px solid rgb(var(--v-border-color) / var(--v-border-opacity));
  border-radius: 0.75rem;
  min-inline-size: 0;
  padding: clamp(1rem, 2vw, 1.5rem);
}

.service-case-stage {
  background: rgba(var(--v-theme-primary), .045);
}

.case-section--history { background: rgba(var(--v-theme-info), .045); }
.case-section--tasks { background: rgba(var(--v-theme-secondary), .045); }
.case-section--finance { background: rgba(var(--v-theme-success), .045); }

.case-section__heading {
  align-items: flex-start;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  margin-block-end: 1rem;
}

.case-section__heading h3 {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  line-height: 1.4;
  margin: 0;
}

.case-section__eyebrow {
  color: rgb(var(--v-theme-on-surface) / var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  margin: 0 0 0.25rem;
  text-transform: uppercase;
}

.case-section :deep(.v-list) {
  background: transparent;
}

.service-case-grid {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (max-width: 959px) {
  .service-case-grid { grid-template-columns: 1fr; }
}

@media (max-width: 599px) {
  .case-section {
    padding: 1rem;
  }
}
</style>
