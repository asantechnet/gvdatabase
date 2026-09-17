<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import ApplicantApi, { type Applicant } from '@/api/applicants'
import BoardApi from '@/api/board'
import TaskApi from '@/api/task'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const route = useRoute()
const applicant = ref<Applicant | null>(null)
const stages = ref<Array<{ id: number; name: string; key: string; is_terminal?: boolean }>>([])
const selected = ref<number | null>(null)
const note = ref('')
const moving = ref(false)

const boards = ref<Array<{ id: string; title: string }>>([])
const taskDialog = ref(false)
const creatingTask = ref(false)
const completingId = ref<number | null>(null)
const taskForm = ref({ board_id: '', title: '', priority: 'medium' as 'low' | 'medium' | 'high' | 'urgent', due_date: '' })

const canCreateTask = computed(() => canAccess('create_tasks'))
const canEditCase = computed(() => canAccess('edit_applicants'))
const canViewFinance = computed(() => canAccess('view_pending_transactions') || canAccess('view_incomes') || canAccess('view_expenses'))
const openTasks = computed(() => (applicant.value?.tasks ?? []).filter(t => t.progress < 100))
const doneTasks = computed(() => (applicant.value?.tasks ?? []).filter(t => t.progress >= 100))
const documents = computed(() => (applicant.value?.tasks ?? []).flatMap(task => (task.attachments ?? []).map(a => ({ ...a, task_id: task.id, task_title: task.title }))))
const postedIncomeTotal = computed(() => (applicant.value?.incomes ?? []).reduce((sum, row) => sum + Number(row.amount || 0), 0))
const postedExpenseTotal = computed(() => (applicant.value?.expenses ?? []).reduce((sum, row) => sum + Number(row.amount || 0), 0))
const money = (value: number | string) => Number(value || 0).toLocaleString()
const feeOf = (row: { fee: number | string; currency?: { code: string } | null }) => `${money(row.fee)}${row.currency?.code ? ` ${row.currency.code}` : ''}`

const notesDraft = ref('')
const savingNotes = ref(false)
const downloadingId = ref<number | null>(null)

async function load() {
  applicant.value = await ApplicantApi.get(Number(route.params.id))
  stages.value = await ApplicantApi.stages()
  selected.value = applicant.value.current_stage_id ?? null
  notesDraft.value = applicant.value.notes ?? ''
}

async function loadBoards() {
  try {
    const response = await BoardApi.getAll()
    const rows = (response as { data?: { data?: Array<{ id: string; title: string }> } }).data?.data ?? []

    boards.value = rows.map(b => ({ id: String(b.id), title: b.title }))
    if (!taskForm.value.board_id && boards.value.length)
      taskForm.value.board_id = boards.value[0].id
  }
  catch {
    toast.error('Unable to load task boards.')
  }
}

async function move() {
  if (!selected.value)
    return

  const target = stages.value.find(s => s.id === selected.value)

  // Terminal moves (cancel, reject, withdraw, complete) must carry a reason
  // for the audit trail — the history row is permanent.
  if (target?.is_terminal && !note.value.trim()) {
    toast.error('A note is required when closing a case (cancelled, rejected, withdrawn, completed).')

    return
  }

  moving.value = true
  try {
    applicant.value = await ApplicantApi.changeStage(Number(route.params.id), selected.value, note.value)
    note.value = ''
    toast.success('Stage recorded. Required operational tasks were generated automatically.')
  }
  catch {
    toast.error('This stage transition is not allowed.')
  }
  finally {
    moving.value = false
  }
}

function openTaskDialog() {
  taskForm.value = { board_id: boards.value[0]?.id ?? '', title: '', priority: 'medium', due_date: '' }
  taskDialog.value = true
}

async function createTask() {
  if (!taskForm.value.board_id || !taskForm.value.title.trim() || !applicant.value)
    return

  creatingTask.value = true
  try {
    await TaskApi.create({
      board_id: taskForm.value.board_id,
      title: taskForm.value.title.trim(),
      priority: taskForm.value.priority,
      due_date: taskForm.value.due_date || undefined,
      customer_id: applicant.value.customer_id,
      applicant_id: applicant.value.id,
    })
    taskDialog.value = false
    await load()
    toast.success('Task added to this case.')
  }
  catch {
    toast.error('Unable to create the task.')
  }
  finally {
    creatingTask.value = false
  }
}

