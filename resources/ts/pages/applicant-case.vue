<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type Applicant, type ApplicantStage, ApplicantUniversityApi, ApplicantAttachmentApi, type ApplicantAttachment } from '@/api/applicants'
import ApplicantPortalApi from '@/api/applicantPortal'
import ContractApi, { type Contract } from '@/api/contracts'
import ContractTemplateApi, { type ContractTemplate } from '@/api/contractTemplate'
import BoardApi from '@/api/board'
import ContractForm, { type ContractFormPayload } from '@/components/ContractForm.vue'
import ContractDocumentPrinter from '@/components/contract/ContractDocumentPrinter.vue'
import EmployeeApi, { type Employee } from '@/api/employee'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import PendingTransactionApi, { type PendingTransactionInput, type PendingTransactionOptions } from '@/api/pendingTransactions'
import TaskApi from '@/api/task'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate, translateBackendValue } from '@/utils/localization'

const toast = useToast()
const route = useRoute()
const { t, n } = useI18n()
const applicant = ref<Applicant | null>(null)
const stages = ref<ApplicantStage[]>([])
// Vuetify can preserve an item value as a string after a component remount.
// Keep the UI tolerant of that, but always convert it before posting to the
// integer-only workflow endpoint.
const selected = ref<number | string | null>(null)
const note = ref('')
const moving = ref(false)

const boards = ref<Array<{ id: string; title: string }>>([])
const taskDialog = ref(false)
const creatingTask = ref(false)
const completingId = ref<number | null>(null)
const taskForm = ref({ board_id: '', title: '', priority: 'medium' as 'low' | 'medium' | 'high' | 'urgent', due_date: '' })

const canCreateTask = computed(() => canAccess('create_tasks'))
const canEditCase = computed(() => canAccess('edit_applicants'))
const canInvitePortal = computed(() => canAccess('invite_applicant_portal'))
const canAssignEmployee = computed(() => canAccess('assign_applicant_employee'))
const canViewFinance = computed(() => canAccess('view_applicant_financials'))
const canCreatePendingTransactions = computed(() => canAccess('create_pending_transactions'))
const canViewContracts = computed(() => canAccess('view_applicant_contracts'))
const canViewServices = computed(() => canAccess('view_applicant_services'))
const canManageServices = computed(() => canAccess('create_contracts'))
// Family contact and the grade metric are shown next to the case title so
// staff can confirm them without opening the edit dialog.
const guardianSummary = computed(() => {
  const current = applicant.value
  if (!current) return []
  const rows: Array<{ key: string; label: string; value: string }> = []
  if (current.father_name)
    rows.push({ key: 'father-name', label: t('educational.fatherName'), value: current.father_name })
  if (current.father_phone)
    rows.push({ key: 'father-phone', label: t('educational.fatherPhone'), value: current.father_phone })
  if (current.percentage !== null && current.percentage !== undefined)
    rows.push({ key: 'percentage', label: t('educational.gradePercentage'), value: `${n(Number(current.percentage))}%` })
  return rows
})
const transitionOptions = computed(() => {
  const current = applicant.value?.current_stage?.key
  if (!current) return stages.value
  const allowed = stages.value.find(stage => stage.key === current)?.allowed_next_keys ?? []
  return stages.value.filter(stage => allowed.includes(stage.key))
})
const openTasks = computed(() => (applicant.value?.tasks ?? []).filter(t => t.progress < 100))
const doneTasks = computed(() => (applicant.value?.tasks ?? []).filter(t => t.progress >= 100))
const stageHistory = computed(() => [...(applicant.value?.stage_history ?? [])].sort((a, b) => new Date(a.changed_at).getTime() - new Date(b.changed_at).getTime()))
const documents = computed(() => (applicant.value?.tasks ?? []).flatMap(task => (task.attachments ?? []).map(a => ({ ...a, task_id: task.id, task_title: task.title }))))
const postedIncomeTotal = computed(() => (applicant.value?.incomes ?? []).reduce((sum, row) => sum + Number(row.amount || 0), 0))
const postedExpenseTotal = computed(() => (applicant.value?.expenses ?? []).reduce((sum, row) => sum + Number(row.amount || 0), 0))
const money = (value: number | string) => n(Number(value || 0), 'decimal')
const feeOf = (row: { fee: number | string | null; currency?: { code: string } | null }) => row.fee === null || row.fee === undefined ? t('common.messages.notAvailable') : `${money(row.fee)}${row.currency?.code ? ` ${row.currency.code}` : ''}`
const formatDate = (value?: string | null) => value ? formatLocalizedDate(value, 'short') : ''
const formatDateTime = (value?: string | null) => value ? formatLocalizedDate(value, 'long') : ''
const stageSubtitle = (item: { changed_at: string; note?: string | null }) => {
  const when = formatDateTime(item.changed_at)
  const desc = item.note?.trim()

  return desc ? `${when} · ${desc}` : when
}
const taskSummary = (task: { progress: number; priority?: string | null; due_date?: string | null }) => t('educational.caseUi.taskSummary', {
  progress: n(task.progress),
  priority: task.priority ? translateBackendValue(task.priority) : t('common.messages.notAvailable'),
  date: task.due_date ? formatDate(task.due_date) : t('common.messages.notAvailable'),
})

