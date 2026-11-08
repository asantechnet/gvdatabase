<script setup lang="ts">
import { translateApplicantStage, translate } from '@/utils/localization'
import ErpField from '@/components/erp/ErpField.vue'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { COMPLETED_APPLICANT_STAGE, type Applicant, type ApplicantStage, ApplicantUniversityApi, ApplicantAttachmentApi, type ApplicantAttachment, type ApplicantPayload, applicantServicePayload } from '@/api/applicants'
import { externalUrl } from '@/utils/externalUrl'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import ApplicantSummary from '@/components/erp/ApplicantSummary.vue'
import ApplicantPortalApi from '@/api/applicantPortal'
import BoardApi from '@/api/board'
import EmployeeApi, { type Employee } from '@/api/employee'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import { approveExpense, createExpense, rejectExpense } from '@/api/expenses'
import { approveIncome, createIncome, rejectIncome } from '@/api/incomes'
import { getFinanceTypes, type FinanceType } from '@/api/financeTypes'
import { financeTypeLabel } from '@/utils/financeTypes'
import CurrencyApi, { type Currency } from '@/api/currency'
import TaskApi from '@/api/task'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate, translateBackendValue } from '@/utils/localization'
import { confirmActionWarning } from '@/composables/useActionWarning'

const toast = useToast()
const route = useRoute()
const { t, n } = useI18n()
const applicant = ref<Applicant | null>(null)
const caseLoading = ref(true)
const stages = ref<ApplicantStage[]>([])
// Vuetify can preserve an item value as a string after a component remount.
// Keep the UI tolerant of that, but always convert it before posting to the
// integer-only workflow endpoint.
const selected = ref<number | string | null>(null)
const note = ref('')
const moving = ref(false)

const boards = ref<Array<{ id: string; title: string; is_personal: boolean; is_owned: boolean }>>([])
const taskDialog = ref(false)
const creatingTask = ref(false)
const completingId = ref<number | null>(null)
const taskForm = ref({ board_id: '', title: '', priority: 'medium' as 'low' | 'medium' | 'high' | 'urgent', due_date: '' })

const personalBoard = computed(() => boards.value.find(board => board.is_personal && board.is_owned) ?? null)
const canCreateTask = computed(() => canAccess('create_tasks') && !!personalBoard.value)
const canEditCase = computed(() => canAccess('edit_applicants'))
const canInvitePortal = computed(() => canAccess('invite_applicant_portal'))
const canAssignEmployee = computed(() => canAccess('assign_applicant_employee'))
const canAssignAdmission = computed(() => canAccess('assign_admission_employee'))
const canViewFinance = computed(() => canAccess('view_applicant_financials'))
const canCreateDraftFinance = computed(() => canAccess('create_draft_finance_records'))
const canApproveDrafts = computed(() => canAccess('approve_draft_finance_records'))
// Service names are catalogue labels, not backend status enums: use the same
// translated labels as the applications list instead of the raw `test_offer`.
const serviceLabelKeys: Record<string, string> = { scholarship: 'scholarship', private: 'private', test_offer: 'testOffer', academy: 'academy' }
const serviceLabel = computed(() => {
  const service = applicant.value?.service
  if (!service) return ''
  const key = serviceLabelKeys[service]

  return key ? t(`educational.builtInServices.${key}`) : translateBackendValue(service)
})
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

type CaseTask = NonNullable<Applicant['tasks']>[number]

// A contract line owns its delivery task, and a line that re-enters the
// pipeline gets a new task with the same title. Name the line and the
// succession so two identical titles read as one piece of work.
const taskLineLabel = (task: CaseTask) => {
  const line = (applicant.value?.services ?? []).find(row => row.id === task.applicant_contract_id)

  if (!line)
    return t('educational.caseUi.taskLineUnknown')

  return line.contract_number ? `${line.type} · ${line.contract_number}` : line.type
}
const taskSuccession = (task: CaseTask) => {
  const siblings = (applicant.value?.tasks ?? []).filter(row => row.title === task.title)

  if (siblings.length < 2)
    return ''

  if (task.progress < 100) {
    const previous = siblings
      .filter(row => row.progress >= 100 && row.id < task.id)
      .sort((a, b) => b.id - a.id)[0]

    return previous ? t('educational.caseUi.taskSuccessor', { id: previous.id }) : ''
  }

  const next = siblings
    .filter(row => row.progress < 100 && row.id > task.id)
    .sort((a, b) => a.id - b.id)[0]

  return next ? t('educational.caseUi.taskPredecessor', { id: next.id }) : ''
}
// The server derives progress from subtasks and refuses a completion whose
// predecessors are unfinished. Say so before the click, not as a toast.
const taskCompleteBlock = (task: CaseTask) => {
  if ((task.subtasks_count ?? 0) > 0)
    return t('educational.caseUi.taskCompleteViaSubtasks', { open: n(task.open_subtasks_count ?? 0), total: n(task.subtasks_count ?? 0) })

  if ((task.incomplete_dependencies_count ?? 0) > 0)
    return t('educational.caseUi.taskCompleteBlockedDependencies', { count: n(task.incomplete_dependencies_count ?? 0) })

  return ''
}
const taskSubtitle = (task: CaseTask) => [
  taskSummary(task),
  taskLineLabel(task),
  taskSuccession(task),
  // A case closed while delivery work is still open must explain itself rather
  // than leaving an orphan task on the screen without a reason.
  caseIsClosed.value && task.progress < 100
    ? t('educational.caseUi.taskOpenOnClosedCase', { stage: translateApplicantStage(applicant.value?.current_stage) })
    : '',
].filter(Boolean).join(' · ')
const stageHistory = computed(() => [...(applicant.value?.stage_history ?? [])].sort((a, b) => new Date(a.changed_at).getTime() - new Date(b.changed_at).getTime()))
const documents = computed(() => (applicant.value?.tasks ?? []).flatMap(task => (task.attachments ?? []).map(a => ({ ...a, task_id: task.id, task_title: task.title }))))
const money = (value: number | string) => n(Number(value || 0), 'decimal')
const formatDate = (value?: string | null) => value ? formatLocalizedDate(value, 'short') : ''
const formatDateTime = (value?: string | null) => value ? formatLocalizedDate(value, 'long') : ''
const stageSubtitle = (item: {
  changed_at: string
  note?: string | null
  changer?: { name: string } | null
  contract_number?: string | null
  contract_role?: string | null
  is_case_level?: boolean
}) => {
  const parts = [formatDateTime(item.changed_at)]

  // Name the workflow this row belongs to before anything else: two lines on
  // one case must never read as a single sequence.
  if (item.contract_number)
    parts.push(`${item.contract_number}${item.contract_role === 'extension' ? ` (${t('serviceContracts.extension')})` : ''}`)
  else if (item.is_case_level)
    parts.push(t('educational.caseUi.caseLevelMove'))

  // A permanent history row names who moved the case and why.
  if (item.changer?.name)
    parts.push(item.changer.name)
  if (item.note?.trim())
    parts.push(item.note.trim())

  return parts.join(' · ')
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
const semesters = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const admissionEmployees = ref<Employee[]>([])
const uniDialog = ref(false)
const savingUni = ref(false)
const uniForm = ref({ university_id: null as number | null, semester_id: null as number | null, status: 'pending', intake: '', notes: '' })
const uniStatuses = ['pending', 'applied', 'admitted', 'rejected', 'withdrawn']
const statusColor = (status: string) => ({ pending: 'secondary', applied: 'info', admitted: 'success', rejected: 'error', withdrawn: 'warning' }[status] ?? 'secondary')
type CaseUniversityApplication = NonNullable<Applicant['universities']>[number]
const uniNotesDialog = ref(false)
const uniNotesTarget = ref<CaseUniversityApplication | null>(null)
const uniNotesDraft = ref('')
const savingUniNotes = ref(false)
// The case's destination country carries the embassy contact details used by
// the quick-contact block. It comes from the same catalogue the form edits.
const caseCountry = computed(() => {
  const countryId = applicant.value?.details?.country_id

  return countryId ? countries.value.find(row => row.id === countryId) ?? null : null
})
const universityById = computed(() => new Map<number, MasterDataRecord>(universities.value.map(row => [row.id, row] as const)))
const universityContactFor = (id: number) => universityById.value.get(id) ?? null
const portalHref = (value?: string | null) => externalUrl(value)

function openUniNotes(app: CaseUniversityApplication) {
  uniNotesTarget.value = app
  uniNotesDraft.value = app.notes ?? ''
  uniNotesDialog.value = true
}

async function saveUniNotes() {
  if (!applicant.value || !uniNotesTarget.value)
    return

  savingUniNotes.value = true
  try {
    await ApplicantUniversityApi.update(applicant.value.id, uniNotesTarget.value.id, { notes: uniNotesDraft.value.trim() || null })
    uniNotesDialog.value = false
    uniNotesTarget.value = null
    await load()
    toast.success(t('educational.caseUi.universityNotesSaved'))
  }
  catch {
    toast.error(t('educational.caseUi.universityNotesSaveFailed'))
  }
  finally {
    savingUniNotes.value = false
  }
}

const editDialog = ref(false)
const savingCase = ref(false)
const editStep = ref<1 | 2>(1)
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
type DraftFinanceRow = {
  id: number
  kind: 'income' | 'expense'
  amount: number | string
  date?: string | null
  description?: string | null
  status: 'draft' | 'approved' | 'rejected'
  rejection_reason?: string | null
}

const financeDialog = ref(false)
const savingFinance = ref(false)
const rejectFinanceDialog = ref(false)
const savingRejectFinance = ref(false)
const rejectFinanceTarget = ref<DraftFinanceRow | null>(null)
const rejectFinanceReason = ref('')
const financeOptions = ref<{ currencies: Currency[]; types: FinanceType[] }>({ currencies: [], types: [] })
const financeForm = ref({ transaction_type: 'income' as 'income' | 'expense', type: '', description: '', amount: 0, currency_id: null as number | null })
const financeTypeOptions = computed(() => financeOptions.value.types.map(type => ({ title: financeTypeLabel(type), value: type.value })))
const documentDialog = ref(false)
const uploadingDocument = ref(false)
const documentTaskId = ref<number | null>(null)
const documentDestination = ref<'applicant' | 'task'>('task')
const documentLabel = ref('')
const documentFile = ref<File | null>(null)
const applicantAttachments = ref<ApplicantAttachment[]>([])
const canAttachDocuments = computed(() => (applicant.value?.tasks?.length ?? 0) > 0)
const canOpenDocumentDialog = computed(() => canEditCase.value || canAttachDocuments.value)
const documentTargetOptions = computed(() => [
  ...(canEditCase.value ? [{ title: t('educational.caseUi.applicantDocuments'), value: 'applicant' as const }] : []),
  ...(canAttachDocuments.value ? [{ title: t('common.ui.caseTask'), value: 'task' as const }] : []),
])
const documentFileInput = ref<HTMLInputElement | null>(null)
const documentPreviewDialog = ref(false)
const documentPreviewUrl = ref('')
const documentPreviewName = ref('')
const documentPreviewIsImage = ref(false)
const previewLoading = ref(false)

const dockDialogCalendar = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
    instance.calendarContainer.style.setProperty('z-index', '2400', 'important')
  })
}