async function completeTask(id: number) {
  completingId.value = id
  try {
    await TaskApi.update(String(id), { progress: 100 })
    await load()
    toast.success('Task marked complete.')
  }
  catch (error: unknown) {
    // The server distinguishes permission denials from business rules
    // (subtasks open, dependencies incomplete) — show its reason.
    const message = (error as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (error as { message?: string })?.message
      || 'Unable to complete this task.'

    toast.error(message)
  }
  finally {
    completingId.value = null
  }
}

async function saveNotes() {
  if (!applicant.value)
    return

  savingNotes.value = true
  try {
    applicant.value = await ApplicantApi.update(applicant.value.id, { notes: notesDraft.value })
    notesDraft.value = applicant.value.notes ?? ''
    toast.success('Case notes saved.')
  }
  catch {
    toast.error('Unable to save notes.')
  }
  finally {
    savingNotes.value = false
  }
}

async function downloadDoc(taskId: number, attachmentId: number, filename: string) {
  downloadingId.value = attachmentId
  try {
    const response = await TaskApi.downloadAttachment(String(taskId), String(attachmentId))
    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')

    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  }
  catch {
    toast.error('Unable to download the document.')
  }
  finally {
    downloadingId.value = null
  }
}

onMounted(async () => {
  await load()
  await loadBoards()
})
</script>
<template>
  <VCard v-if="applicant">
    <VCardTitle>{{ applicant.customer?.name }} — Visa case</VCardTitle>
    <VCardText>
      <VChip color="primary">{{ applicant.current_stage?.name }}</VChip>
      <VChip v-if="openTasks.length" color="warning" size="small" class="ms-2">{{ openTasks.length }} open task{{ openTasks.length === 1 ? '' : 's' }}</VChip>
      <VSelect v-model="selected" :items="stages.map(s => ({ title: s.name, value: s.id }))" label="Move case to stage" class="mt-4" />
      <VTextarea v-model="note" label="Stage note (required to close a case)" />
      <VBtn color="primary" :loading="moving" @click="move">Record stage change</VBtn>
      <VDivider class="my-6" />
      <h3>Timeline</h3>
      <VList><VListItem v-for="item in applicant.stage_history" :key="item.id" :title="`${item.old_stage?.name ?? 'Started'} → ${item.new_stage?.name}`" :subtitle="item.note || item.changed_at" /></VList>
      <div class="d-flex align-center mt-4">
        <h3>Tasks</h3>
        <VSpacer />
        <VBtn v-if="canCreateTask" size="small" prepend-icon="tabler-plus" @click="openTaskDialog">New case task</VBtn>
      </div>
      <p v-if="!canCreateTask" class="text-caption text-medium-emphasis">Task creation requires permission. Auto-generated stage tasks still appear here.</p>
      <VList>
        <VListItem v-for="task in openTasks" :key="task.id" :title="task.title" :subtitle="`${task.progress}% complete${task.priority ? ` · ${task.priority}` : ''}${task.due_date ? ` · due ${task.due_date}` : ''}`">
          <template #append><VBtn size="small" variant="text" color="success" :loading="completingId === task.id" @click="completeTask(task.id)">Complete</VBtn></template>
        </VListItem>
        <VListItem v-for="task in doneTasks" :key="`done-${task.id}`" :title="task.title" subtitle="Complete"><template #prepend><VIcon icon="tabler-check" color="success" /></template></VListItem>
      </VList>
      <p v-if="!applicant.tasks?.length" class="text-caption text-medium-emphasis">No operational work yet. Moving this case into a work stage generates it automatically.</p>
      <h3 class="mt-4">Contracts</h3>
      <VList><VListItem v-for="contract in applicant.contracts" :key="contract.id" :title="contract.contract_number" :subtitle="contract.status" /></VList>
      <h3 class="mt-4">Services sold</h3>
      <VList>
        <VListItem v-for="row in applicant.scholarships" :key="`sch-${row.id}`" title="Scholarship service" :subtitle="`${feeOf(row)}${row.date ? ` · ${row.date}` : ''}${row.status ? ` · ${row.status}` : ''}`" />
        <VListItem v-for="row in applicant.test_offers" :key="`test-${row.id}`" title="Test offer" :subtitle="`${feeOf(row)}${row.date ? ` · ${row.date}` : ''}${row.status ? ` · ${row.status}` : ''}`" />
      </VList>
      <p v-if="!applicant.scholarships?.length && !applicant.test_offers?.length" class="text-caption text-medium-emphasis">No services recorded for this case yet.</p>
      <div v-if="canViewFinance">
        <div class="d-flex align-center mt-4">
          <h3>Finance</h3>
          <VSpacer />
          <VBtn size="small" variant="text" :to="{ name: 'pending-transactions' }">Pending workspace</VBtn>
        </div>
        <p class="text-caption text-medium-emphasis">Posted income {{ money(postedIncomeTotal) }} · posted expenses {{ money(postedExpenseTotal) }}.</p>
        <VList>
          <VListItem v-for="row in applicant.pending_transactions" :key="`pt-${row.id}`" :title="`${row.transaction_type} — ${row.reason}`" :subtitle="`${money(row.amount)} · ${row.approval_status.replace('_', ' ')}${row.rejection_reason ? ` · ${row.rejection_reason}` : ''}`" />
        </VList>
        <VList>
          <VListItem v-for="row in applicant.incomes" :key="`in-${row.id}`" title="Income posted" :subtitle="`${money(row.amount)}${row.date ? ` · ${row.date}` : ''}${row.description ? ` · ${row.description}` : ''}`"><template #prepend><VIcon icon="tabler-arrow-down-left" color="success" /></template></VListItem>
          <VListItem v-for="row in applicant.expenses" :key="`ex-${row.id}`" title="Expense posted" :subtitle="`${money(row.amount)}${row.date ? ` · ${row.date}` : ''}${row.description ? ` · ${row.description}` : ''}`"><template #prepend><VIcon icon="tabler-arrow-up-right" color="error" /></template></VListItem>
        </VList>
        <p v-if="!applicant.pending_transactions?.length && !applicant.incomes?.length && !applicant.expenses?.length" class="text-caption text-medium-emphasis">No financial activity for this case yet.</p>
      </div>
      <h3 class="mt-4">Documents</h3>
      <VList>
        <VListItem v-for="doc in documents" :key="doc.id" :title="doc.original_name" :subtitle="`From task: ${doc.task_title}`">
          <template #append><VBtn size="small" variant="text" :loading="downloadingId === doc.id" @click="downloadDoc(doc.task_id, doc.id, doc.original_name)">Download</VBtn></template>
        </VListItem>
      </VList>
      <p v-if="!documents.length" class="text-caption text-medium-emphasis">No documents yet. Files attached to this case's tasks appear here.</p>
      <h3 class="mt-4">Notes</h3>
      <VTextarea v-model="notesDraft" label="Case notes" :disabled="!canEditCase" />
      <VBtn v-if="canEditCase" color="primary" :loading="savingNotes" @click="saveNotes">Save notes</VBtn>
      <p v-else class="text-caption text-medium-emphasis">Editing notes requires permission.</p>
    </VCardText>
  </VCard>
  <VDialog v-model="taskDialog" max-width="560">
    <VCard title="New case task">
      <VCardText>
        <VSelect v-model="taskForm.board_id" :items="boards.map(b => ({ title: b.title, value: b.id }))" label="Board" class="mb-3" />
        <VTextField v-model="taskForm.title" label="Title" class="mb-3" />
        <VSelect v-model="taskForm.priority" :items="['low', 'medium', 'high', 'urgent']" label="Priority" class="mb-3" />
        <VTextField v-model="taskForm.due_date" type="date" label="Due date" clearable />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="taskDialog = false">Cancel</VBtn>
        <VBtn color="primary" :loading="creatingTask" :disabled="!taskForm.board_id || !taskForm.title.trim()" @click="createTask">Create</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
