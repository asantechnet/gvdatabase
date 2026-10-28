<script setup lang="ts">
import { translateApplicantStage, translate } from '@/utils/localization'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { COMPLETED_APPLICANT_STAGE, type Applicant, type ApplicantStage, type BuiltInService, ApplicantUniversityApi, ApplicantAttachmentApi, type ApplicantAttachment, type ApplicantPayload, applicantServicePayload } from '@/api/applicants'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import ApplicantSummary from '@/components/erp/ApplicantSummary.vue'
import ApplicantPortalApi from '@/api/applicantPortal'
import BoardApi from '@/api/board'
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
const canAssignAdmission = computed(() => canAccess('assign_admission_employee'))
const canViewFinance = computed(() => canAccess('view_applicant_financials'))
const canCreatePendingTransactions = computed(() => canAccess('create_pending_transactions'))
const serviceLabel = computed(() => applicant.value ? translateBackendValue(applicant.value.service) : '')
// Shared contact and service details remain visible beside the case title.
const guardianSummary = computed(() => {
  const current = applicant.value
  if (!current) return []
  const rows: Array<{ key: string; label: string; value: string }> = []
  if (current.father_name)
    rows.push({ key: 'father-name', label: t('educational.fatherName'), value: current.father_name })
  if (current.father_phone)
    rows.push({ key: 'father-phone', label: t('educational.fatherPhone'), value: current.father_phone })
  if (current.details?.grade_score !== null && current.details?.grade_score !== undefined)
    rows.push({ key: 'grade-score', label: t('educational.serviceFields.gradeScore'), value: `${n(Number(current.details?.grade_score))}` })
  if (current.details?.passport_id)
    rows.push({ key: 'passport', label: t('educational.serviceFields.passportId'), value: current.details.passport_id })
  for (const [key, catalog] of [['scholarship', 'scholarships'], ['academy_class', 'classes'], ['test', 'tests'], ['program', 'programs'], ['time', 'times'], ['duration', 'durations']] as const) {
    const name = current.details?.[key]?.name
    if (name)
      rows.push({ key, label: t(catalog === 'scholarships' ? 'erp.scholarship' : catalog === 'classes' ? 'erp.class' : `educational.masterData.${catalog}`), value: name })
  }
  for (const [key, label] of [['favorite_subjects', 'educational.serviceFields.favoriteSubjects'], ['skills', 'educational.masterData.skills'], ['teachers', 'educational.masterData.teachers']] as const) {
    if (current[key]?.length)
      rows.push({ key, label: t(label), value: current[key].map(row => row.name).join(', ') })
  }
  return rows
})
const transitionOptions = computed(() => {
  const current = applicant.value?.current_stage
  if (!current) return stages.value
  const configured = stages.value.find(stage => stage.key === current.key)
  // The case sits on a stage that is not in its service's live list (for
  // example an archived stage): never trap it, offer the whole list.
  if (!configured) return stages.value
  // A terminal stage has no allowed next keys on purpose, so an empty map only
  // means "no moves" when the stage itself is in the list.
  return stages.value.filter(stage => (configured.allowed_next_keys ?? []).includes(stage.key))
})
// Terminal stages are the ones that close the case: `completed` finishes it and
// releases commission, while cancelled/rejected/withdrawn end it without one.
// They are listed after the open pipeline in the move field so a closing move is
// never mistaken for a regular step.
const openTransitions = computed(() => transitionOptions.value.filter(stage => !stage.is_terminal))
const terminalTransitions = computed(() => transitionOptions.value.filter(stage => stage.is_terminal))
const transitionItems = computed<Array<Record<string, unknown>>>(() => {
  const items: Array<Record<string, unknown>> = [...openTransitions.value]
  if (openTransitions.value.length && terminalTransitions.value.length)
    items.push({ type: 'divider' })
  if (terminalTransitions.value.length)
    items.push({ type: 'subheader', title: t('educational.caseUi.finalOutcomes') })

  return [...items, ...terminalTransitions.value]
})
/** Terminal options carry a flag icon and say that choosing one closes the case. */
const transitionItemProps = (item: Record<string, unknown>) => item.is_terminal === true
  ? { prependIcon: 'tabler-flag', subtitle: t('educational.caseUi.terminalStageOption') }
  : {}