async function load() {
  caseLoading.value = true
  try {
    applicant.value = await ApplicantApi.get(Number(route.params.id))
    // Stages are service-scoped: an academy or test-offer case must only be
    // offered its own service's pipeline.
    stages.value = await ApplicantApi.stages({ service: applicant.value.service, country_id: applicant.value.details?.country_id, university_id: applicant.value.details?.university_id })
    selected.value = null
    notesDraft.value = applicant.value.notes ?? ''
    applicantAttachments.value = await ApplicantAttachmentApi.list(applicant.value.id)
  }
  finally {
    caseLoading.value = false
  }
}

// One grant decides whether the case-level writer is offered. A lined applicant
// has no writer here: each service case is moved from its own page.
const canMoveStage = computed(() => canAccess('change_applicant_stage'))

async function removeApplicantAttachment(id: number) {
  if (!applicant.value || !await confirmActionWarning({
    key: `delete-applicant-attachment-${id}`,
    title: t('educational.caseUi.removeDocumentTitle'),
    message: t('educational.caseUi.removeDocumentConfirm'),
    confirmText: t('common.actions.remove'),
    cancelText: t('common.actions.cancel'),
    icon: 'tabler-trash',
    tone: 'error',
  })) return

  await ApplicantAttachmentApi.remove(applicant.value.id, id)
  applicantAttachments.value = applicantAttachments.value.filter(item => item.id !== id)
}