const notesDraft = ref('')
const savingNotes = ref(false)
const downloadingId = ref<number | null>(null)

const universities = ref<MasterDataRecord[]>([])
const countries = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const semesters = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const uniDialog = ref(false)
const savingUni = ref(false)
const uniForm = ref({ university_id: null as number | null, semester_id: null as number | null, status: 'pending', intake: '', notes: '' })
const uniStatuses = ['pending', 'applied', 'admitted', 'rejected', 'withdrawn']
const statusColor = (status: string) => ({ pending: 'secondary', applied: 'info', admitted: 'success', rejected: 'error', withdrawn: 'warning' }[status] ?? 'secondary')

const editDialog = ref(false)
const savingCase = ref(false)
const editForm = ref({ father_name: '', father_phone: '', responsible_employee_id: null as number | null, country_id: null as number | null, university_id: null as number | null, education_level_id: null as number | null, percentage: '', language_id: null as number | null, semester_id: null as number | null, application_date: '' })
const portalDialog = ref(false)
const sendingPortalInvite = ref(false)
const portalForm = ref({ name: '', email: '', phone: '', address: '' })
const contractDialog = ref(false)
const savingContract = ref(false)
const contractTemplates = ref<ContractTemplate[]>([])
// Individual contracts print through the same dialog the contracts workspace
// uses, so the cover, letterhead, signature and party-detail options match.
const contractPrintDialog = ref(false)
const contractToPrint = ref<Contract | null>(null)
const printingContractId = ref<number | null>(null)
const financeDialog = ref(false)
const savingFinance = ref(false)
const financeOptions = ref<PendingTransactionOptions>({ currencies: [], employees: [], applicants: [] })
const financeForm = ref<PendingTransactionInput>({ transaction_type: 'income', reason: '', amount: 0, currency_id: null, applicant_id: null, employee_id: null })
const documentDialog = ref(false)
const uploadingDocument = ref(false)
const documentTaskId = ref<number | null>(null)
const documentFile = ref<File | null>(null)
const applicantAttachments = ref<ApplicantAttachment[]>([])
const attachmentLabel = ref('')
const attachmentFile = ref<File | File[] | null>(null)
const uploadingApplicantAttachment = ref(false)
const canAttachDocuments = computed(() => (applicant.value?.tasks?.length ?? 0) > 0)

async function load() {
  applicant.value = await ApplicantApi.get(Number(route.params.id))
  stages.value = await ApplicantApi.stages({ country_id: applicant.value.country_id, university_id: applicant.value.university_id })
  selected.value = null
  notesDraft.value = applicant.value.notes ?? ''
  applicantAttachments.value = await ApplicantAttachmentApi.list(applicant.value.id)
}

async function uploadApplicantAttachment() { const file = Array.isArray(attachmentFile.value) ? attachmentFile.value[0] : attachmentFile.value; if (!applicant.value || !file || !attachmentLabel.value.trim()) return; uploadingApplicantAttachment.value = true; try { applicantAttachments.value.unshift(await ApplicantAttachmentApi.upload(applicant.value.id, file, attachmentLabel.value.trim())); attachmentFile.value = null; attachmentLabel.value = ''; toast.success(t('educational.caseUi.documentAdded')) } catch { toast.error(t('educational.caseUi.documentAddFailed')) } finally { uploadingApplicantAttachment.value = false } }
async function removeApplicantAttachment(id: number) { if (!applicant.value) return; await ApplicantAttachmentApi.remove(applicant.value.id, id); applicantAttachments.value = applicantAttachments.value.filter(item => item.id !== id) }

async function loadBoards() {
  try {
    const response = await BoardApi.getAll()
    const rows = (response as { data?: { data?: Array<{ id: string; title: string }> } }).data?.data ?? []

    boards.value = rows.map(b => ({ id: String(b.id), title: b.title }))
    if (!taskForm.value.board_id && boards.value.length)
      taskForm.value.board_id = boards.value[0].id
  }
  catch {
    toast.error(t('educational.caseUi.loadBoardsFailed'))
  }
}