const caseIsClosed = computed(() => applicant.value?.current_stage?.is_terminal === true)
const caseCompleted = computed(() => applicant.value?.current_stage?.key === COMPLETED_APPLICANT_STAGE)
const terminalDialog = ref(false)
const pendingTerminalStage = ref<ApplicantStage | null>(null)
const pendingIsCompletion = computed(() => pendingTerminalStage.value?.key === 'completed')
const openTasks = computed(() => (applicant.value?.tasks ?? []).filter(t => t.progress < 100))
const doneTasks = computed(() => (applicant.value?.tasks ?? []).filter(t => t.progress >= 100))
const stageHistory = computed(() => [...(applicant.value?.stage_history ?? [])].sort((a, b) => new Date(a.changed_at).getTime() - new Date(b.changed_at).getTime()))
const documents = computed(() => (applicant.value?.tasks ?? []).flatMap(task => (task.attachments ?? []).map(a => ({ ...a, task_id: task.id, task_title: task.title }))))
const postedIncomeTotal = computed(() => (applicant.value?.incomes ?? []).reduce((sum, row) => sum + Number(row.amount || 0), 0))
const postedExpenseTotal = computed(() => (applicant.value?.expenses ?? []).reduce((sum, row) => sum + Number(row.amount || 0), 0))
const money = (value: number | string) => n(Number(value || 0), 'decimal')
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
const semesters = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const admissionEmployees = ref<Employee[]>([])
const uniDialog = ref(false)
const savingUni = ref(false)
const uniForm = ref({ university_id: null as number | null, semester_id: null as number | null, status: 'pending', intake: '', notes: '' })
const uniStatuses = ['pending', 'applied', 'admitted', 'rejected', 'withdrawn']
const statusColor = (status: string) => ({ pending: 'secondary', applied: 'info', admitted: 'success', rejected: 'error', withdrawn: 'warning' }[status] ?? 'secondary')

const editDialog = ref(false)
const savingCase = ref(false)
const editForm = ref({ father_name: '', father_phone: '', backup_phone: '', responsible_employee_id: null as number | null, admission_employee_id: null as number | null })
const serviceForm = ref<ApplicantPayload>({ service: 'scholarship' })
const portalDialog = ref(false)
const sendingPortalInvite = ref(false)
const portalAccessDialog = ref(false)
const updatingPortalAccess = ref(false)
const portalDeleteDialog = ref(false)
const deletingPortalUser = ref(false)
const portalForm = ref({ name: '', email: '', phone: '', address: '', password: '' })
// A supplied password must satisfy the server's minimum; an empty one keeps the
// invitation flow, where the applicant chooses their own.
const portalPasswordValid = computed(() => !portalForm.value.password || portalForm.value.password.length >= 8)
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
  // Stages are service-scoped: an academy or test-offer case must only be
  // offered its own service's pipeline.
  stages.value = await ApplicantApi.stages({ service: applicant.value.service, country_id: applicant.value.details?.country_id, university_id: applicant.value.details?.university_id })
  selected.value = null
  notesDraft.value = applicant.value.notes ?? ''
  applicantAttachments.value = await ApplicantAttachmentApi.list(applicant.value.id)
  await loadLineStages()
}

// Per-service workflow: each contracted line tracks its own service's stage.
const lineStageOptions = ref<Record<string, ApplicantStage[]>>({})
const lineStageSelection = ref<Record<number, number | null>>({})
const movingLine = ref<number | null>(null)
const canMoveLineStage = computed(() => canAccess('change_applicant_stage'))
const lineStageLabel = (stage?: { name: string; key: string } | null) => stage ? translateApplicantStage(stage) : '—'
const lineNextStages = (line: { service?: BuiltInService | null; current_stage?: { allowed_next_keys?: string[] } | null }) => {
  const options = lineStageOptions.value[line.service ?? ''] ?? []
  // A line with no stage yet is seated from its service's whole live list; a
  // seated line only offers its configured next stages.
  if (!line.current_stage)
    return options

  const allowed = line.current_stage.allowed_next_keys ?? []

  return options.filter(stage => allowed.includes(stage.key))
}
async function loadLineStages() {
  const services = [...new Set((applicant.value?.services ?? []).map(line => line.service).filter((service): service is BuiltInService => !!service))]
  await Promise.all(services.map(async service => {
    // Active stages only: an inactive stage must never be offered as a target.
    if (!lineStageOptions.value[service])
      lineStageOptions.value[service] = await ApplicantApi.stages({ service })
  }))
}
async function moveLine(line: { id: number }) {
  const stageId = Number(lineStageSelection.value[line.id])
  if (!Number.isSafeInteger(stageId))
    return
  movingLine.value = line.id
  try {
    await ApplicantApi.changeLineStage(line.id, stageId)
    await load()
    toast.success(t('educational.caseUi.stageRecorded'))
  }
  catch {
    // The shared API error notifier shows the server's message.
  }
  finally {
    movingLine.value = null
  }
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
  // Closing a case is irreversible through the normal workflow and it decides
  // commission, so it is confirmed in a dialog that states the consequence.
  if (target?.is_terminal) {
    pendingTerminalStage.value = target
    terminalDialog.value = true

    return
  }

  await recordStage(target)
}