async function loadBoards() {
  try {
    const response = await BoardApi.getAll()
    const rows = (response as { data?: { data?: Array<{ id: string; title: string; is_personal?: boolean; is_owned?: boolean }> } }).data?.data ?? []

    boards.value = rows.map(b => ({
      id: String(b.id),
      title: b.title,
      is_personal: !!b.is_personal,
      is_owned: !!b.is_owned,
    }))
    if (!taskForm.value.board_id && personalBoard.value)
      taskForm.value.board_id = personalBoard.value.id
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
  if (!personalBoard.value) {
    toast.error(t('educational.caseUi.personalBoardMissing'))

    return
  }

  taskForm.value = { board_id: personalBoard.value.id, title: '', priority: 'medium', due_date: '' }
  taskDialog.value = true
}

async function createTask() {
  if (!personalBoard.value || !taskForm.value.title.trim() || !applicant.value)
    return

  taskForm.value.board_id = personalBoard.value.id

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

async function downloadApplicantDocument(attachmentId: number, filename: string) {
  if (!applicant.value)
    return

  downloadingId.value = attachmentId
  try {
    const response = await ApplicantAttachmentApi.download(applicant.value.id, attachmentId)
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

function showDocumentPreview(url: string, name: string) {
  if (documentPreviewUrl.value.startsWith('blob:'))
    URL.revokeObjectURL(documentPreviewUrl.value)

  documentPreviewUrl.value = url
  documentPreviewName.value = name
  documentPreviewIsImage.value = /\.(png|jpe?g|webp)$/i.test(name)
  documentPreviewDialog.value = true
}

async function previewApplicantDocument(attachmentId: number, filename: string) {
  if (!applicant.value)
    return

  previewLoading.value = true
  try {
    const response = await ApplicantAttachmentApi.download(applicant.value.id, attachmentId)
    showDocumentPreview(URL.createObjectURL(response.data as Blob), filename)
  }
  catch {
    toast.error(t('educational.caseUi.downloadFailed'))
  }
  finally {
    previewLoading.value = false
  }
}

async function previewTaskDocument(taskId: number, attachmentId: number, filename: string) {
  previewLoading.value = true
  try {
    const response = await TaskApi.downloadAttachment(String(taskId), String(attachmentId))
    showDocumentPreview(URL.createObjectURL(response.data as Blob), filename)
  }
  catch {
    toast.error(t('educational.caseUi.downloadFailed'))
  }
  finally {
    previewLoading.value = false
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
  editStep.value = 1
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
  if (!applicant.value || !await confirmActionWarning({
    key: `delete-applicant-university-${id}`,
    title: t('educational.caseUi.removeUniversityTitle'),
    message: t('educational.caseUi.removeUniversityConfirm'),
    confirmText: t('common.actions.remove'),
    cancelText: t('common.actions.cancel'),
    icon: 'tabler-trash',
    tone: 'error',
  }))
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

async function openFinanceDialog(transactionType: 'income' | 'expense') {
  if (!applicant.value)
    return

  try {
    const [currencyResponse, types] = await Promise.all([
      CurrencyApi.getAll(),
      getFinanceTypes(transactionType),
    ])
    financeOptions.value = { currencies: currencyResponse.data, types }
    financeForm.value = {
      transaction_type: transactionType,
      type: types[0]?.value ?? '',
      description: '',
      amount: 0,
      currency_id: currencyResponse.data.find(currency => currency.isBase)?.id ?? null,
    }
    financeDialog.value = true
  }
  catch {
    toast.error(translate('common.ui.unableToLoadFinancialRequestOptions'))
  }
}

async function createFinanceRequest() {
  if (!applicant.value || !financeForm.value.type || !financeForm.value.description.trim() || financeForm.value.amount <= 0 || !financeForm.value.currency_id)
    return

  const currency = financeOptions.value.currencies.find(row => row.id === financeForm.value.currency_id)
  savingFinance.value = true
  try {
    const payload = {
      applicant_id: applicant.value.id,
      type: financeForm.value.type,
      description: financeForm.value.description.trim(),
      amount: financeForm.value.amount,
      quantity: 1,
      currency_id: financeForm.value.currency_id,
      rate: Number(currency?.rate ?? 1),
      date: new Date().toISOString().slice(0, 10),
      status: 'draft' as const,
    }

    if (financeForm.value.transaction_type === 'income')
      await createIncome(payload)
    else
      await createExpense(payload)

    financeDialog.value = false
    await load()
    toast.success(t('finance.drafts.draftSaved'))
  }
  catch {
    // The shared API notifier displays the server's specific validation error.
  }
  finally {
    savingFinance.value = false
  }
}

async function approveDraftFinance(row: DraftFinanceRow) {
  try {
    if (row.kind === 'income')
      await approveIncome(row.id)
    else
      await approveExpense(row.id)

    await load()
    toast.success(translate('finance.drafts.approved'))
  }
  catch {
    toast.error(translate('finance.drafts.approveFailed'))
  }
}

function openRejectDraft(row: DraftFinanceRow) {
  rejectFinanceTarget.value = row
  rejectFinanceReason.value = ''
  rejectFinanceDialog.value = true
}

async function rejectDraftFinance() {
  if (!rejectFinanceTarget.value || !rejectFinanceReason.value.trim())
    return

  savingRejectFinance.value = true
  try {
    if (rejectFinanceTarget.value.kind === 'income')
      await rejectIncome(rejectFinanceTarget.value.id, rejectFinanceReason.value.trim())
    else
      await rejectExpense(rejectFinanceTarget.value.id, rejectFinanceReason.value.trim())

    rejectFinanceDialog.value = false
    await load()
    toast.success(translate('finance.drafts.rejected'))
  }
  catch {
    toast.error(translate('finance.drafts.rejectFailed'))
  }
  finally {
    savingRejectFinance.value = false
  }
}

function openDocumentDialog() {
  documentTaskId.value = applicant.value?.tasks?.[0]?.id ?? null
  documentDestination.value = canAttachDocuments.value ? 'task' : 'applicant'
  documentLabel.value = ''
  documentFile.value = null
  if (documentFileInput.value)
    documentFileInput.value.value = ''
  documentDialog.value = true
}

function chooseDocumentFile() {
  documentFileInput.value?.click()
}

function selectDocumentFile(event: Event) {
  documentFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

async function uploadDocument() {
  if (!applicant.value || !documentFile.value)
    return

  uploadingDocument.value = true
  try {
    if (documentDestination.value === 'applicant') {
      if (!documentLabel.value.trim())
        return

      applicantAttachments.value.unshift(await ApplicantAttachmentApi.upload(applicant.value.id, documentFile.value, documentLabel.value.trim()))
      toast.success(t('educational.caseUi.documentAdded'))
    }
    else {
      if (!documentTaskId.value)
        return

      // Reuse the task's input-attachment flow: documents therefore retain
      // the same access controls, audit trail, download handling, and file
      // limits as the assigned case task.
      await TaskApi.uploadAttachment(String(documentTaskId.value), documentFile.value, 'input')
      await load()
      toast.success(translate('common.ui.documentAttachedToThisVisaCase'))
    }

    documentDialog.value = false
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
    const [universityRows, semesterRows, employeeRows, admissionRows] = await Promise.all([
      MasterDataApi.list('universities'),
      MasterDataApi.list('semesters'),
      EmployeeApi.getOptions(),
      canAssignAdmission.value ? EmployeeApi.getAssignmentOptions('admission') : Promise.resolve([]),
    ])

    universities.value = universityRows
    semesters.value = semesterRows
    employees.value = employeeRows
    admissionEmployees.value = admissionRows
  }
  catch {
    toast.error(t('educational.caseUi.loadUniversitiesFailed'))
  }
})

onBeforeUnmount(() => {
  if (documentPreviewUrl.value.startsWith('blob:'))
    URL.revokeObjectURL(documentPreviewUrl.value)
})
</script>
<template>
  <VCard v-if="applicant" class="visa-case-card">
    <VCardText class="visa-case-content">
      <section class="case-section case-section--profile">
        <div class="case-profile-hero">
          <div class="case-profile-hero__identity">
            <VAvatar color="primary" variant="tonal" size="64"><VIcon icon="tabler-user" size="32" /></VAvatar>
            <div class="min-w-0">
              <p class="case-section__eyebrow mb-1">{{ t('educational.applicant') }}</p>
              <RouterLink class="case-title-link" :to="{ name: 'all-customers', query: { contact: applicant.id } }" :title="t('educational.openContactInCrm')">{{ applicant.name }}</RouterLink>
              <div class="d-flex flex-wrap align-center ga-2 mt-1"><VChip size="x-small" variant="tonal">{{ serviceLabel }}</VChip><CrmRelationshipDetails type="applicant" :id="applicant.id" :title="applicant.name" /></div>
            </div>
          </div>
          <div class="case-profile-hero__actions">
            <VChip v-if="applicant.user_id" size="small" class="case-portal-status" :color="applicant.portal_access_status === 'active' ? 'success' : 'warning'">{{ t(applicant.portal_access_status === 'active' ? 'educational.caseUi.portalActive' : 'educational.caseUi.portalDisabledStatus') }}</VChip>
            <VBtn v-if="applicant.user_id && canInvitePortal" size="small" variant="tonal" color="warning" :prepend-icon="applicant.portal_access_status === 'active' ? 'tabler-lock' : 'tabler-lock-open'" @click="portalAccessDialog = true">{{ t(applicant.portal_access_status === 'active' ? 'educational.caseUi.disablePortal' : 'educational.caseUi.enablePortal') }}</VBtn>
            <VBtn v-if="applicant.user_id && canInvitePortal" size="small" variant="tonal" color="error" prepend-icon="tabler-user-minus" @click="portalDeleteDialog = true">{{ t('educational.caseUi.deletePortalUser') }}</VBtn>
            <VBtn v-if="!applicant.user_id && canInvitePortal" size="small" variant="tonal" color="primary" prepend-icon="tabler-send" @click="openPortalInvite">{{ t('educational.caseUi.invitePortal') }}</VBtn>
            <VBtn v-if="canEditCase" size="small" variant="tonal" prepend-icon="tabler-pencil" @click="openEditCase">{{ t('educational.caseUi.editCase') }}</VBtn>
          </div>
        </div>
        <div class="case-profile-body">
      <div class="visa-case-stage-panel">
      <VChip color="primary">{{ translateApplicantStage(applicant.current_stage) }}</VChip>
      <VChip v-if="openTasks.length" color="warning" size="small" class="ms-2">{{ t('educational.caseUi.openTasks', { count: n(openTasks.length) }) }}</VChip>
      <!-- A lined applicant is a party profile: every contracted service is a
           separate case with its own page. The case-level writer stays only
           for an intake record that has not bought a service yet. -->
      <VAlert v-if="!applicant.services?.length && !canMoveStage" type="warning" variant="tonal" density="compact" class="mt-5 mb-0">{{ t('educational.stageMovePermission') }}</VAlert>
      <template v-if="!applicant.services?.length && canMoveStage">
      <VAlert v-if="caseIsClosed && !transitionOptions.length" :type="caseCompleted ? 'success' : 'warning'" variant="tonal" density="compact" class="mt-5 mb-0">
        <div>{{ t('educational.caseUi.caseClosedFinal', { stage: translateApplicantStage(applicant.current_stage) }) }}</div>
        <div v-if="caseCompleted" class="mt-1">{{ t('educational.caseUi.completedCommissionNote') }}</div>
      </VAlert>
      <template v-else>
        <VAlert v-if="caseIsClosed" type="info" variant="tonal" density="compact" class="mt-5 mb-0">{{ t('educational.caseUi.caseClosedReopen', { stage: translateApplicantStage(applicant.current_stage) }) }}</VAlert>
        <div class="case-stage-change mt-5"><ErpField v-model="selected" kind="select" :items="transitionOptions.map(stage => ({ title: translateApplicantStage(stage), value: stage.id }))" :label="t('educational.caseUi.moveStage')" :placeholder="t('educational.caseUi.moveStage')" class="mb-4" /><ErpField v-model="note" kind="textarea" :label="t('educational.caseUi.stageNote')" :placeholder="t('educational.caseUi.stageNote')" rows="3" class="mb-4" /><VBtn color="primary" variant="flat" :loading="moving" @click="move">{{ t('educational.caseUi.recordStage') }}</VBtn></div>
      </template>
      </template>
      </div>
      <div class="case-overview-grid mt-6">
        <section class="case-section case-section--details">
          <div class="case-section__heading"><div><p class="case-section__eyebrow">{{ t('educational.applicant') }}</p><h3>{{ t('common.crmDetails.profileTitle') }}</h3></div><VChip size="small" variant="tonal">{{ serviceLabel }}</VChip></div>
          <ApplicantSummary :applicant="applicant" />
          <div v-if="guardianSummary.length" class="case-detail-grid mt-4"><div v-for="row in guardianSummary" :key="row.key"><span>{{ row.label }}</span><strong>{{ row.value }}</strong></div></div>
        </section>
        <section class="case-section case-section--cases">
          <div class="case-section__heading"><div><p class="case-section__eyebrow">{{ t('educational.stage') }}</p><h3>{{ t('educational.caseUi.serviceCases') }}</h3></div></div>
          <div v-for="line in applicant.services" :key="line.id" class="case-case-row">
            <div class="case-case-row__identity">
              <div class="d-flex flex-wrap align-center ga-2">
                <strong>{{ line.type ?? line.service }}</strong>
                <span v-if="line.contract_number" class="text-caption text-medium-emphasis">{{ line.contract_number }}</span>
                <span v-if="line.parent_contract_number" class="text-caption text-medium-emphasis">({{ line.parent_contract_number }})</span>
                <VChip v-if="line.is_extension" size="x-small" variant="tonal" color="info">{{ t('serviceContracts.extension') }}</VChip>
              </div>
              <p class="text-caption text-medium-emphasis mb-0 mt-1">
                {{ t('educational.caseUi.serviceBalance', { fee: money(line.payable_total ?? 0), paid: money(line.amount_paid ?? 0), remaining: money(line.remaining_total ?? 0), status: translateBackendValue(line.payment_status ?? 'unpaid') }) }}
              </p>
              <VBtn size="small" variant="tonal" color="primary" class="mt-3" :to="{ name: 'service-case', params: { line: line.id } }" :aria-label="`${t('educational.caseUi.openServiceCase')}: ${line.contract_number ?? line.type ?? line.service}`">{{ t('educational.caseUi.openServiceCase') }}</VBtn>
            </div>
            <div class="case-case-row__meta">
              <VChip size="small" color="primary">{{ translateApplicantStage(line.current_stage) }}</VChip>
              <VChip size="x-small" variant="tonal">{{ translateBackendValue(line.status ?? '') }}</VChip>
              <VChip size="x-small" variant="tonal" :color="line.payment_status === 'paid' ? 'success' : line.payment_status === 'partially_paid' ? 'warning' : 'secondary'">
                {{ translateBackendValue(line.payment_status ?? 'unpaid') }}
              </VChip>
            </div>
          </div>
          <p v-if="!applicant.services?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.serviceCasesEmpty') }}</p>
        </section>
      </div>
        </div>
      </section>

      <section v-if="!applicant.services?.length" class="case-section case-section--timeline mt-5">
        <div class="case-section__heading"><div><p class="case-section__eyebrow">{{ t('educational.stage') }}</p><h3>{{ t('educational.caseUi.timeline') }}</h3></div></div>
        <VList><VListItem v-for="item in stageHistory" :key="item.id" :title="t('educational.caseUi.stageTransition', { from: item.old_stage ? translateApplicantStage(item.old_stage) : t('educational.caseUi.started'), to: translateApplicantStage(item.new_stage) })" :subtitle="stageSubtitle(item)" /></VList>
      </section>

      <div class="case-content-grid mt-5">
        <section class="case-section case-section--tasks">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.tasks') }}</h3><VBtn v-if="canCreateTask" size="small" color="primary" variant="flat" prepend-icon="tabler-plus" @click="openTaskDialog">{{ t('educational.caseUi.newTask') }}</VBtn></div>
          <p v-if="!canCreateTask" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.taskPermission') }}</p>
          <VList><VListItem v-for="task in openTasks" :key="task.id" :title="task.title" :subtitle="taskSubtitle(task)"><template #append><VBtn size="small" variant="text" color="success" :loading="completingId === task.id" :disabled="!!taskCompleteBlock(task)" :aria-label="`${t('educational.caseUi.complete')}: ${task.title}`" @click="completeTask(task.id)">{{ t('educational.caseUi.complete') }}<VTooltip v-if="taskCompleteBlock(task)" activator="parent">{{ taskCompleteBlock(task) }}</VTooltip></VBtn></template></VListItem><VListItem v-for="task in doneTasks" :key="`done-${task.id}`" class="case-task-item--done" :title="task.title" :subtitle="taskSubtitle(task)"><template #prepend><VIcon icon="tabler-check" color="success" /></template></VListItem></VList>
          <p v-if="!applicant.tasks?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.noTasks') }}</p>
        </section>

        <section v-if="['scholarship', 'private'].includes(applicant.service)" class="case-section case-section--universities">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.universityApplications') }}</h3><VBtn v-if="canEditCase" size="small" color="primary" variant="flat" prepend-icon="tabler-plus" @click="openUniDialog">{{ t('educational.caseUi.addApplication') }}</VBtn></div>
          <p class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.primaryChoiceHint') }}</p>
          <p v-if="applicant.details?.university" class="text-caption text-medium-emphasis">{{ t('educational.caseUi.primaryChoice', { university: applicant.details?.university.name }) }}</p>
          <h4 class="mt-2">{{ t('educational.caseUi.trackedApplications') }}</h4>
          <VList>
            <VListItem
              v-for="app in applicant.universities"
              :key="app.id"
              class="university-application-item"
              :title="app.university?.name ?? t('educational.caseUi.universityNumber', { id: n(app.university_id) })"
            >
              <template #prepend>
                <VChip size="small" :color="statusColor(app.status)">{{ translateBackendValue(app.status) }}</VChip>
              </template>
              <template #subtitle>
                <span>{{ t('educational.caseUi.applicationSummary', { status: translateBackendValue(app.status), intake: app.intake || t('common.messages.notAvailable'), notes: app.notes || t('common.messages.notAvailable') }) }}</span>
                <div v-if="universityContactFor(app.university_id)" class="university-quick-contact">
                  <ContactActionMenu v-if="universityContactFor(app.university_id)?.phone" kind="phone" :value="universityContactFor(app.university_id)?.phone" />
                  <ContactActionMenu v-if="universityContactFor(app.university_id)?.email" kind="email" :value="universityContactFor(app.university_id)?.email" />
                  <a v-if="portalHref(universityContactFor(app.university_id)?.portal_url)" :href="portalHref(universityContactFor(app.university_id)?.portal_url) ?? undefined" class="case-portal-link" target="_blank" rel="noopener noreferrer">
                    <VIcon icon="tabler-external-link" size="16" />
                    <span>{{ t('educational.caseUi.openPortal') }}</span>
                  </a>
                </div>
              </template>
              <template v-if="canEditCase" #append>
                <VBtn size="small" variant="text" prepend-icon="tabler-notes" @click="openUniNotes(app)">{{ t('educational.caseUi.universityNotes') }}</VBtn>
                <VMenu>
                  <template #activator="{ props }">
                    <VBtn size="small" variant="text" v-bind="props">{{ t('common.fields.status') }}</VBtn>
                  </template>
                  <VList density="compact">
                    <VListItem v-for="s in uniStatuses" :key="s" :title="translateBackendValue(s)" @click="setUniStatus(app.id, s)" />
                  </VList>
                </VMenu>
                <VBtn v-if="app.university_id !== applicant.details?.university_id" size="small" variant="text" @click="setPrimaryUniversity(app.university_id)">{{ t('educational.caseUi.primary') }}</VBtn>
                <VBtn size="small" variant="text" color="error" @click="removeUni(app.id)">{{ t('common.actions.remove') }}</VBtn>
              </template>
            </VListItem>
          </VList>
          <p v-if="!applicant.universities?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.noApplications') }}</p>
        </section>

        <section v-if="canViewFinance" class="case-section case-section--finance">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.finance') }}</h3><div class="d-flex flex-wrap justify-end ga-1"><VBtn v-if="canCreateDraftFinance" size="small" color="success" variant="flat" prepend-icon="tabler-arrow-down-left" @click="openFinanceDialog('income')">{{ $t('pages.finance.income.income.recordIncome2') }}</VBtn><VBtn v-if="canCreateDraftFinance" size="small" color="error" variant="tonal" prepend-icon="tabler-arrow-up-right" @click="openFinanceDialog('expense')">{{ $t('pages.finance.expenses.expenses.recordExpense2') }}</VBtn></div></div>
          <VList v-if="applicant.services?.length" density="compact">
            <VListItem v-for="line in applicant.services" :key="`balance-${line.id}`" :title="line.contract_number ?? line.type ?? line.service" :subtitle="t('educational.caseUi.serviceBalance', { fee: money(line.payable_total ?? 0), paid: money(line.amount_paid ?? 0), remaining: money(line.remaining_total ?? 0), status: translateBackendValue(line.payment_status ?? 'unpaid') })" />
          </VList>
          <VList><VListItem v-for="row in applicant.incomes" :key="`in-${row.id}`" :title="t('educational.caseUi.incomeEntry')" :subtitle="t('educational.caseUi.postedSummary', { amount: money(row.amount), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), description: row.description || t('common.messages.notAvailable') })"><template #prepend><VIcon icon="tabler-arrow-down-left" color="success" /></template><template #append><VChip size="x-small" variant="tonal" :color="row.status === 'draft' ? 'warning' : row.status === 'rejected' ? 'error' : 'success'">{{ translateBackendValue(row.status ?? 'approved') }}</VChip></template></VListItem><VListItem v-for="row in applicant.expenses" :key="`ex-${row.id}`" :title="t('educational.caseUi.expenseEntry')" :subtitle="t('educational.caseUi.postedSummary', { amount: money(row.amount), date: row.date ? formatDate(row.date) : t('common.messages.notAvailable'), description: row.description || t('common.messages.notAvailable') })"><template #prepend><VIcon icon="tabler-arrow-up-right" color="error" /></template><template #append><VChip size="x-small" variant="tonal" :color="row.status === 'draft' ? 'warning' : row.status === 'rejected' ? 'error' : 'success'">{{ translateBackendValue(row.status ?? 'approved') }}</VChip></template></VListItem></VList>
          <p v-if="!applicant.incomes?.length && !applicant.expenses?.length" class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.noFinance') }}</p>
        </section>

        <section class="case-section case-section--documents">
          <div class="case-section__heading"><div><h3>{{ t('educational.caseUi.documents') }}</h3><p class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.profileDocumentCount', { count: applicantAttachments.length }) }}</p></div><VBtn v-if="canOpenDocumentDialog" size="small" color="primary" variant="flat" prepend-icon="tabler-paperclip" @click="openDocumentDialog">{{ $t('common.ui.attachDocument') }}</VBtn></div>
          <VList density="compact"><VListItem v-for="file in applicantAttachments" :key="file.id" :title="file.label" :subtitle="file.name"><template #append><VBtn icon="tabler-eye" size="x-small" variant="text" :loading="previewLoading" :aria-label="file.name" @click="previewApplicantDocument(file.id, file.name)"/><VBtn icon="tabler-download" size="x-small" variant="text" :loading="downloadingId === file.id" :aria-label="`${t('educational.caseUi.download')}: ${file.label}`" @click="downloadApplicantDocument(file.id, file.name)"/><VBtn v-if="canEditCase" icon="tabler-trash" size="x-small" variant="text" color="error" :aria-label="`${t('common.actions.remove')}: ${file.label}`" @click="removeApplicantAttachment(file.id)"/></template></VListItem></VList>
          <VDivider class="my-4" />
          <VList><VListItem v-for="doc in documents" :key="doc.id" :title="doc.original_name" :subtitle="t('educational.caseUi.fromTask', { task: doc.task_title })"><template #append><VBtn icon="tabler-eye" size="small" variant="text" :loading="previewLoading" :aria-label="doc.original_name" @click="previewTaskDocument(doc.task_id, doc.id, doc.original_name)"/><VBtn size="small" variant="text" :loading="downloadingId === doc.id" @click="downloadDoc(doc.task_id, doc.id, doc.original_name)">{{ t('educational.caseUi.download') }}</VBtn></template></VListItem></VList>
        </section>

        <section class="case-section case-section--notes">
          <div class="case-section__heading"><h3>{{ t('educational.caseUi.notes') }}</h3></div>
          <ErpField v-model="notesDraft" kind="textarea" :label="t('educational.caseUi.caseNotes')" :placeholder="t('educational.caseUi.caseNotes')" :disabled="!canEditCase" rows="5" class="mb-4" />
          <VBtn v-if="canEditCase" color="primary" variant="flat" :loading="savingNotes" @click="saveNotes">{{ t('educational.caseUi.saveNotes') }}</VBtn>
          <p v-else class="text-caption text-medium-emphasis mb-0">{{ t('educational.caseUi.notesPermission') }}</p>
        </section>
      </div>
    </VCardText>
  </VCard>
  <VCard v-else-if="caseLoading" class="visa-case-card case-loading-card">
    <VCardText class="visa-case-content">
      <section class="case-section case-section--profile"><div class="case-profile-hero"><div class="d-flex align-center ga-3"><VSkeletonLoader type="avatar" /><div><VSkeletonLoader type="heading" width="220" /><VSkeletonLoader type="text" width="140" /></div></div><VSkeletonLoader type="button" /></div><div class="case-overview-grid mt-6"><VSkeletonLoader type="article" /><VSkeletonLoader type="article" /></div></section>
      <div class="case-content-grid mt-5"><VSkeletonLoader type="article" /><VSkeletonLoader type="article" /><VSkeletonLoader type="article" /><VSkeletonLoader type="article" /></div>
    </VCardText>
  </VCard>
  <VDialog v-model="terminalDialog" max-width="480" class="case-confirm-overlay">
    <VCard :class="['case-confirm-dialog', pendingIsCompletion ? 'case-confirm-dialog--success' : 'case-confirm-dialog--warning']"><VCardText class="case-confirm-dialog__content"><div class="case-confirm-dialog__icon"><VIcon :icon="pendingIsCompletion ? 'tabler-circle-check' : 'tabler-alert-triangle'" size="30" /></div><h2 class="case-confirm-dialog__title">{{ t('educational.caseUi.terminalConfirmTitle') }}</h2><p class="case-confirm-dialog__description">{{ pendingIsCompletion ? t('educational.caseUi.completedMoveNote', { stage: translateApplicantStage(pendingTerminalStage) }) : t('educational.caseUi.exitMoveNote', { stage: translateApplicantStage(pendingTerminalStage) }) }}</p><p class="case-confirm-dialog__description">{{ t('educational.caseUi.terminalConfirm', { stage: translateApplicantStage(pendingTerminalStage) }) }}</p></VCardText><VCardActions class="case-confirm-dialog__actions"><VBtn class="case-confirm-dialog__button case-confirm-dialog__button--cancel" :disabled="moving" @click="cancelTerminalMove">{{ t('common.actions.cancel') }}</VBtn><VBtn class="case-confirm-dialog__button case-confirm-dialog__button--confirm" :loading="moving" @click="confirmTerminalMove">{{ t('educational.caseUi.recordStage') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="taskDialog" max-width="560" content-class="case-form-dialog">
    <VCard class="case-form-card">
      <VForm class="app-form" @submit.prevent="createTask">
        <VCardTitle class="case-form-dialog__title px-5 py-4"><span>{{ t('educational.caseUi.newTask') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="case-form-dialog__close" :aria-label="t('common.actions.close')" :disabled="creatingTask" @click="taskDialog = false" /></VCardTitle>
        <VCardText class="case-dialog-body case-form-dialog__body pa-5">
          <ErpField v-model="taskForm.board_id" kind="select" :items="boards.map(board => ({ title: board.title, value: board.id }))" :label="t('common.fields.board')" :placeholder="t('common.fields.board')" class="mb-3" />
          <ErpField v-model="taskForm.title" :label="t('common.fields.title')" :placeholder="t('common.fields.title')" class="mb-3" />
          <ErpField v-model="taskForm.priority" kind="select" :items="['low', 'medium', 'high', 'urgent'].map(value => ({ value, title: translateBackendValue(value) }))" :label="t('common.fields.priority')" :placeholder="t('common.fields.priority')" class="mb-3" />
          <div class="erp-field"><label class="erp-field-label">{{ t('educational.caseUi.dueDate') }}</label><AppDateTimePicker v-model="taskForm.due_date" class="erp-field-control" :placeholder="t('educational.caseUi.dueDate')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }" /></div>
        </VCardText>
        <VCardActions class="case-form-dialog__actions px-5 py-4"><VSpacer /><VBtn class="case-form-dialog__cancel" variant="flat" color="error" :disabled="creatingTask" @click="taskDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="case-form-dialog__submit" color="primary" variant="flat" type="submit" :loading="creatingTask" :disabled="!taskForm.board_id || !taskForm.title.trim()">{{ t('common.actions.create') }}</VBtn></VCardActions>
      </VForm>
    </VCard>
  </VDialog>
  <VDialog v-model="uniDialog" max-width="560" content-class="case-form-dialog">
    <VCard class="case-form-card">
      <VForm class="app-form" @submit.prevent="saveUni">
        <VCardTitle class="case-form-dialog__title px-5 py-4"><span>{{ t('educational.caseUi.addUniversityApplication') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="case-form-dialog__close" :aria-label="t('common.actions.close')" :disabled="savingUni" @click="uniDialog = false" /></VCardTitle>
        <VCardText class="case-dialog-body case-form-dialog__body pa-5">
          <VRow><VCol cols="12" sm="6"><ErpField v-model="uniForm.university_id" kind="select" :items="universities.filter(university => university.is_active && university.country_id === applicant?.details?.country_id).map(university => ({ title: university.name, value: university.id }))" :label="t('educational.university')" :placeholder="t('educational.university')" /></VCol><VCol cols="12" sm="6"><ErpField v-model="uniForm.semester_id" kind="select" :items="semesters.filter(semester => semester.is_active).map(semester => ({ title: semester.name, value: semester.id }))" :label="t('educational.semester')" :placeholder="t('educational.semester')" clearable /></VCol><VCol cols="12" sm="6"><ErpField v-model="uniForm.status" kind="select" :items="uniStatuses.map(value => ({ value, title: translateBackendValue(value) }))" :label="t('educational.caseUi.outcome')" :placeholder="t('educational.caseUi.outcome')" /></VCol><VCol cols="12" sm="6"><ErpField v-model="uniForm.intake" label="Intake" :placeholder="t('educational.caseUi.intakeExample')" clearable /></VCol></VRow>
          <ErpField v-model="uniForm.notes" kind="textarea" :label="t('common.fields.notes')" :placeholder="t('common.fields.notes')" rows="3" />
        </VCardText>
        <VCardActions class="case-form-dialog__actions px-5 py-4"><VSpacer /><VBtn class="case-form-dialog__cancel" variant="flat" color="error" :disabled="savingUni" @click="uniDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="case-form-dialog__submit" color="primary" variant="flat" type="submit" :loading="savingUni" :disabled="!uniForm.university_id">{{ t('common.actions.add') }}</VBtn></VCardActions>
      </VForm>
    </VCard>
  </VDialog>
  <VDialog v-model="editDialog" max-width="820" content-class="case-form-dialog">
    <VCard class="case-form-card">
      <VForm class="app-form" @submit.prevent="saveCase">
      <VCardTitle class="case-form-dialog__title px-5 py-4"><span>{{ t('educational.caseUi.editCase') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="case-form-dialog__close" :aria-label="t('common.actions.close')" :disabled="savingCase" @click="editDialog = false" /></VCardTitle>
      <div class="case-edit-wizard" role="list"><button type="button" class="case-edit-wizard__step" :class="{ 'case-edit-wizard__step--active': editStep === 1, 'case-edit-wizard__step--complete': editStep > 1 }" :disabled="savingCase" @click="editStep = 1"><span class="case-edit-wizard__indicator"><VIcon v-if="editStep > 1" icon="tabler-check" size="15" /><span v-else>1</span></span><span>{{ t('common.crmDetails.profileTitle') }}</span></button><button type="button" class="case-edit-wizard__step" :class="{ 'case-edit-wizard__step--active': editStep === 2 }" :disabled="savingCase || editStep < 2" @click="editStep = 2"><span class="case-edit-wizard__indicator">2</span><span>{{ t('educational.service') }}</span></button></div>
      <VCardText class="case-dialog-body case-form-dialog__body pa-5">
        <Transition name="case-edit-step" mode="out-in"><div :key="editStep"><template v-if="editStep === 1"><ErpField kind="select" v-if="canAssignEmployee && applicant?.service !== 'academy'" v-model="editForm.responsible_employee_id" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="t('educational.responsibleEmployee')" :placeholder="t('educational.responsibleEmployee')" clearable class="mb-3" /><ErpField kind="select" v-if="canAssignAdmission && applicant?.service !== 'academy'" v-model="editForm.admission_employee_id" :items="admissionEmployees.map(e => ({ title: e.name, value: e.id }))" :label="t('agencyStaff.admission')" :placeholder="t('agencyStaff.admission')" clearable class="mb-3" /><VRow dense><VCol cols="12" sm="6"><ErpField v-model="editForm.father_name" :label="t('educational.fatherName')" :placeholder="t('educational.fatherName')" clearable /></VCol><VCol cols="12" sm="6"><ErpField v-model="editForm.father_phone" :label="t('educational.fatherPhone')" :placeholder="t('educational.fatherPhone')" clearable /></VCol></VRow><ErpField v-if="applicant?.service === 'test_offer'" v-model="editForm.backup_phone" :label="t('erp.backupPhone')" :placeholder="t('erp.backupPhone')" class="mt-3" /></template><ApplicantServiceFields v-else v-model="serviceForm" /></div></Transition>
      </VCardText>
      <VCardActions class="case-form-dialog__actions px-5 py-4">
        <VBtn v-if="editStep === 1" class="case-form-dialog__cancel" color="error" variant="flat" :disabled="savingCase" @click="editDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn v-else color="secondary" variant="tonal" prepend-icon="tabler-arrow-left" :disabled="savingCase" @click="editStep = 1">{{ t('serviceContracts.wizard.back') }}</VBtn><VSpacer /><VBtn v-if="editStep === 1" class="case-form-dialog__submit" color="primary" variant="flat" append-icon="tabler-arrow-right" :disabled="savingCase" @click="editStep = 2">{{ t('serviceContracts.wizard.next') }}</VBtn><VBtn v-else class="case-form-dialog__submit" color="primary" variant="flat" type="submit" :loading="savingCase">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
  <VDialog v-model="portalAccessDialog" max-width="480" class="case-confirm-overlay">
    <VCard :class="['case-confirm-dialog', applicant?.portal_access_status === 'active' ? 'case-confirm-dialog--error' : 'case-confirm-dialog--success']">
      <VCardText class="case-confirm-dialog__content"><div class="case-confirm-dialog__icon" aria-hidden="true"><VIcon :icon="applicant?.portal_access_status === 'active' ? 'tabler-lock' : 'tabler-lock-open'" size="30" /></div><h2 class="case-confirm-dialog__title">{{ t(applicant?.portal_access_status === 'active' ? 'educational.caseUi.disablePortal' : 'educational.caseUi.enablePortal') }}</h2><p class="case-confirm-dialog__description">{{ t(applicant?.portal_access_status === 'active' ? 'educational.caseUi.disablePortalConfirm' : 'educational.caseUi.enablePortalConfirm') }}</p></VCardText>
      <VCardActions class="case-confirm-dialog__actions"><VBtn class="case-confirm-dialog__button case-confirm-dialog__button--cancel" :disabled="updatingPortalAccess" @click="portalAccessDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="case-confirm-dialog__button case-confirm-dialog__button--confirm" :loading="updatingPortalAccess" @click="updatePortalAccess">{{ t('common.actions.confirm') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="portalDialog" max-width="560" content-class="case-form-dialog" scroll-strategy="block">
    <VCard class="case-form-card">
      <VForm class="app-form" @submit.prevent="sendPortalInvite">
        <VCardTitle class="case-form-dialog__title px-5 py-4"><span>{{ t('educational.caseUi.inviteTitle') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="case-form-dialog__close" :aria-label="t('common.actions.close')" :disabled="sendingPortalInvite" @click="portalDialog = false" /></VCardTitle>
        <VCardText class="case-dialog-body case-form-dialog__body pa-5">
          <p class="text-body-2 text-medium-emphasis mb-5">{{ t('educational.caseUi.inviteSubtitle') }}</p>
          <VRow><VCol cols="12" sm="6"><ErpField v-model="portalForm.name" :label="t('educational.caseUi.applicantName')" :placeholder="t('educational.caseUi.applicantName')" required /></VCol><VCol cols="12" sm="6"><ErpField v-model="portalForm.email" type="email" :label="t('educational.caseUi.applicantEmail')" :placeholder="t('educational.caseUi.applicantEmail')" required /></VCol><VCol cols="12" sm="6"><ErpField v-model="portalForm.password" type="password" autocomplete="new-password" :label="t('educational.caseUi.portalPassword')" :placeholder="t('educational.caseUi.portalPassword')" :hint="t('educational.caseUi.portalPasswordHint')" :error-messages="portalPasswordValid ? [] : [t('educational.caseUi.portalPasswordTooShort')]" persistent-hint /></VCol><VCol cols="12" sm="6"><ErpField v-model="portalForm.phone" :label="t('common.fields.phone')" :placeholder="t('common.fields.phone')" /></VCol></VRow>
          <ErpField v-model="portalForm.address" kind="textarea" :label="t('common.fields.address')" :placeholder="t('common.fields.address')" rows="2" />
        </VCardText>
        <VCardActions class="case-form-dialog__actions px-5 py-4"><VBtn class="case-form-dialog__cancel" variant="flat" color="error" :disabled="sendingPortalInvite" @click="portalDialog = false">{{ t('common.actions.cancel') }}</VBtn><VSpacer /><VBtn class="case-form-dialog__submit" color="primary" variant="flat" type="submit" :loading="sendingPortalInvite" :disabled="!portalForm.name.trim() || !portalForm.email.trim() || !portalPasswordValid">{{ t(portalForm.password ? 'educational.caseUi.createPortalAccount' : 'educational.caseUi.sendInvitation') }}</VBtn></VCardActions>
      </VForm>
    </VCard>
  </VDialog>
  <VDialog v-model="portalDeleteDialog" max-width="480" class="case-confirm-overlay">
    <VCard class="case-confirm-dialog case-confirm-dialog--error">
      <VCardText class="case-confirm-dialog__content"><div class="case-confirm-dialog__icon" aria-hidden="true"><VIcon icon="tabler-user-minus" size="30" /></div><h2 class="case-confirm-dialog__title">{{ t('educational.caseUi.deletePortalUser') }}</h2><p class="case-confirm-dialog__description">{{ t('educational.caseUi.deletePortalUserConfirm', { name: applicant?.name }) }}</p></VCardText>
      <VCardActions class="case-confirm-dialog__actions"><VBtn class="case-confirm-dialog__button case-confirm-dialog__button--cancel" :disabled="deletingPortalUser" @click="portalDeleteDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="case-confirm-dialog__button case-confirm-dialog__button--confirm" :loading="deletingPortalUser" @click="deletePortalUser">{{ t('common.actions.delete') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
  <VDialog v-model="financeDialog" max-width="600" content-class="case-form-dialog">
    <VCard class="case-form-card">
      <VForm class="app-form" @submit.prevent="createFinanceRequest">
        <VCardTitle class="case-form-dialog__title px-5 py-4"><span>{{ financeForm.transaction_type === 'income' ? $t('pages.finance.income.income.recordIncome2') : $t('pages.finance.expenses.expenses.recordExpense2') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="case-form-dialog__close" :aria-label="t('common.actions.close')" :disabled="savingFinance" @click="financeDialog = false" /></VCardTitle>
        <VCardText class="case-dialog-body case-form-dialog__body pa-5">
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">{{ $t('finance.drafts.saveDraftHint') }}</VAlert>
          <ErpField v-model="financeForm.type" kind="select" :items="financeTypeOptions" :label="financeForm.transaction_type === 'income' ? $t('common.messages.incomeType') : $t('common.messages.expenseType')" :placeholder="financeForm.transaction_type === 'income' ? $t('common.messages.incomeType') : $t('common.messages.expenseType')" class="mb-3" />
          <ErpField v-model="financeForm.description" kind="textarea" :label="$t('common.fields.description')" :placeholder="$t('common.fields.description')" rows="3" class="mb-3" />
          <VRow><VCol cols="12" sm="6"><ErpField v-model.number="financeForm.amount" type="number" min="0.000001" :label="$t('common.fields.amount')" :placeholder="$t('common.fields.amount')" /></VCol><VCol cols="12" sm="6"><ErpField v-model="financeForm.currency_id" kind="select" :items="financeOptions.currencies.map(row => ({ title: `${row.code} - ${row.name}`, value: row.id }))" :label="$t('serviceContracts.currency')" :placeholder="$t('serviceContracts.currency')" /></VCol></VRow>
        </VCardText>
        <VCardActions class="case-form-dialog__actions px-5 py-4"><VSpacer /><VBtn class="case-form-dialog__cancel" variant="flat" color="error" :disabled="savingFinance" @click="financeDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="case-form-dialog__submit" color="primary" variant="flat" type="submit" :loading="savingFinance" :disabled="!financeForm.type || !financeForm.description.trim() || financeForm.amount <= 0 || !financeForm.currency_id">{{ $t('finance.drafts.saveDraft') }}</VBtn></VCardActions>
      </VForm>
    </VCard>
  </VDialog>
  <VDialog v-model="documentDialog" max-width="560" content-class="case-form-dialog">
    <VCard class="case-form-card">
      <VForm class="app-form" @submit.prevent="uploadDocument">
        <VCardTitle class="case-form-dialog__title px-5 py-4"><span>{{ $t('common.ui.attachCaseDocument') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="case-form-dialog__close" :aria-label="t('common.actions.close')" :disabled="uploadingDocument" @click="documentDialog = false" /></VCardTitle>
        <VCardText class="case-dialog-body case-form-dialog__body pa-5">
          <p class="text-caption text-medium-emphasis mb-4">{{ documentDestination === 'task' ? $t('common.ui.documentsAreStoredAsInputAttachmentsOnACaseTaskSoTheyFollowTheSameAccessControlsAndAuditTrail') : t('educational.caseUi.profileDocumentsHint') }}</p>
          <ErpField v-if="documentTargetOptions.length > 1" v-model="documentDestination" kind="select" :items="documentTargetOptions" :label="t('educational.caseUi.documentDestination')" :placeholder="t('educational.caseUi.documentDestination')" class="mb-3" />
          <ErpField v-if="documentDestination === 'task'" v-model="documentTaskId" kind="select" :items="(applicant?.tasks ?? []).map(task => ({ title: task.title, value: task.id }))" :label="$t('common.ui.caseTask')" :placeholder="$t('common.ui.caseTask')" class="mb-3" />
          <ErpField v-else v-model="documentLabel" :label="t('educational.caseUi.documentLabel')" :placeholder="t('educational.caseUi.documentLabel')" required class="mb-3" />
          <input ref="documentFileInput" class="d-none" type="file" accept=".pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" @change="selectDocumentFile">
          <button type="button" class="case-file-picker" @click="chooseDocumentFile"><span class="case-file-picker__icon"><VIcon :icon="documentFile ? 'tabler-file-check' : 'tabler-upload'" /></span><span class="case-file-picker__copy"><strong>{{ documentFile?.name || $t('applicantPortal.selectDocument') }}</strong><small>{{ documentFile ? `${Math.ceil(documentFile.size / 1024)} KB` : $t('common.attachments.acceptedHint') }}</small></span><span class="case-file-picker__action">{{ documentFile ? $t('common.actions.edit') : $t('common.actions.upload') }}</span></button>
        </VCardText>
        <VCardActions class="case-form-dialog__actions px-5 py-4"><VSpacer /><VBtn class="case-form-dialog__cancel" variant="flat" color="error" :disabled="uploadingDocument" @click="documentDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="case-form-dialog__submit" color="primary" variant="flat" type="submit" :loading="uploadingDocument" :disabled="!documentFile || (documentDestination === 'task' ? !documentTaskId : !documentLabel.trim())">{{ $t('components.taskManager.todomatrixview.attach') }}</VBtn></VCardActions>
      </VForm>
    </VCard>
  </VDialog>
  <VDialog v-model="documentPreviewDialog" max-width="960" content-class="case-preview-dialog">
    <VCard class="case-preview-card">
      <VCardTitle class="d-flex align-center"><span class="text-truncate">{{ documentPreviewName }}</span><VSpacer /><VBtn icon="tabler-x" variant="text" :aria-label="t('common.actions.close')" @click="documentPreviewDialog = false" /></VCardTitle>
      <VCardText class="case-preview-card__body pa-0"><img v-if="documentPreviewUrl && documentPreviewIsImage" :src="documentPreviewUrl" :alt="documentPreviewName"><iframe v-else-if="documentPreviewUrl" :src="documentPreviewUrl" :title="documentPreviewName" /></VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.case-country-contact {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  gap: 8px 16px;
  margin-block: 8px 12px;
  padding-block: 10px;
  padding-inline: 12px;
}

.case-country-contact__identity {
  display: flex;
  flex-direction: column;
}

.university-quick-contact {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
  margin-block-start: 4px;
}

.case-portal-link {
  display: inline-flex;
  align-items: center;
  color: rgb(var(--v-theme-primary));
  font-size: 0.8125rem;
  gap: 4px;
  text-decoration: none;
}

.case-portal-link:hover {
  text-decoration: underline;
}

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

.case-section--profile {
  background: linear-gradient(145deg, rgba(var(--v-theme-primary), 0.08), rgb(var(--v-theme-surface)) 48%);
}

.case-profile-hero {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  gap: 1rem;
  padding-block-end: 1.25rem;
}

.case-profile-hero__identity,
.case-profile-hero__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.case-profile-hero__identity { align-items: flex-start; }
.case-profile-hero__actions { justify-content: flex-end; }
.case-profile-body { display: grid; gap: 1.25rem; grid-template-columns: repeat(2, minmax(0, 1fr)); padding-block-start: 1.25rem; }
.case-profile-body > .visa-case-stage-panel { grid-column: 2; }
.case-profile-body > .case-overview-grid { display: contents; }
.case-profile-body .case-section--details { grid-column: 1; grid-row: 1 / span 2; }
.case-profile-body .case-section--cases { grid-column: 2; }
.case-portal-status { min-block-size: 2rem; padding-inline: 0.75rem !important; }

.visa-case-stage-panel {
  max-inline-size: 46rem;
}

.case-overview-grid {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.case-content-grid {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.case-section {
  padding: clamp(1rem, 2vw, 1.5rem);
  border: 1px solid rgb(var(--v-border-color) / var(--v-border-opacity));
  border-radius: 0.75rem;
  background: rgb(var(--v-theme-on-surface) / 3.5%);
  min-inline-size: 0;
}

.case-section--timeline {
  border-color: rgba(var(--v-theme-secondary), 0.18);
  background: rgba(var(--v-theme-secondary), 0.05);
}

.case-section--tasks { background: rgba(var(--v-theme-info), 0.045); }
.case-section--universities { background: rgba(var(--v-theme-primary), 0.045); }
.case-section--finance { background: rgba(var(--v-theme-success), 0.045); }
.case-section--documents { background: rgba(var(--v-theme-warning), 0.045); }
.case-section--notes { background: rgba(var(--v-theme-secondary), 0.045); }
.case-section--details { border-color: rgba(var(--v-theme-primary), 0.18); background: rgba(var(--v-theme-primary), 0.055); }
.case-section--cases { border-color: rgba(var(--v-theme-info), 0.18); background: rgba(var(--v-theme-info), 0.055); }
.case-section--notes { grid-column: 1 / -1; }
.case-stage-change { padding: 1rem; border: 1px solid rgba(var(--v-theme-primary), 0.18); border-radius: 10px; background: rgba(var(--v-theme-primary), 0.055); }

.case-section--details,
.case-section--cases {
  min-block-size: 100%;
}

.case-section__heading {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-block-end: 1rem;
}

.case-section__heading h3,
.case-section__heading h4,
.case-section h4 {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  line-height: 1.4;
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
  margin-block: 0 0.25rem;
  margin-inline: 0;
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

.case-case-row {
  display: grid;
  align-items: center;
  padding: 0.75rem;
  border: 1px solid rgb(var(--v-border-color) / 36%);
  border-radius: 12px;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 97%, white 3%);
  box-shadow: 0 1px 2px rgb(var(--v-theme-on-surface) / 3.5%);
  gap: 0.75rem;
  grid-template-columns: minmax(10rem, 1fr) auto;
}

.case-case-row + .case-case-row {
  margin-block-start: 0.5rem;
}

.case-case-row__identity {
  min-inline-size: 0;
}

.case-case-row__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.case-section :deep(.v-list) {
  background: transparent;
}

.case-section--universities :deep(.v-list-item),
.case-section--finance :deep(.v-list-item),
.case-section--documents :deep(.v-list-item),
.case-task-item--done {
  overflow: hidden;
  border: 1px solid rgb(var(--v-border-color) / 34%);
  border-radius: 12px !important;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 97%, white 3%);
  box-shadow: 0 1px 2px rgb(var(--v-theme-on-surface) / 2.5%);
  padding-block: 0.7rem;
  padding-inline: 0.75rem;
}

.case-section--universities :deep(.v-list-item + .v-list-item),
.case-section--finance :deep(.v-list-item + .v-list-item),
.case-section--documents :deep(.v-list-item + .v-list-item),
.case-task-item--done {
  margin-block-start: 0.45rem;
}

.case-task-item--done {
  border-color: rgb(var(--v-theme-success) / 34%);
  background: rgb(var(--v-theme-success) / 3.5%);
}

:global(.v-theme--dark .case-case-row),
:global(.v-theme--dark .case-section--universities .v-list-item),
:global(.v-theme--dark .case-section--finance .v-list-item),
:global(.v-theme--dark .case-section--documents .v-list-item) {
  border-color: rgb(var(--v-border-color) / 42%);
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, black 7%);
}

.case-document-upload {
  display: grid;
  align-items: center;
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
  .case-profile-body { display: block; }
  .case-profile-body > .visa-case-stage-panel { margin-block-end: 1.25rem; }
  .case-profile-body > .case-overview-grid { display: grid; }

  .case-profile-body .case-section--details,
 .case-profile-body .case-section--cases { grid-column: auto; grid-row: auto; }

  .case-overview-grid,
  .case-content-grid {
    grid-template-columns: 1fr;
  }

  .case-section--notes { grid-column: auto; }
}

@media (max-width: 599px) {
  .visa-case-content {
    padding: 1rem;
  }

  .case-section {
    padding: 1rem;
  }

  .case-case-row,
  .case-document-upload {
    grid-template-columns: 1fr;
  }

  .case-section__heading > :last-child:not(:only-child) {
    inline-size: 100%;
  }

  .case-profile-hero__actions { justify-content: flex-start; inline-size: 100%; }
}
</style>

<style lang="scss">
.v-overlay:has(.case-form-dialog) .v-overlay__scrim,
.case-confirm-overlay .v-overlay__scrim,
.v-overlay:has(.case-preview-dialog) .v-overlay__scrim {
  backdrop-filter: blur(7px);
  background: rgba(var(--v-theme-on-surface), 0.28) !important;
  opacity: 1 !important;
}

.case-form-card { overflow: hidden; }
.case-edit-wizard { display: grid; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 95%, rgb(var(--v-theme-primary)) 5%); border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.06); gap: 0.5rem; grid-template-columns: repeat(2, minmax(0, 1fr)); padding-block: 0.9rem; padding-inline: 1.25rem; }
.case-edit-wizard__step { display: flex; align-items: center; padding: 0.35rem; border: 0; border-radius: 8px; background: transparent; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); cursor: pointer; font: inherit; font-size: 0.8rem; font-weight: 600; gap: 0.5rem; min-inline-size: 0; text-align: start; }
.case-edit-wizard__step:disabled { cursor: default; opacity: 1; }
.case-edit-wizard__indicator { display: grid; flex: 0 0 auto; border: 1px solid rgba(var(--v-theme-on-surface), 0.18); border-radius: 50%; block-size: 25px; font-size: 0.75rem; font-weight: 700; inline-size: 25px; place-items: center; }
.case-edit-wizard__step--active { color: rgb(var(--v-theme-primary)); }
.case-edit-wizard__step--active .case-edit-wizard__indicator { border-color: rgba(var(--v-theme-primary), 0.3); background: rgba(var(--v-theme-primary), 0.12); }
.case-edit-wizard__step--complete .case-edit-wizard__indicator { border-color: transparent; background: rgba(var(--v-theme-secondary), 0.14); color: rgb(var(--v-theme-secondary)); }

.case-edit-step-enter-active,
 .case-edit-step-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.case-edit-step-enter-from { opacity: 0; transform: translateY(8px); }
.case-edit-step-leave-to { opacity: 0; transform: translateY(-6px); }

.case-form-dialog__title,
.case-form-dialog__actions { display: flex; align-items: center; border-width: 0; border-style: solid; border-color: rgba(var(--v-theme-on-surface), 0.05); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.case-form-dialog__title { border-block-end-width: 1px; font-size: 1.35rem; font-weight: 700; }
.case-form-dialog__actions { border-block-start-width: 1px; }
.case-form-dialog__body { background: rgb(var(--v-theme-surface)); }
.case-form-dialog__close { border: 1px solid rgba(var(--v-theme-error), 0.38) !important; border-radius: 50% !important; }
.case-form-dialog__cancel { border: 1px solid rgba(var(--v-theme-error), 0.62) !important; background: rgba(var(--v-theme-error), 0.12) !important; color: rgb(var(--v-theme-error)) !important; }
.case-form-dialog__cancel:hover { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.case-form-dialog__submit { color: rgb(var(--v-theme-on-primary)) !important; }

.case-confirm-dialog { overflow: hidden; border: 1px solid rgba(var(--v-border-color), 0.2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, 18%) !important; text-align: center; --case-confirm-color: rgb(var(--v-theme-error)); --case-confirm-on-color: rgb(var(--v-theme-on-error)); --case-confirm-tint: rgba(var(--v-theme-error), 0.14); }
.case-confirm-dialog--success { --case-confirm-color: rgb(var(--v-theme-success)); --case-confirm-on-color: rgb(var(--v-theme-on-success)); --case-confirm-tint: rgba(var(--v-theme-success), 0.14); }
.case-confirm-dialog--warning { --case-confirm-color: rgb(var(--v-theme-warning)); --case-confirm-on-color: rgb(var(--v-theme-on-warning)); --case-confirm-tint: rgba(var(--v-theme-warning), 0.14); }
.case-confirm-dialog__content { display: flex; flex-direction: column; align-items: center; padding-block: 2rem 1.25rem !important; padding-inline: 2rem !important; }
.case-confirm-dialog__icon { display: grid; border-radius: 50%; background: var(--case-confirm-tint); block-size: 4.25rem; color: var(--case-confirm-color); inline-size: 4.25rem; margin-block-end: 1.25rem; place-items: center; }
.case-confirm-dialog__title { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1.2rem; font-weight: 700; line-height: 1.45; }
.case-confirm-dialog__description { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.65; margin-block: 0.65rem 0; margin-inline: 0; max-inline-size: 24rem; }
.case-confirm-dialog__actions { display: grid !important; gap: 0.75rem; grid-template-columns: repeat(2, minmax(0, 1fr)); padding-block: 0 2rem !important; padding-inline: 2rem !important; }
.case-confirm-dialog__button { border-radius: 10px !important; font-weight: 700; letter-spacing: 0; min-block-size: 2.85rem; text-transform: none; }
.case-confirm-dialog__button--cancel { border: 1px solid rgba(var(--v-theme-secondary), 0.52) !important; background: rgba(var(--v-theme-secondary), 0.13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.case-confirm-dialog__button--confirm { background: var(--case-confirm-color) !important; color: var(--case-confirm-on-color) !important; }

.case-file-picker { display: flex; align-items: center; padding: 1rem; border: 1px dashed rgba(var(--v-theme-on-surface), 0.22); border-radius: 12px; background: rgba(var(--v-theme-primary), 0.035); color: inherit; cursor: pointer; gap: 0.875rem; inline-size: 100%; text-align: start; }
.case-file-picker:hover { border-color: rgba(var(--v-theme-primary), 0.55); background: rgba(var(--v-theme-primary), 0.07); }
.case-file-picker__icon { display: grid; flex: 0 0 auto; border-radius: 50%; background: rgba(var(--v-theme-primary), 0.12); block-size: 2.5rem; color: rgb(var(--v-theme-primary)); inline-size: 2.5rem; place-items: center; }
.case-file-picker__copy { display: grid; flex: 1 1 auto; gap: 0.2rem; min-inline-size: 0; }

.case-file-picker__copy strong,
 .case-file-picker__copy small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.case-file-picker__copy small { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }
.case-file-picker__action { flex: 0 0 auto; border-radius: 7px; background: rgba(var(--v-theme-primary), 0.12); color: rgb(var(--v-theme-primary)); font-size: 0.75rem; font-weight: 600; padding-block: 0.38rem; padding-inline: 0.7rem; }
.case-preview-card { overflow: hidden; }
.case-preview-card__body { display: grid; overflow: auto; background: rgb(var(--v-theme-surface-variant)); block-size: min(72vh, 760px); place-items: center; }
.case-preview-card__body img { display: block; max-block-size: 100%; max-inline-size: 100%; object-fit: contain; }
.case-preview-card__body iframe { border: 0; background: white; block-size: 100%; inline-size: 100%; }
.case-section--documents :deep(.v-list-item + .v-list-item) { margin-block-start: 0.45rem; }

@media (max-width: 599px) {
  .case-file-picker { flex-wrap: wrap; align-items: flex-start; }
  .case-file-picker__action { margin-inline-start: 3.375rem; }

  .case-confirm-dialog__content,
 .case-confirm-dialog__actions { padding-inline: 1.25rem !important; }
}
</style>