async function move() {
  const stageId = Number(selected.value)
  if (!Number.isSafeInteger(stageId))
    return

  const target = stages.value.find(s => s.id === stageId)

  // The current stage is not a transition, and a stale select value must not
  // be submitted after the case or workflow data has been refreshed.
  if (!target || !transitionOptions.value.some(stage => stage.id === stageId))
    return

  // Terminal moves (cancel, reject, withdraw, complete) must carry a reason
  // for the audit trail — the history row is permanent.
  if (target?.is_terminal && !note.value.trim()) {
    toast.error(t('educational.caseUi.terminalNoteRequired'))

    return
  }
  if (target?.is_terminal && !confirm(t('educational.caseUi.terminalConfirm', { stage: target.name })))
    return

  moving.value = true
  try {
    applicant.value = await ApplicantApi.changeStage(Number(route.params.id), stageId, note.value)
    selected.value = null
    note.value = ''
    toast.success(t('educational.caseUi.stageRecorded'))
  }
  catch {
    // The shared API error notifier shows the server's specific validation or
    // workflow message. Avoid adding a contradictory second generic toast.
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
      applicant_id: applicant.value.id,
    })
    taskDialog.value = false
    await load()
    toast.success(t('educational.caseUi.taskCreated'))
  }
  catch {
    toast.error(t('educational.caseUi.taskCreateFailed'))
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
    toast.success(t('educational.caseUi.taskCompleted'))
  }
  catch (error: unknown) {
    // The server distinguishes permission denials from business rules
    // (subtasks open, dependencies incomplete) — show its reason.
    const message = (error as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (error as { message?: string })?.message
      || t('educational.caseUi.taskCompleteFailed')

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
    toast.success(t('educational.caseUi.notesSaved'))
  }
  catch {
    toast.error(t('educational.caseUi.notesSaveFailed'))
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
    toast.error(t('educational.caseUi.downloadFailed'))
  }
  finally {
    downloadingId.value = null
  }
}

function openEditCase() {
  if (!applicant.value)
    return

  editForm.value = {
    father_name: applicant.value.father_name ?? '',
    father_phone: applicant.value.father_phone ?? '',
    responsible_employee_id: applicant.value.responsible_employee_id ?? null,
    country_id: applicant.value.country_id ?? null,
    university_id: applicant.value.university_id ?? null,
    education_level_id: applicant.value.education_level_id ?? null,
    percentage: applicant.value.percentage === null || applicant.value.percentage === undefined ? '' : String(applicant.value.percentage),
    language_id: applicant.value.language_id ?? null,
    semester_id: applicant.value.semester_id ?? null,
    application_date: applicant.value.application_date ?? '',
  }
  editDialog.value = true
}

function openPortalInvite() {
  if (!applicant.value)
    return

  portalForm.value = {
    name: applicant.value.name,
    email: applicant.value.email ?? '',
    phone: applicant.value.contact ?? '',
    address: '',
  }
  portalDialog.value = true
}

async function sendPortalInvite() {
  if (!applicant.value || !portalForm.value.name.trim() || !portalForm.value.email.trim())
    return

  sendingPortalInvite.value = true
  try {
    await ApplicantPortalApi.invite(applicant.value.id, {
      name: portalForm.value.name.trim(),
      email: portalForm.value.email.trim(),
      phone: portalForm.value.phone.trim() || null,
      address: portalForm.value.address.trim() || null,
    })
    portalDialog.value = false
    await load()
    toast.success(t('educational.caseUi.invitationSent'))
  }
  catch {
    toast.error(t('educational.caseUi.invitationFailed'))
  }
  finally {
    sendingPortalInvite.value = false
  }
}

async function saveCase() {
  if (!applicant.value)
    return

  savingCase.value = true
  try {
    const payload: Record<string, unknown> = {}

    for (const [key, value] of Object.entries(editForm.value)) {
      if (key === 'responsible_employee_id' && !canAssignEmployee.value)
        continue
      if (value !== null && value !== '') {
        // The grade metric is held as text so a cleared field is skipped
        // instead of being posted as an empty number.
        payload[key] = key === 'percentage' ? Number(value) : value
      }
    }

    applicant.value = await ApplicantApi.update(applicant.value.id, payload)
    editDialog.value = false
    toast.success(t('educational.caseUi.caseUpdated'))
  }
  catch {
    toast.error(t('educational.caseUi.caseUpdateFailed'))
  }
  finally {
    savingCase.value = false
  }
}

function openUniDialog() {
  uniForm.value = { university_id: null, semester_id: applicant.value?.semester_id ?? null, status: 'pending', intake: '', notes: '' }
  uniDialog.value = true
}

async function saveUni() {
  if (!applicant.value || !uniForm.value.university_id)
    return

  savingUni.value = true
  try {
    await ApplicantUniversityApi.create(applicant.value.id, {
      university_id: uniForm.value.university_id,
      semester_id: uniForm.value.semester_id,
      status: uniForm.value.status,
      intake: uniForm.value.intake.trim() || undefined,
      notes: uniForm.value.notes.trim() || undefined,
    })
    uniDialog.value = false
    await load()
    toast.success(t('educational.caseUi.universityAdded'))
  }
  catch {
    toast.error(t('educational.caseUi.universityAddFailed'))
  }
  finally {
    savingUni.value = false
  }
}