function cancelTerminalMove() {
  terminalDialog.value = false
  pendingTerminalStage.value = null
}

async function confirmTerminalMove() {
  if (!pendingTerminalStage.value)
    return

  await recordStage(pendingTerminalStage.value)
}

async function recordStage(target: ApplicantStage) {
  moving.value = true
  try {
    applicant.value = await ApplicantApi.changeStage(Number(route.params.id), target.id, note.value)
    selected.value = null
    note.value = ''
    terminalDialog.value = false
    pendingTerminalStage.value = null
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
    backup_phone: applicant.value.backup_phone ?? '',
    responsible_employee_id: applicant.value.responsible_employee_id ?? null,
    admission_employee_id: applicant.value.admission_employee_id ?? null,
  }
  serviceForm.value = { service: applicant.value.service, ...applicant.value.details, favorite_subject_ids: [...(applicant.value.favorite_subject_ids ?? [])], skill_ids: [...(applicant.value.skill_ids ?? [])], teacher_ids: [...(applicant.value.teacher_ids ?? [])] }
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
    password: '',
  }
  portalDialog.value = true
}

async function sendPortalInvite() {
  if (!applicant.value || !portalForm.value.name.trim() || !portalForm.value.email.trim() || !portalPasswordValid.value)
    return

  sendingPortalInvite.value = true
  try {
    const created = portalForm.value.password.length > 0
    await ApplicantPortalApi.invite(applicant.value.id, {
      name: portalForm.value.name.trim(),
      email: portalForm.value.email.trim(),
      phone: portalForm.value.phone.trim() || null,
      address: portalForm.value.address.trim() || null,
      password: created ? portalForm.value.password : null,
    })
    portalDialog.value = false
    await load()
    toast.success(t(created ? 'educational.caseUi.portalAccountCreated' : 'educational.caseUi.invitationSent'))
  }
  catch {
    // The shared API notifier shows the server's specific validation error.
  }
  finally {
    sendingPortalInvite.value = false
  }
}

async function deletePortalUser() {
  if (!applicant.value?.user_id)
    return

  deletingPortalUser.value = true
  try {
    await ApplicantPortalApi.remove(applicant.value.id)
    portalDeleteDialog.value = false
    await load()
    toast.success(t('educational.caseUi.portalUserDeleted'))
  }
  catch {
    // The shared API notifier shows the server's specific message.
  }
  finally {
    deletingPortalUser.value = false
  }
}

async function updatePortalAccess() {
  if (!applicant.value?.user_id)
    return

  updatingPortalAccess.value = true
  try {
    const enabled = applicant.value.portal_access_status !== 'active'
    await ApplicantPortalApi.setAccess(applicant.value.id, enabled)
    portalAccessDialog.value = false
    await load()
    toast.success(t(enabled ? 'educational.caseUi.portalEnabled' : 'educational.caseUi.portalDisabled'))
  }
  finally {
    updatingPortalAccess.value = false
  }
}

async function saveCase() {
  if (!applicant.value)
    return

  savingCase.value = true
  try {
    const payload: ApplicantPayload = { ...applicantServicePayload(serviceForm.value), father_name: editForm.value.father_name || null, father_phone: editForm.value.father_phone || null, backup_phone: editForm.value.backup_phone || null }
    if (canAssignEmployee.value)
      payload.responsible_employee_id = editForm.value.responsible_employee_id
    if (canAssignAdmission.value)
      payload.admission_employee_id = editForm.value.admission_employee_id

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
  uniForm.value = { university_id: null, semester_id: applicant.value?.details?.semester_id ?? null, status: 'pending', intake: '', notes: '' }
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
    toast.error(translate('common.ui.unableToLoadFinancialRequestOptions'))
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
    toast.success(translate('common.ui.financialRequestLinkedToThisVisaCase'))
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
    toast.success(translate('common.ui.documentAttachedToThisVisaCase'))
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
    const [universityRows, countryRows, levelRows, semesterRows, employeeRows, admissionRows] = await Promise.all([
      MasterDataApi.list('universities'),
      MasterDataApi.list('countries'),
      MasterDataApi.list('education-levels'),
      MasterDataApi.list('semesters'),
      EmployeeApi.getOptions(),
      canAssignAdmission.value ? EmployeeApi.getAssignmentOptions('admission') : Promise.resolve([]),
    ])

    universities.value = universityRows
    countries.value = countryRows
    levels.value = levelRows
    semesters.value = semesterRows
    employees.value = employeeRows
    admissionEmployees.value = admissionRows
  }
  catch {
    toast.error(t('educational.caseUi.loadUniversitiesFailed'))
  }
})
</script>
<template>
  <VCard v-if="applicant" class="visa-case-card">
    <VCardTitle class="d-flex flex-wrap align-center ga-2">
      <RouterLink class="case-title-link" :to="{ name: 'all-customers', query: { contact: applicant.id } }" :title="t('educational.openContactInCrm')">{{ applicant.name }}</RouterLink>
      <span class="text-caption text-medium-emphasis">{{ t('educational.caseUi.visaCaseLabel') }}</span>
      <ContactActionMenu v-if="applicant.contact" kind="phone" :value="applicant.contact" contactable-type="applicant" :contactable-id="applicant.id" audience="applicant" :context="applicant" />
      <ContactActionMenu v-if="applicant.email" kind="email" :value="applicant.email" contactable-type="applicant" :contactable-id="applicant.id" />
      <CrmRelationshipDetails type="applicant" :id="applicant.id" :title="applicant.name" />
      <VSpacer />
      <VChip v-if="applicant.user_id" size="small" :color="applicant.portal_access_status === 'active' ? 'success' : 'warning'">{{ t(applicant.portal_access_status === 'active' ? 'educational.caseUi.portalActive' : 'educational.caseUi.portalDisabledStatus') }}</VChip>
      <VBtn v-if="applicant.user_id && canInvitePortal" size="small" variant="tonal" :color="applicant.portal_access_status === 'active' ? 'error' : 'success'" :prepend-icon="applicant.portal_access_status === 'active' ? 'tabler-lock' : 'tabler-lock-open'" @click="portalAccessDialog = true">{{ t(applicant.portal_access_status === 'active' ? 'educational.caseUi.disablePortal' : 'educational.caseUi.enablePortal') }}</VBtn>
      <VBtn v-if="applicant.user_id && canInvitePortal" size="small" variant="text" color="error" prepend-icon="tabler-user-minus" @click="portalDeleteDialog = true">{{ t('educational.caseUi.deletePortalUser') }}</VBtn>
      <VBtn v-if="!applicant.user_id && canInvitePortal" size="small" variant="tonal" color="primary" prepend-icon="tabler-send" @click="openPortalInvite">{{ t('educational.caseUi.invitePortal') }}</VBtn>
      <VBtn v-if="canEditCase" size="small" variant="text" prepend-icon="tabler-pencil" @click="openEditCase">{{ t('educational.caseUi.editCase') }}</VBtn>
    </VCardTitle>
    <VCardSubtitle v-if="guardianSummary.length" class="d-flex flex-wrap ga-4">
      <span v-for="row in guardianSummary" :key="row.key">{{ row.label }}: <strong>{{ row.value }}</strong></span>
    </VCardSubtitle>
    <VCardText class="visa-case-content">
      <div class="visa-case-stage-panel">
      <VChip color="primary">{{ translateApplicantStage(applicant.current_stage) }}</VChip>
      <VChip v-if="openTasks.length" color="warning" size="small" class="ms-2">{{ t('educational.caseUi.openTasks', { count: n(openTasks.length) }) }}</VChip>
      <VAlert v-if="caseIsClosed && !transitionOptions.length" :type="caseCompleted ? 'success' : 'warning'" variant="tonal" density="compact" class="mt-5 mb-0">
        <div>{{ t('educational.caseUi.caseClosedFinal', { stage: translateApplicantStage(applicant.current_stage) }) }}</div>
        <div v-if="caseCompleted" class="mt-1">{{ t('educational.caseUi.completedCommissionNote') }}</div>
      </VAlert>
      <template v-else>
        <VAlert v-if="caseIsClosed" type="info" variant="tonal" density="compact" class="mt-5 mb-0">{{ t('educational.caseUi.caseClosedReopen', { stage: translateApplicantStage(applicant.current_stage) }) }}</VAlert>
        <VSelect
          v-model="selected"
          :items="transitionItems"
          :item-title="translateApplicantStage"
          :item-props="transitionItemProps"
          item-value="id"
          :label="t('educational.caseUi.moveStage')"
          class="mt-5 mb-4"
        >
          <template #subheader="{ props: item }">
            <VListSubheader>{{ item.title }}</VListSubheader>
          </template>
        </VSelect>
        <VTextarea v-model="note" :label="t('educational.caseUi.stageNote')" class="mb-4" />
        <VBtn color="primary" :loading="moving" @click="move">{{ t('educational.caseUi.recordStage') }}</VBtn>
      </template>
      </div>
      <div class="case-overview-grid mt-6">
        <section class="case-section case-section--details">
          <div class="case-section__heading"><div><p class="case-section__eyebrow">{{ t('educational.applicant') }}</p><h3>{{ t('common.crmDetails.profileTitle') }}</h3></div><VChip size="small" variant="tonal">{{ serviceLabel }}</VChip></div>
          <ApplicantSummary :applicant="applicant" />
          <div v-if="guardianSummary.length" class="case-detail-grid mt-4"><div v-for="row in guardianSummary" :key="row.key"><span>{{ row.label }}</span><strong>{{ row.value }}</strong></div></div>
        </section>
        <section class="case-section case-section--workflow">
          <div class="case-section__heading"><div><p class="case-section__eyebrow">{{ t('educational.stage') }}</p><h3>{{ t('educational.caseUi.serviceWorkflow') }}</h3></div></div>
          <div v-for="line in applicant.services" :key="line.id" class="case-service-line"><strong>{{ line.type ?? line.service }}</strong><VChip size="small" color="primary">{{ lineStageLabel(line.current_stage) }}</VChip><template v-if="canMoveLineStage && lineNextStages(line).length"><VSelect v-model="lineStageSelection[line.id]" :items="lineNextStages(line).map(s => ({ title: translateApplicantStage(s), value: s.id }))" density="compact" hide-details :placeholder="t('educational.caseUi.moveStage')"/><VBtn size="small" color="primary" :disabled="!lineStageSelection[line.id]" :loading="movingLine === line.id" @click="moveLine(line)">{{ t('educational.caseUi.recordStage') }}</VBtn></template></div>
          <p v-if="!applicant.services?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.serviceWorkflowHint') }}</p>
        </section>
      </div>

      <section class="case-section case-section--timeline mt-5">
        <div class="case-section__heading"><div><p class="case-section__eyebrow">{{ t('educational.stage') }}</p><h3>{{ t('educational.caseUi.timeline') }}</h3></div></div>
        <VList><VListItem v-for="item in stageHistory" :key="item.id" :title="t('educational.caseUi.stageTransition', { from: item.old_stage ? translateApplicantStage(item.old_stage) : t('educational.caseUi.started'), to: translateApplicantStage(item.new_stage) })" :subtitle="stageSubtitle(item)" /></VList>
      </section>

      <div class="case-content-grid mt-5">
        <section class="case-section">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.tasks') }}</h3><VBtn v-if="canCreateTask" size="small" prepend-icon="tabler-plus" @click="openTaskDialog">{{ t('educational.caseUi.newTask') }}</VBtn></div>
          <p v-if="!canCreateTask" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.taskPermission') }}</p>
          <VList><VListItem v-for="task in openTasks" :key="task.id" :title="task.title" :subtitle="taskSummary(task)"><template #append><VBtn size="small" variant="text" color="success" :loading="completingId === task.id" @click="completeTask(task.id)">{{ t('educational.caseUi.complete') }}</VBtn></template></VListItem><VListItem v-for="task in doneTasks" :key="`done-${task.id}`" :title="task.title" :subtitle="t('educational.caseUi.complete')"><template #prepend><VIcon icon="tabler-check" color="success" /></template></VListItem></VList>
          <p v-if="!applicant.tasks?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.noTasks') }}</p>
        </section>

        <section v-if="['scholarship', 'private'].includes(applicant.service)" class="case-section">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.universityApplications') }}</h3><VBtn v-if="canEditCase" size="small" prepend-icon="tabler-plus" @click="openUniDialog">{{ t('educational.caseUi.addApplication') }}</VBtn></div>
          <p v-if="applicant.details?.university" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.primaryChoice', { university: applicant.details?.university.name }) }}</p>
          <VList><VListItem v-for="app in applicant.universities" :key="app.id" class="university-application-item" :title="app.university?.name ?? t('educational.caseUi.universityNumber', { id: n(app.university_id) })" :subtitle="t('educational.caseUi.applicationSummary', { status: translateBackendValue(app.status), intake: app.intake || t('common.messages.notAvailable'), notes: app.notes || t('common.messages.notAvailable') })"><template #prepend><VChip size="small" :color="statusColor(app.status)">{{ translateBackendValue(app.status) }}</VChip></template><template v-if="canEditCase" #append><VMenu><template #activator="{ props }"><VBtn size="small" variant="text" v-bind="props">{{ t('common.fields.status') }}</VBtn></template><VList density="compact"><VListItem v-for="s in uniStatuses" :key="s" :title="translateBackendValue(s)" @click="setUniStatus(app.id, s)" /></VList></VMenu><VBtn v-if="app.university_id !== applicant.details?.university_id" size="small" variant="text" @click="setPrimaryUniversity(app.university_id)">{{ t('educational.caseUi.primary') }}</VBtn><VBtn size="small" variant="text" color="error" @click="removeUni(app.id)">{{ t('common.actions.remove') }}</VBtn></template></VListItem></VList>
          <p v-if="!applicant.universities?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.noApplications') }}</p>
        </section>

        <section v-if="canViewFinance" class="case-section">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.finance') }}</h3><div class="d-flex flex-wrap justify-end ga-1"><VBtn v-if="canCreatePendingTransactions" size="small" prepend-icon="tabler-plus" @click="openFinanceDialog">{{ $t('common.ui.newFinancialRequest') }}</VBtn><VBtn size="small" variant="text" :to="{ name: 'payment-requests' }">{{ t('educational.caseUi.pendingWorkspace') }}</VBtn></div></div>
          <p class="text-caption text-medium-emphasis">{{ t('educational.caseUi.financeSummary', { income: money(postedIncomeTotal), expenses: money(postedExpenseTotal) }) }}</p>
          <VList><VListItem v-for="row in applicant.pending_transactions" :key="`pt-${row.id}`" :title="t('educational.caseUi.transactionTitle', { type: translateBackendValue(row.transaction_type), reason: row.reason })" :subtitle="t('educational.caseUi.transactionSummary', { amount: money(row.amount), status: translateBackendValue(row.approval_status), rejection: row.rejection_reason || t('common.messages.notAvailable') })" /></VList>
          <VList><VListItem v-for="row in applicant.incomes" :key="`in-${row.id}`" :title="t('educational.caseUi.incomePosted')" :subtitle="t('educational.caseUi.postedSummary', { amount: money(row.amount), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), description: row.description || t('common.messages.notAvailable') })"><template #prepend><VIcon icon="tabler-arrow-down-left" color="success" /></template></VListItem><VListItem v-for="row in applicant.expenses" :key="`ex-${row.id}`" :title="t('educational.caseUi.expensePosted')" :subtitle="t('educational.caseUi.postedSummary', { amount: money(row.amount), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), description: row.description || t('common.messages.notAvailable') })"><template #prepend><VIcon icon="tabler-arrow-up-right" color="error" /></template></VListItem></VList>
          <p v-if="!applicant.pending_transactions?.length && !applicant.incomes?.length && !applicant.expenses?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.noFinance') }}</p>
        </section>

        <section class="case-section case-section--documents">
          <div class="case-section__heading"><div><h3>{{ t('educational.caseUi.documents') }}</h3><p class="text-caption text-medium-emphasis mb-0">{{ applicantAttachments.length ? t('educational.caseUi.applicantDocumentsReady', { count: applicantAttachments.length }) : t('educational.caseUi.applicantDocumentsNeeded') }}</p></div><VBtn v-if="canAttachDocuments" size="small" prepend-icon="tabler-paperclip" @click="openDocumentDialog">{{ $t('common.ui.attachDocument') }}</VBtn></div>
          <h4>{{ t('educational.caseUi.applicantDocuments') }}</h4>
          <VList density="compact"><VListItem v-for="file in applicantAttachments" :key="file.id" :title="file.label" :subtitle="file.name"><template #append><VBtn icon="tabler-download" size="x-small" variant="text" :href="file.download_url"/><VBtn v-if="canEditCase" icon="tabler-trash" size="x-small" variant="text" color="error" @click="removeApplicantAttachment(file.id)"/></template></VListItem></VList>
          <div v-if="canEditCase" class="case-document-upload"><VTextField v-model="attachmentLabel" :label="t('educational.caseUi.documentLabel')" density="compact" hide-details/><VFileInput v-model="attachmentFile" density="compact" hide-details/><VBtn color="primary" :loading="uploadingApplicantAttachment" :disabled="!attachmentFile || !attachmentLabel.trim()" @click="uploadApplicantAttachment">{{ t('educational.caseUi.attach') }}</VBtn></div>
          <VDivider class="my-4" />
          <VList><VListItem v-for="doc in documents" :key="doc.id" :title="doc.original_name" :subtitle="t('educational.caseUi.fromTask', { task: doc.task_title })"><template #append><VBtn size="small" variant="text" :loading="downloadingId === doc.id" @click="downloadDoc(doc.task_id, doc.id, doc.original_name)">{{ t('educational.caseUi.download') }}</VBtn></template></VListItem></VList>
          <p v-if="!documents.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.noDocuments') }}</p>
        </section>

        <section class="case-section case-section--notes">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.notes') }}</h3></div>
          <VTextarea v-model="notesDraft" :label="t('educational.caseUi.caseNotes')" :disabled="!canEditCase" class="mb-4" />
          <VBtn v-if="canEditCase" color="primary" :loading="savingNotes" @click="saveNotes">{{ t('educational.caseUi.saveNotes') }}</VBtn>
          <p v-else class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.notesPermission') }}</p>
        </section>
      </div>
    </VCardText>
  </VCard>
  <VDialog v-model="terminalDialog" max-width="520">
    <VCard :title="t('educational.caseUi.terminalConfirmTitle')">
      <VCardText>
        <VAlert :type="pendingIsCompletion ? 'success' : 'warning'" variant="tonal" density="compact" class="mb-4">
          {{ pendingIsCompletion
            ? t('educational.caseUi.completedMoveNote', { stage: translateApplicantStage(pendingTerminalStage) })
            : t('educational.caseUi.exitMoveNote', { stage: translateApplicantStage(pendingTerminalStage) }) }}
        </VAlert>
        <p class="mb-0">{{ t('educational.caseUi.terminalConfirm', { stage: translateApplicantStage(pendingTerminalStage) }) }}</p>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="cancelTerminalMove">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="moving" @click="confirmTerminalMove">{{ t('educational.caseUi.recordStage') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
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
        <VSelect v-model="uniForm.university_id" :items="universities.filter(u => u.is_active && u.country_id === applicant?.details?.country_id).map(u => ({ title: u.name, value: u.id }))" :label="t('educational.university')" class="mb-3" />
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
  <VDialog v-model="editDialog" max-width="1100" class="erp-form">
    <VCard :title="t('educational.caseUi.editCase')">
      <VCardText class="case-dialog-body">
        <ErpField kind="select" v-if="canAssignEmployee && applicant?.service !== 'academy'" v-model="editForm.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="t('educational.responsibleEmployee')" clearable class="mb-3" />
        <ErpField kind="select" v-if="canAssignAdmission && applicant?.service !== 'academy'" v-model="editForm.admission_employee_id" :items="admissionEmployees.map(e => ({ title: e.name, value: e.id }))" :label="t('agencyStaff.admission')" clearable class="mb-3" />
        <VRow dense>
          <VCol cols="12" sm="6"><ErpField v-model="editForm.father_name" :label="t('educational.fatherName')" clearable class="mb-3" /></VCol>
          <VCol cols="12" sm="6"><ErpField v-model="editForm.father_phone" :label="t('educational.fatherPhone')" clearable class="mb-3" /></VCol>
        </VRow>
        <ErpField v-if="applicant?.service === 'test_offer'" v-model="editForm.backup_phone" :label="t('erp.backupPhone')" class="mb-3" />
        <ApplicantServiceFields v-model="serviceForm" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="tonal" @click="editDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" variant="flat" :loading="savingCase" @click="saveCase">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="portalAccessDialog" max-width="520">
    <VCard :title="t(applicant?.portal_access_status === 'active' ? 'educational.caseUi.disablePortal' : 'educational.caseUi.enablePortal')">
      <VCardText>{{ t(applicant?.portal_access_status === 'active' ? 'educational.caseUi.disablePortalConfirm' : 'educational.caseUi.enablePortalConfirm') }}</VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="portalAccessDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn :color="applicant?.portal_access_status === 'active' ? 'error' : 'success'" :loading="updatingPortalAccess" @click="updatePortalAccess">{{ t('common.actions.confirm') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="portalDialog" max-width="560">
    <VCard :title="t('educational.caseUi.inviteTitle')" :subtitle="t('educational.caseUi.inviteSubtitle')">
      <VCardText class="case-dialog-body">
        <VTextField v-model="portalForm.name" :label="t('educational.caseUi.applicantName')" class="mb-3" />
        <VTextField v-model="portalForm.email" type="email" :label="t('educational.caseUi.applicantEmail')" class="mb-3" />
        <VTextField
          v-model="portalForm.password"
          type="password"
          autocomplete="new-password"
          :label="t('educational.caseUi.portalPassword')"
          :hint="t('educational.caseUi.portalPasswordHint')"
          :error-messages="portalPasswordValid ? [] : [t('educational.caseUi.portalPasswordTooShort')]"
          persistent-hint
          class="mb-4"
        />
        <VTextField v-model="portalForm.phone" :label="t('common.fields.phone')" class="mb-3" />
        <VTextarea v-model="portalForm.address" :label="t('common.fields.address')" rows="2" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="portalDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="sendingPortalInvite" :disabled="!portalForm.name.trim() || !portalForm.email.trim() || !portalPasswordValid" @click="sendPortalInvite">{{ t(portalForm.password ? 'educational.caseUi.createPortalAccount' : 'educational.caseUi.sendInvitation') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="portalDeleteDialog" max-width="520">
    <VCard :title="t('educational.caseUi.deletePortalUser')">
      <VCardText>{{ t('educational.caseUi.deletePortalUserConfirm', { name: applicant?.name }) }}</VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="portalDeleteDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="error" :loading="deletingPortalUser" @click="deletePortalUser">{{ t('common.actions.delete') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="financeDialog" max-width="600">
    <VCard :title="$t('common.ui.newFinancialRequest')">
      <VCardText class="case-dialog-body">
        <VSelect v-model="financeForm.transaction_type" :items="['income', 'expense'].map(value => ({ title: translateBackendValue(value), value }))" :label="$t('educational.transactionType')" class="mb-3" />
        <VTextarea v-model="financeForm.reason" :label="$t('common.fields.reason')" class="mb-3" />
        <VTextField v-model.number="financeForm.amount" type="number" min="0.000001" :label="$t('common.fields.amount')" class="mb-3" />
        <VSelect v-model="financeForm.currency_id" :items="financeOptions.currencies.map(row => ({ title: `${row.code} — ${row.name}`, value: row.id }))" :label="$t('serviceContracts.currency')" class="mb-3" />
        <VSelect v-model="financeForm.employee_id" :items="financeOptions.employees.map(row => ({ title: row.name, value: row.id }))" :label="$t('serviceContracts.responsibleEmployee')" clearable />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="financeDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="savingFinance" :disabled="!financeForm.reason.trim() || financeForm.amount <= 0 || !financeForm.currency_id" @click="createFinanceRequest">{{ t('common.actions.submit') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="documentDialog" max-width="560">
    <VCard :title="$t('common.ui.attachCaseDocument')">
      <VCardText class="case-dialog-body">
        <p class="text-caption text-medium-emphasis mb-4">{{ $t('common.ui.documentsAreStoredAsInputAttachmentsOnACaseTaskSoTheyFollowTheSameAccessControlsAndAuditTrail') }}</p>
        <VSelect v-model="documentTaskId" :items="(applicant?.tasks ?? []).map(task => ({ title: task.title, value: task.id }))" :label="$t('common.ui.caseTask')" class="mb-3" />
        <VFileInput v-model="documentFile" :label="$t('common.ui.document')" prepend-icon="tabler-paperclip" accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" show-size />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="documentDialog = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="uploadingDocument" :disabled="!documentTaskId || !documentFile" @click="uploadDocument">{{ $t('components.taskManager.todomatrixview.attach') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.case-title-link {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  text-decoration: none;
}

.case-title-link:hover {
  text-decoration: underline;
}

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

.case-overview-grid,
.case-content-grid {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.case-section {
  background: rgb(var(--v-theme-on-surface) / 0.035);
  border: 1px solid rgb(var(--v-border-color) / var(--v-border-opacity));
  border-radius: 0.75rem;
  min-inline-size: 0;
  padding: clamp(1rem, 2vw, 1.5rem);
}

.case-section--timeline {
  background: rgb(var(--v-theme-on-surface) / 0.06);
}

.case-section--details,
.case-section--workflow {
  min-block-size: 100%;
}

.case-section__heading {
  align-items: flex-start;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  margin-block-end: 1rem;
}

.case-section__heading h3,
.case-section__heading h4,
.case-section h4 {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  line-height: 1.4;
  margin: 0;
}

.case-section h4 {
  font-size: 0.875rem;
  margin-block: 1rem 0.5rem;
}

.case-section__eyebrow {
  color: rgb(var(--v-theme-on-surface) / var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  margin: 0 0 0.25rem;
  text-transform: uppercase;
}

.case-detail-grid {
  display: grid;
  gap: 0.75rem 1rem;
  grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
}

.case-detail-grid > div {
  display: grid;
  gap: 0.2rem;
}

.case-detail-grid span {
  color: rgb(var(--v-theme-on-surface) / var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
}

.case-service-line {
  align-items: center;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(8rem, 1fr) auto minmax(11rem, 1fr) auto;
  padding-block: 0.75rem;
}

.case-service-line + .case-service-line {
  border-block-start: 1px solid rgb(var(--v-border-color) / var(--v-border-opacity));
}

.case-section :deep(.v-list) {
  background: transparent;
}

.case-document-upload {
  align-items: center;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(10rem, 1fr) minmax(12rem, 1fr) auto;
  margin-block-start: 1rem;
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

@media (max-width: 959px) {
  .case-overview-grid,
  .case-content-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 599px) {
  .visa-case-content {
    padding: 1rem;
  }

  .case-section {
    padding: 1rem;
  }

  .case-service-line,
  .case-document-upload {
    grid-template-columns: 1fr;
  }

  .case-section__heading > :last-child:not(:only-child) {
    inline-size: 100%;
  }
}
</style>