async function setUniStatus(id: number, status: string) {
  if (!applicant.value)
    return

  try {
    await ApplicantUniversityApi.update(applicant.value.id, id, { status })
    await load()
    toast.success(t('educational.caseUi.applicationMarked', { status: translateBackendValue(status) }))
  }
  catch {
    toast.error(t('educational.caseUi.applicationUpdateFailed'))
  }
}

async function removeUni(id: number) {
  if (!applicant.value || !confirm(t('educational.caseUi.removeUniversityConfirm')))
    return

  try {
    await ApplicantUniversityApi.remove(applicant.value.id, id)
    await load()
    toast.success(t('educational.caseUi.applicationRemoved'))
  }
  catch {
    toast.error(t('educational.caseUi.applicationRemoveFailed'))
  }
}

async function setPrimaryUniversity(universityId: number) {
  if (!applicant.value)
    return

  try {
    await ApplicantApi.update(applicant.value.id, { university_id: universityId })
    await load()
    toast.success(t('educational.caseUi.primaryUpdated'))
  }
  catch {
    toast.error(t('educational.caseUi.primaryUpdateFailed'))
  }
}

async function openContractPrint(contract: { id: number }) {
  printingContractId.value = contract.id
  try {
    contractToPrint.value = await ContractApi.getById(contract.id)
    contractPrintDialog.value = true
  }
  catch {
    toast.error(t('educational.caseUi.printContractFailed'))
  }
  finally {
    printingContractId.value = null
  }
}

async function openContractDialog() {
  try {
    contractTemplates.value = (await ContractTemplateApi.getAll()).filter(template => template.type_slug === 'customer')
    contractDialog.value = true
  }
  catch {
    toast.error('Unable to load contract templates.')
  }
}

async function createCaseContract(payload: ContractFormPayload) {
  if (!applicant.value)
    return

  savingContract.value = true
  try {
    await ContractApi.create({
      ...payload,
      applicant_id: applicant.value.id,
    })
    contractDialog.value = false
    await load()
    toast.success('Contract linked to this visa case.')
  }
  catch {
    // The shared API notifier displays the server's specific validation error.
  }
  finally {
    savingContract.value = false
  }
}

async function openFinanceDialog() {
  if (!applicant.value)
    return

  try {
    financeOptions.value = await PendingTransactionApi.options()
    financeForm.value = {
      transaction_type: 'income',
      reason: '',
      amount: 0,
      currency_id: financeOptions.value.currencies.find(currency => currency.is_base)?.id ?? null,
      applicant_id: applicant.value.id,
      employee_id: applicant.value.responsible_employee_id ?? null,
    }
    financeDialog.value = true
  }
  catch {
    toast.error('Unable to load financial request options.')
  }
}

async function createFinanceRequest() {
  if (!financeForm.value.reason.trim() || financeForm.value.amount <= 0 || !financeForm.value.currency_id)
    return

  savingFinance.value = true
  try {
    await PendingTransactionApi.create(financeForm.value)
    financeDialog.value = false
    await load()
    toast.success('Financial request linked to this visa case.')
  }
  catch {
    // The shared API notifier displays the server's specific validation error.
  }
  finally {
    savingFinance.value = false
  }
}

function openDocumentDialog() {
  documentTaskId.value = applicant.value?.tasks?.[0]?.id ?? null
  documentFile.value = null
  documentDialog.value = true
}

async function uploadDocument() {
  if (!documentTaskId.value || !documentFile.value)
    return

  uploadingDocument.value = true
  try {
    // Reuse the task's input-attachment flow: documents therefore retain the
    // same access controls, audit trail, download handling, and file limits.
    await TaskApi.uploadAttachment(String(documentTaskId.value), documentFile.value, 'input')
    documentDialog.value = false
    await load()
    toast.success('Document attached to this visa case.')
  }
  catch {
    // The shared API notifier displays the exact upload or permission error.
  }
  finally {
    uploadingDocument.value = false
  }
}

onMounted(async () => {
  await load()
  await loadBoards()

  try {
    const [universityRows, countryRows, levelRows, languageRows, semesterRows, employeeRows] = await Promise.all([
      MasterDataApi.list('universities'),
      MasterDataApi.list('countries'),
      MasterDataApi.list('education-levels'),
      MasterDataApi.list('languages'),
      MasterDataApi.list('semesters'),
      EmployeeApi.getOptions(),
    ])

    universities.value = universityRows
    countries.value = countryRows
    levels.value = levelRows
    languages.value = languageRows
    semesters.value = semesterRows
    employees.value = employeeRows
  }
  catch {
    toast.error(t('educational.caseUi.loadUniversitiesFailed'))
  }
})
</script>
<template>
  <VCard v-if="applicant" class="visa-case-card">
    <VCardTitle class="d-flex flex-wrap align-center ga-2">
      <span>{{ t('educational.caseUi.visaCase', { name: applicant.name }) }}</span>
      <VSpacer />
      <VChip v-if="applicant.user_id" size="small" color="success">{{ t('educational.caseUi.portalActive') }}</VChip>
      <VBtn v-else-if="canInvitePortal" size="small" variant="tonal" color="primary" prepend-icon="tabler-send" @click="openPortalInvite">{{ t('educational.caseUi.invitePortal') }}</VBtn>
      <VBtn v-if="canEditCase" size="small" variant="text" prepend-icon="tabler-pencil" @click="openEditCase">{{ t('educational.caseUi.editCase') }}</VBtn>
    </VCardTitle>
    <VCardSubtitle v-if="guardianSummary.length" class="d-flex flex-wrap ga-4">
      <span v-for="row in guardianSummary" :key="row.key">{{ row.label }}: <strong>{{ row.value }}</strong></span>
    </VCardSubtitle>
    <VCardText class="visa-case-content">
      <div class="visa-case-stage-panel">
      <VChip color="primary">{{ applicant.current_stage?.name }}</VChip>
      <VChip v-if="openTasks.length" color="warning" size="small" class="ms-2">{{ t('educational.caseUi.openTasks', { count: n(openTasks.length) }) }}</VChip>
      <VSelect v-model="selected" :items="transitionOptions" item-title="name" item-value="id" :label="t('educational.caseUi.moveStage')" class="mt-5 mb-4" />
      <VTextarea v-model="note" :label="t('educational.caseUi.stageNote')" class="mb-4" />
      <VBtn color="primary" :loading="moving" @click="move">{{ t('educational.caseUi.recordStage') }}</VBtn>
      </div>
      <VCard variant="tonal" color="primary" class="mt-6"><VCardItem :title="t('educational.caseUi.applicantDocuments')" :subtitle="applicantAttachments.length ? t('educational.caseUi.applicantDocumentsReady', { count: applicantAttachments.length }) : t('educational.caseUi.applicantDocumentsNeeded')" /><VCardText><VList density="compact"><VListItem v-for="file in applicantAttachments" :key="file.id" :title="file.label" :subtitle="file.name"><template #append><VBtn icon="tabler-download" size="x-small" variant="text" :href="file.download_url"/><VBtn v-if="canEditCase" icon="tabler-trash" size="x-small" variant="text" color="error" @click="removeApplicantAttachment(file.id)"/></template></VListItem></VList><div v-if="canEditCase" class="d-flex flex-wrap ga-2 mt-3"><VTextField v-model="attachmentLabel" :label="t('educational.caseUi.documentLabel')" density="compact" hide-details style="min-width:180px"/><VFileInput v-model="attachmentFile" density="compact" hide-details style="min-width:220px"/><VBtn color="primary" :loading="uploadingApplicantAttachment" :disabled="!attachmentFile || !attachmentLabel.trim()" @click="uploadApplicantAttachment">{{ t('educational.caseUi.attach') }}</VBtn></div></VCardText></VCard>
      <VDivider class="my-8" />
      <h3>{{ t('educational.caseUi.timeline') }}</h3>
      <VList><VListItem v-for="item in stageHistory" :key="item.id" :title="t('educational.caseUi.stageTransition', { from: item.old_stage?.name ?? t('educational.caseUi.started'), to: item.new_stage?.name })" :subtitle="stageSubtitle(item)" /></VList>
      <div class="d-flex align-center mt-4">
        <h3>{{ t('educational.caseUi.tasks') }}</h3>
        <VSpacer />
        <VBtn v-if="canCreateTask" size="small" prepend-icon="tabler-plus" @click="openTaskDialog">{{ t('educational.caseUi.newTask') }}</VBtn>
      </div>
      <p v-if="!canCreateTask" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.taskPermission') }}</p>
      <VList>
        <VListItem v-for="task in openTasks" :key="task.id" :title="task.title" :subtitle="taskSummary(task)">
          <template #append><VBtn size="small" variant="text" color="success" :loading="completingId === task.id" @click="completeTask(task.id)">{{ t('educational.caseUi.complete') }}</VBtn></template>
        </VListItem>
        <VListItem v-for="task in doneTasks" :key="`done-${task.id}`" :title="task.title" :subtitle="t('educational.caseUi.complete')"><template #prepend><VIcon icon="tabler-check" color="success" /></template></VListItem>
      </VList>
      <p v-if="!applicant.tasks?.length" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.noTasks') }}</p>
      <div class="d-flex align-center mt-4">
        <h3>{{ t('educational.caseUi.universityApplications') }}</h3>
        <VSpacer />
        <VBtn v-if="canEditCase" size="small" prepend-icon="tabler-plus" @click="openUniDialog">{{ t('educational.caseUi.addApplication') }}</VBtn>
      </div>
      <p v-if="applicant.university" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.primaryChoice', { university: applicant.university.name }) }}</p>
      <VList>
        <VListItem v-for="app in applicant.universities" :key="app.id" class="university-application-item" :title="app.university?.name ?? t('educational.caseUi.universityNumber', { id: n(app.university_id) })" :subtitle="t('educational.caseUi.applicationSummary', { status: translateBackendValue(app.status), intake: app.intake || t('common.messages.notAvailable'), notes: app.notes || t('common.messages.notAvailable') })">
          <template #prepend><VChip size="small" :color="statusColor(app.status)">{{ translateBackendValue(app.status) }}</VChip></template>
          <template v-if="canEditCase" #append>
            <VMenu>
              <template #activator="{ props }"><VBtn size="small" variant="text" v-bind="props">{{ t('common.fields.status') }}</VBtn></template>
              <VList density="compact"><VListItem v-for="s in uniStatuses" :key="s" :title="translateBackendValue(s)" @click="setUniStatus(app.id, s)" /></VList>
            </VMenu>
            <VBtn v-if="app.university_id !== applicant.university_id" size="small" variant="text" @click="setPrimaryUniversity(app.university_id)">{{ t('educational.caseUi.primary') }}</VBtn>
            <VBtn size="small" variant="text" color="error" @click="removeUni(app.id)">{{ t('common.actions.remove') }}</VBtn>
          </template>
        </VListItem>
      </VList>
      <p v-if="!applicant.universities?.length" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.noApplications') }}</p>
      <template v-if="canViewServices">
        <div class="d-flex align-center mt-4">
          <h3>{{ t('educational.caseUi.servicesSold') }}</h3>
          <VSpacer />
          <VBtn v-if="canManageServices" size="small" prepend-icon="tabler-plus" @click="openContractDialog()">New contract</VBtn>
        </div>
        <VList>
          <VListItem
            v-for="row in applicant.services"
            :key="`service-${row.id}`"
            :title="row.type"
            :subtitle="t('educational.caseUi.serviceSummary', { fee: feeOf(row), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), status: row.status ? translateBackendValue(row.status) : t('common.messages.notAvailable') })"
          >
            <template v-if="row.contract_id && canViewContracts" #append>
              <VBtn
                icon="tabler-printer"
                size="small"
                variant="text"
                :loading="printingContractId === row.contract_id"
                :aria-label="t('pages.contracts.allContracts.allContracts.printContract')"
                @click="openContractPrint({ id: row.contract_id })"
              >
                <VIcon icon="tabler-printer" />
                <VTooltip activator="parent" location="top">
                  {{ row.contract_number || t('pages.contracts.allContracts.allContracts.printContract') }}
                </VTooltip>
              </VBtn>
            </template>
          </VListItem>
        </VList>
        <p v-if="!applicant.services?.length" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.noServices') }}</p>
      </template>
      <div v-if="canViewFinance">
        <div class="d-flex align-center mt-4">
          <h3>{{ t('educational.caseUi.finance') }}</h3>
          <VSpacer />
          <VBtn v-if="canCreatePendingTransactions" size="small" prepend-icon="tabler-plus" @click="openFinanceDialog">New financial request</VBtn>
          <VBtn size="small" variant="text" :to="{ name: 'pending-transactions' }">{{ t('educational.caseUi.pendingWorkspace') }}</VBtn>
        </div>
        <p class="text-caption text-medium-emphasis">{{ t('educational.caseUi.financeSummary', { income: money(postedIncomeTotal), expenses: money(postedExpenseTotal) }) }}</p>
        <VList>
          <VListItem v-for="row in applicant.pending_transactions" :key="`pt-${row.id}`" :title="t('educational.caseUi.transactionTitle', { type: translateBackendValue(row.transaction_type), reason: row.reason })" :subtitle="t('educational.caseUi.transactionSummary', { amount: money(row.amount), status: translateBackendValue(row.approval_status), rejection: row.rejection_reason || t('common.messages.notAvailable') })" />
        </VList>
        <VList>
          <VListItem v-for="row in applicant.incomes" :key="`in-${row.id}`" :title="t('educational.caseUi.incomePosted')" :subtitle="t('educational.caseUi.postedSummary', { amount: money(row.amount), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), description: row.description || t('common.messages.notAvailable') })"><template #prepend><VIcon icon="tabler-arrow-down-left" color="success" /></template></VListItem>
          <VListItem v-for="row in applicant.expenses" :key="`ex-${row.id}`" :title="t('educational.caseUi.expensePosted')" :subtitle="t('educational.caseUi.postedSummary', { amount: money(row.amount), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), description: row.description || t('common.messages.notAvailable') })"><template #prepend><VIcon icon="tabler-arrow-up-right" color="error" /></template></VListItem>
        </VList>
        <p v-if="!applicant.pending_transactions?.length && !applicant.incomes?.length && !applicant.expenses?.length" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.noFinance') }}</p>
      </div>
      <div class="d-flex align-center mt-4">
        <h3>{{ t('educational.caseUi.documents') }}</h3>
        <VSpacer />
        <VBtn v-if="canAttachDocuments" size="small" prepend-icon="tabler-paperclip" @click="openDocumentDialog">Attach document</VBtn>
      </div>
      <VList>
        <VListItem v-for="doc in documents" :key="doc.id" :title="doc.original_name" :subtitle="t('educational.caseUi.fromTask', { task: doc.task_title })">
          <template #append><VBtn size="small" variant="text" :loading="downloadingId === doc.id" @click="downloadDoc(doc.task_id, doc.id, doc.original_name)">{{ t('educational.caseUi.download') }}</VBtn></template>
        </VListItem>
      </VList>
      <p v-if="!documents.length" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.noDocuments') }}</p>
      <h3 class="mt-4">{{ t('educational.caseUi.notes') }}</h3>
      <VTextarea v-model="notesDraft" :label="t('educational.caseUi.caseNotes')" :disabled="!canEditCase" class="mb-4" />
      <VBtn v-if="canEditCase" color="primary" class="mb-2" :loading="savingNotes" @click="saveNotes">{{ t('educational.caseUi.saveNotes') }}</VBtn>
      <p v-else class="text-caption text-medium-emphasis">{{ t('educational.caseUi.notesPermission') }}</p>
    </VCardText>
  </VCard>
  <VDialog v-model="taskDialog" max-width="560">
    <VCard :title="t('educational.caseUi.newTask')">
      <VCardText class="case-dialog-body">
        <VSelect v-model="taskForm.board_id" :items="boards.map(b => ({ title: b.title, value: b.id }))" :label="t('common.fields.board')" class="mb-3" />
        <VTextField v-model="taskForm.title" :label="t('common.fields.title')" class="mb-3" />
        <VSelect v-model="taskForm.priority" :items="['low', 'medium', 'high', 'urgent'].map(value => ({ value, title: translateBackendValue(value) }))" :label="t('common.fields.priority')" class="mb-3" />
        <VTextField v-model="taskForm.due_date" type="date" :label="t('educational.caseUi.dueDate')" clearable />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="taskDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="creatingTask" :disabled="!taskForm.board_id || !taskForm.title.trim()" @click="createTask">{{ t('common.actions.create') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="uniDialog" max-width="560">
    <VCard :title="t('educational.caseUi.addUniversityApplication')">
      <VCardText class="case-dialog-body">
        <VSelect v-model="uniForm.university_id" :items="universities.filter(u => u.is_active && u.country_id === applicant?.country_id).map(u => ({ title: u.name, value: u.id }))" :label="t('educational.university')" class="mb-3" />
        <VSelect v-model="uniForm.semester_id" :items="semesters.filter(s => s.is_active).map(s => ({ title: s.name, value: s.id }))" :label="t('educational.semester')" clearable class="mb-3" />
        <VSelect v-model="uniForm.status" :items="uniStatuses.map(value => ({ value, title: translateBackendValue(value) }))" :label="t('educational.caseUi.outcome')" class="mb-3" />
        <VTextField v-model="uniForm.intake" :label="t('educational.caseUi.intakeExample')" clearable class="mb-3" />
        <VTextarea v-model="uniForm.notes" :label="t('common.fields.notes')" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="uniDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="savingUni" :disabled="!uniForm.university_id" @click="saveUni">{{ t('common.actions.add') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="editDialog" max-width="600">
    <VCard :title="t('educational.caseUi.editCase')">
      <VCardText class="case-dialog-body">
        <VSelect v-if="canAssignEmployee" v-model="editForm.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="t('educational.responsibleEmployee')" clearable class="mb-3" />
        <VRow dense>
          <VCol cols="12" sm="6"><VTextField v-model="editForm.father_name" :label="t('educational.fatherName')" clearable class="mb-3" /></VCol>
          <VCol cols="12" sm="6"><VTextField v-model="editForm.father_phone" :label="t('educational.fatherPhone')" clearable class="mb-3" /></VCol>
        </VRow>
        <VRow dense>
          <VCol cols="12" sm="6"><VSelect v-model="editForm.country_id" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="t('educational.destinationCountry')" clearable class="mb-3" /></VCol>
          <VCol cols="12" sm="6"><VSelect v-model="editForm.university_id" :items="universities.filter(u => u.is_active && u.country_id === editForm.country_id).map(u => ({ title: u.name, value: u.id }))" :label="t('educational.caseUi.primaryUniversity')" clearable class="mb-3" /></VCol>
          <VCol cols="12" sm="6"><VSelect v-model="editForm.education_level_id" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="t('educational.educationLevel')" clearable class="mb-3" /></VCol>
          <VCol cols="12" sm="6"><VTextField v-model="editForm.percentage" type="number" min="0" max="100" step="0.01" suffix="%" :label="t('educational.gradePercentage')" clearable class="mb-3" /></VCol>
          <VCol cols="12" sm="6"><VSelect v-model="editForm.language_id" :items="languages.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="t('educational.language')" clearable class="mb-3" /></VCol>
          <VCol cols="12" sm="6"><VSelect v-model="editForm.semester_id" :items="semesters.filter(s => s.is_active).map(s => ({ title: s.name, value: s.id }))" :label="t('educational.semester')" clearable class="mb-3" /></VCol>
        </VRow>
        <VTextField v-model="editForm.application_date" type="date" :label="t('educational.applicationDate')" clearable />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="editDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="savingCase" @click="saveCase">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="portalDialog" max-width="560">
    <VCard :title="t('educational.caseUi.inviteTitle')" :subtitle="t('educational.caseUi.inviteSubtitle')">
      <VCardText class="case-dialog-body">
        <VTextField v-model="portalForm.name" :label="t('educational.caseUi.applicantName')" class="mb-3" />
        <VTextField v-model="portalForm.email" type="email" :label="t('educational.caseUi.applicantEmail')" class="mb-3" />
        <VTextField v-model="portalForm.phone" :label="t('common.fields.phone')" class="mb-3" />
        <VTextarea v-model="portalForm.address" :label="t('common.fields.address')" rows="2" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="portalDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="sendingPortalInvite" :disabled="!portalForm.name.trim() || !portalForm.email.trim()" @click="sendPortalInvite">{{ t('educational.caseUi.sendInvitation') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <ContractDocumentPrinter v-model="contractPrintDialog" :contract="contractToPrint" />
  <VDialog v-model="contractDialog" max-width="760">
    <VCard title="Create visa case contract">
      <VCardText class="case-dialog-body">
        <ContractForm
          :templates="contractTemplates"
          :applicant-country-id="applicant?.country_id ?? null"
          :applicant-id="applicant?.id ?? null"
          @submit="createCaseContract"
          @cancel="contractDialog = false"
        />
      </VCardText>
    </VCard>
  </VDialog>
  <VDialog v-model="financeDialog" max-width="600">
    <VCard title="New financial request">
      <VCardText class="case-dialog-body">
        <VSelect v-model="financeForm.transaction_type" :items="['income', 'expense'].map(value => ({ title: translateBackendValue(value), value }))" label="Transaction type" class="mb-3" />
        <VTextarea v-model="financeForm.reason" label="Reason" class="mb-3" />
        <VTextField v-model.number="financeForm.amount" type="number" min="0.000001" label="Amount" class="mb-3" />
        <VSelect v-model="financeForm.currency_id" :items="financeOptions.currencies.map(row => ({ title: `${row.code} — ${row.name}`, value: row.id }))" label="Currency" class="mb-3" />
        <VSelect v-model="financeForm.employee_id" :items="financeOptions.employees.map(row => ({ title: row.name, value: row.id }))" label="Responsible employee" clearable />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="financeDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="savingFinance" :disabled="!financeForm.reason.trim() || financeForm.amount <= 0 || !financeForm.currency_id" @click="createFinanceRequest">{{ t('common.actions.submit') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="documentDialog" max-width="560">
    <VCard title="Attach case document">
      <VCardText class="case-dialog-body">
        <p class="text-caption text-medium-emphasis mb-4">Documents are stored as input attachments on a case task, so they follow the same access controls and audit trail.</p>
        <VSelect v-model="documentTaskId" :items="(applicant?.tasks ?? []).map(task => ({ title: task.title, value: task.id }))" label="Case task" class="mb-3" />
        <VFileInput v-model="documentFile" label="Document" prepend-icon="tabler-paperclip" accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" show-size />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="documentDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="uploadingDocument" :disabled="!documentTaskId || !documentFile" @click="uploadDocument">Attach</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.visa-case-card :deep(.v-card-title) {
  padding-block: 1.25rem;
  padding-inline: 1.5rem;
}

.visa-case-content {
  padding: 1.5rem;
}

.visa-case-stage-panel {
  max-inline-size: 46rem;
}

.visa-case-content h3 {
  margin-block: 2rem 0.75rem;
}

.visa-case-content h3:first-of-type {
  margin-block-start: 0;
}

.visa-case-content :deep(.v-list) {
  margin-block-end: 1.25rem;
}

.university-application-item :deep(.v-list-item__prepend) {
  align-self: flex-start;
  margin-inline-end: 1rem;
}

.university-application-item :deep(.v-list-item__content) {
  min-inline-size: 0;
}

.case-dialog-body {
  padding: 1.5rem;
}

.case-dialog-body :deep(.v-row) {
  row-gap: 0.5rem;
}

.case-dialog-body :deep(.v-field) {
  margin-block-end: 0.75rem;
}

.case-dialog-body :deep(.v-field:last-child) {
  margin-block-end: 0;
}
</style>
