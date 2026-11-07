<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantPortalApi, { type ApplicantPortalAttachment, type ApplicantPortalData } from '@/api/applicantPortal'
import { celebrateApplicationCompletion, stopApplicationCelebration } from '@/extensions/celebration/applicantCelebration'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate } from '@/utils/localization'
import ErpField from '@/components/erp/ErpField.vue'
import ApplicantPortalSkeleton from '@/components/ApplicantPortalSkeleton.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'

const toast = useToast()
const { t, locale } = useI18n()
const portal = ref<ApplicantPortalData | null>(null)
const loading = ref(true)
const saving = ref(false)
const profileDialog = ref(false)
const profileStep = ref<1 | 2>(1)
const profileForm = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const canEditProfile = computed(() => canAccess('edit_own_applicant_profile'))
const attachments = ref<ApplicantPortalAttachment[]>([])
const attachmentFile = ref<File | File[] | null>(null)
const attachmentLabel = ref('')
const uploadingAttachment = ref(false)
const deletingAttachmentId = ref<number | null>(null)
const attachmentInput = ref<HTMLInputElement | null>(null)
const attachmentPreviewUrl = ref<string | null>(null)
const uploadedPreview = ref<ApplicantPortalAttachment | null>(null)
const uploadedPreviewUrl = ref<string | null>(null)
const previewingAttachmentId = ref<number | null>(null)
const form = ref({ name: '', email: '', phone: '', address: '', father_name: '', father_phone: '', date_of_birth: '', passport_number: '', nationality: '', education_background: '', intended_intake: '', english_test: '', preferred_contact_method: '' })

const progress = computed(() => {
  const value = portal.value?.case.progress
  if (!value?.current || !value.total) return 0
  return Math.round((value.current / value.total) * 100)
})
const profileFields = computed(() => portal.value ? [
  { key: 'contact', complete: Boolean(portal.value.profile.phone && portal.value.profile.address), label: t('applicantPortal.checkContact') },
  { key: 'identity', complete: Boolean(portal.value.profile.date_of_birth && portal.value.profile.nationality && portal.value.profile.passport_number), label: t('applicantPortal.checkIdentity') },
  { key: 'study', complete: Boolean(portal.value.profile.education_background && portal.value.profile.intended_intake), label: t('applicantPortal.checkStudy') },
] : [])
const profileCompletion = computed(() => {
  const rows = profileFields.value
  return rows.length ? Math.round(rows.filter(row => row.complete).length / rows.length * 100) : 0
})
const firstName = computed(() => portal.value?.profile.name?.trim().split(/\s+/)[0] || t('applicantPortal.applicant'))
const nextAction = computed(() => {
  if (profileCompletion.value < 100)
    return { icon: 'tabler-user-check', title: t('applicantPortal.completeProfile'), body: t('applicantPortal.completeProfileHelp'), action: canEditProfile.value ? t('applicantPortal.reviewDetails') : '' }
  if (portal.value && ['scholarship', 'private'].includes(portal.value.case.service) && !portal.value.case.university_applications.length)
    return { icon: 'tabler-building-community', title: t('applicantPortal.universityPlanning'), body: t('applicantPortal.universityPlanningHelp'), action: '' }
  return { icon: 'tabler-bell-check', title: t('applicantPortal.stayReady'), body: t('applicantPortal.stayReadyHelp'), action: '' }
})

const statusColor = (status: string) => ({ active: 'primary', closed: 'secondary', admitted: 'success', rejected: 'error', withdrawn: 'warning', applied: 'info', pending: 'warning' }[status] ?? 'primary')
const statusLabel = (status: string) => t(`applicantPortal.statuses.${status}`)
const formatDate = (value: string | null) => value ? formatLocalizedDate(value, 'short') : ''
const whatsappHref = (phone: string) => `https://wa.me/${phone.replace(/\D/g, '')}`
const selectedAttachment = computed(() => Array.isArray(attachmentFile.value) ? attachmentFile.value[0] : attachmentFile.value)
const selectedAttachmentIsImage = computed(() => Boolean(selectedAttachment.value?.type.startsWith('image/')))
const profileSteps = computed(() => [
  { number: 1, title: t('applicantPortal.personalContact') },
  { number: 2, title: t('applicantPortal.studyPlans') },
])

function openProfileDialog() {
  profileStep.value = 1
  profileDialog.value = true
}

const dockCalendar = (_dates: Date[], _value: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.transform = 'translateY(0) scale(0.86)'
    instance.calendarContainer.style.transformOrigin = 'top left'
    instance.calendarContainer.style.zIndex = '2400'
  })
}

function populateForm() {
  const profile = portal.value?.profile
  if (!profile) return
  form.value = {
    name: profile.name ?? '', email: profile.email ?? '', phone: profile.phone ?? '', address: profile.address ?? '',
    father_name: profile.father_name ?? '', father_phone: profile.father_phone ?? '',
    date_of_birth: profile.date_of_birth ?? '', passport_number: profile.passport_number ?? '', nationality: profile.nationality ?? '',
    education_background: profile.education_background ?? '', intended_intake: profile.intended_intake ?? '', english_test: profile.english_test ?? '', preferred_contact_method: profile.preferred_contact_method ?? '',
  }
}

async function load() {
  loading.value = true
  try {
    const [portalData, portalAttachments] = await Promise.all([
      ApplicantPortalApi.get(),
      ApplicantPortalApi.listAttachments(),
    ])
    portal.value = portalData
    attachments.value = portalAttachments
    populateForm()
    celebrateCompletedCase()
  }
  catch { toast.error(t('applicantPortal.loadFailed')) }
  finally { loading.value = false }
}

async function uploadAttachment() {
  const file = selectedAttachment.value
  if (!file)
    return

  uploadingAttachment.value = true
  try {
    attachments.value.unshift(await ApplicantPortalApi.uploadAttachment(file, attachmentLabel.value))
    attachmentFile.value = null
    attachmentLabel.value = ''
    toast.success(t('applicantPortal.documentUploaded'))
  }
  catch {
    toast.error(t('applicantPortal.documentUploadFailed'))
  }
  finally {
    uploadingAttachment.value = false
  }
}

function pickAttachment() { attachmentInput.value?.click() }
function replaceAttachment(event: Event) {
  const input = event.target as HTMLInputElement
  attachmentFile.value = input.files?.[0] ?? null
}
function clearAttachment() {
  attachmentFile.value = null
  if (attachmentInput.value)
    attachmentInput.value.value = ''
}

async function deleteAttachment(attachment: ApplicantPortalAttachment) {
  const confirmed = await confirmActionWarning({
    key: 'delete-applicant-portal-attachment',
    title: t('common.actions.deleteItem', { item: attachment.label }),
    message: t('pages.settings.settings.confirmRemoveFile'),
    confirmText: t('common.actions.delete'),
    cancelText: t('common.actions.cancel'),
    icon: 'tabler-trash',
    tone: 'error',
  })
  if (!confirmed)
    return

  deletingAttachmentId.value = attachment.id
  try {
    await ApplicantPortalApi.deleteAttachment(attachment.id)
    attachments.value = attachments.value.filter(item => item.id !== attachment.id)
    toast.success(t('common.attachments.removed'))
  }
  catch { toast.error(t('common.attachments.removeFailed')) }
  finally { deletingAttachmentId.value = null }
}

async function previewUploadedAttachment(attachment: ApplicantPortalAttachment) {
  previewingAttachmentId.value = attachment.id
  try {
    if (uploadedPreviewUrl.value)
      URL.revokeObjectURL(uploadedPreviewUrl.value)
    const response = await ApplicantPortalApi.previewAttachment(attachment.id)
    uploadedPreviewUrl.value = URL.createObjectURL(response.data)
    uploadedPreview.value = attachment
  }
  catch { toast.error(t('common.attachments.downloadFailed')) }
  finally { previewingAttachmentId.value = null }
}

function closeUploadedPreview() {
  uploadedPreview.value = null
  if (uploadedPreviewUrl.value)
    URL.revokeObjectURL(uploadedPreviewUrl.value)
  uploadedPreviewUrl.value = null
}

watch(selectedAttachment, file => {
  if (attachmentPreviewUrl.value)
    URL.revokeObjectURL(attachmentPreviewUrl.value)
  attachmentPreviewUrl.value = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null
})

async function saveProfile() {
  saving.value = true
  try {
    portal.value = await ApplicantPortalApi.updateProfile({
      name: form.value.name, email: form.value.email, phone: form.value.phone || null, address: form.value.address || null,
      father_name: form.value.father_name || null, father_phone: form.value.father_phone || null,
      profile: {
        date_of_birth: form.value.date_of_birth || null, passport_number: form.value.passport_number || null,
        nationality: form.value.nationality || null, education_background: form.value.education_background || null,
        intended_intake: form.value.intended_intake || null, english_test: form.value.english_test || null,
        preferred_contact_method: (form.value.preferred_contact_method || null) as 'email' | 'phone' | 'whatsapp' | null,
      },
    })
    populateForm()
    profileDialog.value = false
    toast.success(t('applicantPortal.saved'))
  }
  catch { toast.error(t('applicantPortal.saveFailed')) }
  finally { saving.value = false }
}

async function submitProfile() {
  const result = await profileForm.value?.validate()
  if (result && !result.valid)
    return

  if (profileStep.value === 1) {
    profileStep.value = 2
    return
  }

  await saveProfile()
}

// Celebrate a case that has reached its completion stage. Fired once per launch of
// the portal — the confetti runs itself, so there is nothing to await here.
function celebrateCompletedCase() {
  if (!portal.value)
    return

  celebrateApplicationCompletion({
    caseId: portal.value.case.id,
    fullName: portal.value.profile.name,
    stage: portal.value.case.current_stage,
    locale: locale.value,
  })
}

onMounted(load)
onBeforeUnmount(() => {
  stopApplicationCelebration()
  if (attachmentPreviewUrl.value)
    URL.revokeObjectURL(attachmentPreviewUrl.value)
  if (uploadedPreviewUrl.value)
    URL.revokeObjectURL(uploadedPreviewUrl.value)
})
</script>

<template>
  <div class="education-workspace applicant-portal">
    <ApplicantPortalSkeleton v-if="loading" />
    <template v-else-if="portal">
      <section class="portal-hero">
        <div class="portal-hero__copy">
          <div class="education-eyebrow">{{ $t('applicantPortal.caseNumber', { id: portal.case.id }) }}</div>
          <h1>{{ $t('applicantPortal.welcome', { name: firstName }) }}</h1>
          <p>{{ $t('applicantPortal.heroSubtitle') }}</p>
          <div class="d-flex flex-wrap align-center gap-2 mt-4">
            <VChip :color="statusColor(portal.case.status)" variant="flat" size="small">{{ statusLabel(portal.case.status) }}</VChip>
            <span class="text-body-2">{{ portal.case.current_stage?.name ?? $t('applicantPortal.preparing') }}</span>
          </div>
        </div>
        <div class="portal-progress" :aria-label="$t('applicantPortal.caseProgress')">
          <div class="portal-progress__value">{{ progress }}<small>%</small></div>
          <span>{{ $t('applicantPortal.caseProgress') }}</span>
          <VProgressLinear :model-value="progress" color="primary" bg-color="surface" height="8" rounded />
          <small>{{ portal.case.progress.current ? $t('applicantPortal.phaseOf', { current: portal.case.progress.current, total: portal.case.progress.total }) : $t('applicantPortal.firstPhaseSoon') }}</small>
        </div>
      </section>

      <VRow dense class="mt-4">
        <VCol cols="12" lg="8">
          <VCard class="education-panel next-action-card">
            <VCardText class="d-flex align-start gap-3">
              <VAvatar color="primary" variant="tonal" size="44"><VIcon :icon="nextAction.icon" /></VAvatar>
              <div class="flex-grow-1"><div class="text-overline text-primary">{{ $t('applicantPortal.whatNext') }}</div><h2 class="text-h6 mb-1">{{ nextAction.title }}</h2><p class="text-body-2 text-medium-emphasis mb-0">{{ nextAction.body }}</p></div>
              <VBtn v-if="nextAction.action" color="primary" size="small" @click="openProfileDialog">{{ nextAction.action }}</VBtn>
            </VCardText>
          </VCard>

          <VCard class="education-panel mt-4">
            <VCardItem :title="$t('applicantPortal.journey')" :subtitle="$t('applicantPortal.journeyHelp')" />
            <VCardText>
              <div v-if="portal.case.timeline.length" class="portal-timeline">
                <div v-for="(entry, index) in portal.case.timeline" :key="`${entry.key}-${entry.reached_at}`" class="portal-timeline__item">
                  <div class="portal-timeline__marker"><VIcon icon="tabler-check" size="14" /></div>
                  <div><strong>{{ entry.name }}</strong><div class="text-caption text-medium-emphasis">{{ formatDate(entry.reached_at) }}</div></div>
                  <VChip v-if="index === portal.case.timeline.length - 1" color="primary" size="x-small" variant="tonal">{{ $t('applicantPortal.current') }}</VChip>
                </div>
              </div>
              <div v-else class="education-empty"><VIcon icon="tabler-route" size="34" /><strong>{{ $t('applicantPortal.caseCreated') }}</strong><span>{{ $t('applicantPortal.firstPhaseSoon') }}</span></div>
            </VCardText>
          </VCard>

            <VCard v-if="['scholarship', 'private'].includes(portal.case.service)" class="education-panel mt-4">
            <VCardItem :title="$t('applicantPortal.universityApplications')" :subtitle="$t('applicantPortal.universityHelp')" />
            <div v-if="portal.case.university_applications.length" class="education-table-scroll"><VTable density="compact" class="education-table"><thead><tr><th>{{ $t('applicantPortal.university') }}</th><th>{{ $t('applicantPortal.intake') }}</th><th>{{ $t('applicantPortal.status') }}</th></tr></thead><tbody><tr v-for="application in portal.case.university_applications" :key="`${application.university}-${application.intake}`"><td class="font-weight-medium">{{ application.university || $t('applicantPortal.universityApplication') }}</td><td>{{ application.intake || $t('applicantPortal.toBeConfirmed') }}</td><td><VChip size="small" :color="statusColor(application.status)" variant="tonal">{{ statusLabel(application.status) }}</VChip></td></tr></tbody></VTable></div>
            <VCardText v-else><div class="portal-empty-state"><VAvatar color="secondary" variant="tonal" size="48"><VIcon icon="tabler-building-community" /></VAvatar><div><strong>{{ $t('applicantPortal.noUniversityYet') }}</strong><p>{{ $t('applicantPortal.noUniversityHelp') }}</p></div></div></VCardText>
          </VCard>

          <VCard class="education-panel mt-4 portal-documents-card">
            <VCardItem :title="$t('applicantPortal.documents')" :subtitle="$t('applicantPortal.documentsHelp')" />
            <VCardText>
              <div v-if="attachments.length" class="portal-document-list">
                <div v-for="attachment in attachments" :key="attachment.id" class="portal-document-row"><VAvatar color="secondary" variant="tonal" size="38"><VIcon :icon="attachment.mime_type?.startsWith('image/') ? 'tabler-photo' : 'tabler-file-description'" /></VAvatar><div class="portal-document-row__copy"><strong>{{ attachment.label }}</strong><span v-if="attachment.name !== attachment.label">{{ attachment.name }}</span></div><VBtn icon="tabler-eye" color="secondary" variant="text" size="small" :loading="previewingAttachmentId === attachment.id" :aria-label="$t('common.actions.viewFullDetails')" @click="previewUploadedAttachment(attachment)" /><VBtn icon="tabler-trash" color="error" variant="text" size="small" :loading="deletingAttachmentId === attachment.id" :aria-label="$t('common.actions.deleteItem', { item: attachment.label })" @click="deleteAttachment(attachment)" /></div>
              </div>
              <div v-else class="portal-documents-card__empty"><VIcon icon="tabler-folder-open" /><span>{{ $t('applicantPortal.noDocuments') }}</span></div>
              <template v-if="canEditProfile">
                <VDivider class="my-5" />
                <VRow class="portal-document-form"><VCol cols="12" md="6"><ErpField v-model="attachmentLabel" :label="$t('applicantPortal.documentLabel')" :placeholder="$t('serviceContracts.placeholders.admissionNotes')" /></VCol><VCol cols="12" md="6"><input ref="attachmentInput" class="d-none" type="file" accept=".pdf,.txt,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" @change="replaceAttachment"><div class="portal-file-picker" :class="{ 'portal-file-picker--selected': selectedAttachment }"><div class="portal-file-picker__icon"><VIcon :icon="selectedAttachment ? 'tabler-file-check' : 'tabler-upload'" /></div><div><strong>{{ selectedAttachment?.name || $t('applicantPortal.selectDocument') }}</strong><span>{{ selectedAttachment ? `${Math.ceil(selectedAttachment.size / 1024)} KB` : $t('common.attachments.acceptedHint') }}</span></div><VBtn size="small" color="secondary" variant="tonal" @click="pickAttachment">{{ selectedAttachment ? $t('common.actions.edit') : $t('common.actions.upload') }}</VBtn></div></VCol></VRow>
                <div v-if="selectedAttachment" class="portal-document-preview"><img v-if="selectedAttachmentIsImage && attachmentPreviewUrl" :src="attachmentPreviewUrl" :alt="selectedAttachment.name"><VIcon v-else icon="tabler-file-description" size="34" color="secondary" /><div><strong>{{ selectedAttachment.name }}</strong><span>{{ selectedAttachment.type || $t('applicantPortal.selectDocument') }}</span></div><VSpacer /><VBtn color="secondary" variant="tonal" size="small" @click="pickAttachment">{{ $t('common.actions.edit') }}</VBtn><VBtn icon="tabler-trash" color="error" variant="text" size="small" :aria-label="$t('common.actions.remove')" @click="clearAttachment" /></div>
                <VBtn color="primary" class="mt-4" :loading="uploadingAttachment" :disabled="!selectedAttachment" prepend-icon="tabler-upload" @click="uploadAttachment">{{ $t('applicantPortal.uploadDocument') }}</VBtn>
              </template>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" lg="4">
          <VCard class="education-panel">
            <VCardItem :title="$t('applicantPortal.yourDetails')" :subtitle="$t('applicantPortal.profileComplete', { value: profileCompletion })">
              <template v-if="canEditProfile" #append><VBtn icon="tabler-pencil" size="small" variant="text" :aria-label="$t('applicantPortal.editProfile')" @click="openProfileDialog" /></template>
            </VCardItem>
            <VCardText><VProgressLinear :model-value="profileCompletion" :color="profileCompletion === 100 ? 'success' : 'primary'" height="7" rounded class="mb-4" /><div v-for="item in profileFields" :key="item.key" class="profile-check"><VIcon :color="item.complete ? 'success' : 'medium-emphasis'" :icon="item.complete ? 'tabler-circle-check-filled' : 'tabler-circle-dashed'" /><span>{{ item.label }}</span><small>{{ item.complete ? $t('applicantPortal.complete') : $t('applicantPortal.missing') }}</small></div><VBtn v-if="canEditProfile" block variant="tonal" color="secondary" class="mt-4" prepend-icon="tabler-user-edit" @click="openProfileDialog">{{ $t('applicantPortal.reviewDetails') }}</VBtn></VCardText>
          </VCard>

          <VCard class="education-panel mt-4 portal-summary-card">
            <VCardItem :title="$t('applicantPortal.caseSummary')" />
            <VCardText class="portal-summary-list">
              <div class="portal-summary-row"><span>{{ $t('educational.service') }}</span><strong>{{ $t(`educational.builtInServices.${portal.case.service === 'test_offer' ? 'testOffer' : portal.case.service}`) }}</strong></div>
              <template v-if="['scholarship', 'private'].includes(portal.case.service)">
              <div class="portal-summary-row"><span>{{ $t('applicantPortal.destination') }}</span><strong>{{ portal.case.destination_country || $t('applicantPortal.toBeConfirmed') }}</strong></div><div class="portal-summary-row"><span>{{ $t('applicantPortal.primaryUniversity') }}</span><strong>{{ portal.case.primary_university || $t('applicantPortal.toBeConfirmed') }}</strong></div><div class="portal-summary-row"><span>{{ $t('applicantPortal.studyLevel') }}</span><strong>{{ portal.case.education_level || $t('applicantPortal.toBeConfirmed') }}</strong></div><div class="portal-summary-row"><span>{{ $t('educational.serviceFields.gradeScore') }}</span><strong>{{ portal.case.grade_score !== null ? `${portal.case.grade_score}` : $t('applicantPortal.toBeConfirmed') }}</strong></div>
              </template>
              <div v-if="portal.case.service === 'test_offer'" class="portal-summary-row"><span>{{ $t('educational.masterData.tests') }}</span><strong>{{ portal.case.service_details.test || $t('applicantPortal.toBeConfirmed') }}</strong></div>
              <template v-if="portal.case.service === 'academy'">
                <div class="portal-summary-row"><span>{{ $t('educational.masterData.programs') }}</span><strong>{{ portal.case.service_details.program || $t('applicantPortal.toBeConfirmed') }}</strong></div><div class="portal-summary-row"><span>{{ $t('educational.masterData.times') }}</span><strong>{{ portal.case.service_details.time || $t('applicantPortal.toBeConfirmed') }}</strong></div><div class="portal-summary-row"><span>{{ $t('educational.masterData.durations') }}</span><strong>{{ portal.case.service_details.duration || $t('applicantPortal.toBeConfirmed') }}</strong></div>
              </template>
              <div class="portal-summary-row"><span>{{ $t('educational.serviceFields.createdAt') }}</span><strong>{{ formatDate(portal.case.created_at) || $t('applicantPortal.toBeConfirmed') }}</strong></div>
            </VCardText>
          </VCard>

          <VCard v-if="portal.case.advisor" class="education-panel mt-4">
            <VCardItem :title="$t('applicantPortal.needHelp')" :subtitle="portal.case.advisor.name">
              <template #prepend><VAvatar color="primary" variant="tonal"><VIcon icon="tabler-user-heart" /></VAvatar></template>
            </VCardItem>
            <VCardText>
              <p class="text-body-2 text-medium-emphasis mb-3">{{ $t('applicantPortal.helpText') }}</p>
              <div class="d-flex flex-wrap gap-2">
                <VBtn v-if="portal.case.advisor.phone" :href="`tel:${portal.case.advisor.phone}`" size="small" color="primary" prepend-icon="tabler-phone">{{ $t('applicantPortal.phone') }}</VBtn>
                <VBtn v-if="portal.case.advisor.email" :href="`mailto:${portal.case.advisor.email}`" size="small" variant="tonal" prepend-icon="tabler-mail">{{ $t('applicantPortal.email') }}</VBtn>
                <VBtn v-if="portal.case.advisor.phone" :href="whatsappHref(portal.case.advisor.phone)" target="_blank" rel="noopener noreferrer" size="small" variant="tonal" color="success" prepend-icon="tabler-brand-whatsapp">WhatsApp</VBtn>
              </div>
            </VCardText>
          </VCard>
          <VCard v-else class="education-panel mt-4 portal-help-card"><VCardText><VAvatar color="secondary" variant="tonal" size="42"><VIcon icon="tabler-message-circle-question" /></VAvatar><div><h3>{{ $t('applicantPortal.needHelp') }}</h3><p>{{ $t('applicantPortal.helpText') }}</p></div></VCardText></VCard>
        </VCol>
      </VRow>
    </template>

    <VDialog :model-value="Boolean(uploadedPreview)" max-width="900" content-class="applicant-upload-preview-dialog" @update:model-value="value => !value && closeUploadedPreview()">
      <VCard class="applicant-upload-preview-dialog__card"><VCardTitle class="d-flex align-center"><span class="text-h6">{{ uploadedPreview?.label }}</span><VSpacer /><VBtn icon="tabler-x" variant="text" @click="closeUploadedPreview" /></VCardTitle><VCardText class="applicant-upload-preview-dialog__body"><img v-if="uploadedPreview?.mime_type?.startsWith('image/') && uploadedPreviewUrl" :src="uploadedPreviewUrl" :alt="uploadedPreview.name"><iframe v-else-if="uploadedPreviewUrl" :src="uploadedPreviewUrl" :title="uploadedPreview?.name" /></VCardText></VCard>
    </VDialog>

    <VDialog v-if="canEditProfile" v-model="profileDialog" :persistent="saving" scrollable scroll-strategy="block" max-width="820" content-class="applicant-profile-dialog">
      <VCard class="app-form-dialog applicant-profile-dialog__card">
        <VCardTitle class="applicant-profile-dialog__title px-5 py-5">
          <div><div class="text-h5 font-weight-bold">{{ $t('applicantPortal.profileTitle') }}</div></div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" class="applicant-profile-dialog__close" :disabled="saving" :aria-label="$t('common.actions.close')" data-confirm-form-cancel @click="profileDialog = false" />
        </VCardTitle>
        <div class="applicant-profile-wizard" role="list" :aria-label="$t('applicantPortal.profileTitle')">
          <button v-for="step in profileSteps" :key="step.number" type="button" class="applicant-profile-wizard__step" :class="{ 'applicant-profile-wizard__step--active': profileStep === step.number, 'applicant-profile-wizard__step--complete': profileStep > step.number }" :disabled="step.number > profileStep || saving" :aria-current="profileStep === step.number ? 'step' : undefined" @click="step.number < profileStep && (profileStep = step.number as 1 | 2)">
            <span class="applicant-profile-wizard__indicator"><VIcon v-if="profileStep > step.number" icon="tabler-check" size="15" /><span v-else>{{ step.number }}</span></span><span class="applicant-profile-wizard__label">{{ step.title }}</span>
          </button>
        </div>
        <VForm ref="profileForm" class="app-form applicant-profile-form" @submit.prevent="submitProfile">
          <VCardText class="applicant-profile-dialog__body pa-5 pa-sm-6">
            <Transition name="applicant-profile-step" mode="out-in">
              <section :key="profileStep">
                <template v-if="profileStep === 1">
                  <VRow class="applicant-profile-form__grid"><VCol cols="12" md="6"><ErpField v-model="form.name" required :label="$t('applicantPortal.fullName')" :placeholder="$t('serviceContracts.placeholders.name')" /></VCol><VCol cols="12" md="6"><ErpField v-model="form.email" required type="email" :label="$t('applicantPortal.email')" :placeholder="$t('serviceContracts.placeholders.email')" /></VCol><VCol cols="12" md="6"><ErpField v-model="form.phone" :label="$t('applicantPortal.phone')" :placeholder="$t('serviceContracts.placeholders.phone')" /></VCol><VCol cols="12" md="6"><ErpField v-model="form.father_name" :label="$t('educational.fatherName')" :placeholder="$t('serviceContracts.placeholders.fatherName')" /></VCol><VCol cols="12" md="6"><ErpField v-model="form.father_phone" :label="$t('educational.fatherPhone')" :placeholder="$t('serviceContracts.placeholders.fatherPhone')" /></VCol><VCol cols="12" md="6"><ErpField v-model="form.preferred_contact_method" kind="select" :items="[{ title: $t('applicantPortal.email'), value: 'email' }, { title: $t('applicantPortal.phone'), value: 'phone' }, { title: 'WhatsApp', value: 'whatsapp' }]" :label="$t('applicantPortal.contactMethod')" placeholder="Choose a contact method" clearable /></VCol><VCol cols="12" md="6"><ErpField v-model="form.address" :label="$t('applicantPortal.address')" :placeholder="$t('serviceContracts.placeholders.address')" /></VCol><VCol cols="12" md="6"><div class="erp-field"><label class="erp-field-label">{{ $t('applicantPortal.dateOfBirth') }}</label><AppDateTimePicker v-model="form.date_of_birth" clearable class="erp-field-control" :placeholder="'YYYY-MM-DD'" :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockCalendar }" /></div></VCol><VCol cols="12" md="6"><ErpField v-model="form.nationality" :label="$t('applicantPortal.nationality')" placeholder="e.g. Afghan" /></VCol><VCol cols="12" md="6"><ErpField v-model="form.passport_number" :label="$t('applicantPortal.passportNumber')" placeholder="e.g. P1234567" /></VCol></VRow>
                </template>
                <template v-else>
                  <VRow class="applicant-profile-form__grid"><VCol cols="12" md="6"><ErpField v-model="form.intended_intake" :label="$t('applicantPortal.preferredIntake')" :placeholder="$t('applicantPortal.intakeExample')" /></VCol><VCol cols="12" md="6"><ErpField v-model="form.english_test" :label="$t('applicantPortal.englishTest')" :placeholder="$t('applicantPortal.testExample')" /></VCol><VCol cols="12"><ErpField v-model="form.education_background" kind="textarea" :label="$t('applicantPortal.educationBackground')" rows="4" :placeholder="$t('applicantPortal.educationHelp')" /></VCol></VRow>
                </template>
              </section>
            </Transition>
          </VCardText>
          <VCardActions class="applicant-profile-dialog__actions px-5 py-4">
            <VBtn v-if="profileStep === 1" color="error" variant="flat" class="applicant-profile-dialog__cancel" :disabled="saving" data-confirm-form-cancel @click="profileDialog = false">{{ $t('applicantPortal.cancel') }}</VBtn>
            <VBtn v-else color="secondary" variant="tonal" prepend-icon="tabler-arrow-left" :disabled="saving" @click="profileStep = 1">{{ $t('serviceContracts.wizard.back') }}</VBtn>
            <VSpacer />
            <VBtn v-if="profileStep === 1" type="submit" color="primary" append-icon="tabler-arrow-right" class="applicant-profile-dialog__submit" :disabled="saving">{{ $t('serviceContracts.wizard.next') }}</VBtn>
            <VBtn v-else type="submit" color="primary" prepend-icon="tabler-device-floppy" class="applicant-profile-dialog__submit" :loading="saving" :disabled="saving">{{ $t('applicantPortal.save') }}</VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.applicant-portal { padding: 20px; }
.applicant-portal :deep(.education-panel) { overflow: hidden; }
.applicant-portal :deep(.v-card-item) { padding-block: 1rem .75rem; }
.next-action-card { border-color: rgba(var(--v-theme-secondary), .24) !important; background: linear-gradient(115deg, rgba(var(--v-theme-secondary), .09), rgb(var(--v-theme-surface)) 56%) !important; }
.next-action-card :deep(.v-avatar) { box-shadow: 0 8px 18px rgba(var(--v-theme-primary), .12); }
.portal-progress { box-shadow: 0 12px 30px rgba(var(--v-theme-primary), .08); }
.profile-check:first-of-type { border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.profile-check :deep(.v-icon) { padding: .15rem; border-radius: 50%; background: rgba(var(--v-theme-primary), .08); }
.portal-empty-state, .portal-help-card :deep(.v-card-text) { display: flex; align-items: center; gap: .9rem; }.portal-empty-state strong, .portal-help-card h3 { display: block; margin: 0; font-size: .95rem; }.portal-empty-state p, .portal-help-card p { margin: .28rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .82rem; line-height: 1.5; }.portal-help-card :deep(.v-avatar .v-icon) { color: rgb(var(--v-theme-secondary)) !important; opacity: 1; }
.portal-summary-list { display: grid; padding-block: .35rem !important; }.portal-summary-row { display: grid; padding: .72rem 0; border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); gap: .4rem; grid-template-columns: minmax(0, .88fr) minmax(0, 1.12fr); }.portal-summary-row:last-child { border-block-end: 0; }.portal-summary-row span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .78rem; }.portal-summary-row strong { color: rgb(var(--v-theme-on-surface)); font-size: .82rem; font-weight: 650; text-align: end; }
.portal-document-list { display: grid; gap: .5rem; }.portal-document-row { display: flex; align-items: center; padding: .6rem; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .7rem; gap: .7rem; }.portal-document-row__copy { display: grid; min-inline-size: 0; flex: 1; gap: .15rem; }.portal-document-row__copy strong, .portal-document-row__copy span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.portal-document-row__copy strong { font-size: .85rem; }.portal-document-row__copy span, .portal-file-picker span, .portal-document-preview span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; }.portal-documents-card__empty { display: flex; align-items: center; padding: .85rem; border: 1px dashed rgba(var(--v-theme-secondary), .38); border-radius: .7rem; background: rgba(var(--v-theme-secondary), .05); color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); gap: .55rem; font-size: .82rem; }.portal-document-form { margin: -3px !important; }.portal-document-form > :deep(.v-col) { padding: 3px !important; }.portal-file-picker { display: flex; min-block-size: 66px; align-items: center; padding: .65rem .75rem; border: 1px dashed rgba(var(--v-theme-on-surface), .28); border-radius: .55rem; background: rgba(var(--v-theme-on-surface), .025); gap: .65rem; }.portal-file-picker--selected { border-style: solid; border-color: rgba(var(--v-theme-secondary), .35); background: rgba(var(--v-theme-secondary), .06); }.portal-file-picker__icon { display: grid; inline-size: 34px; block-size: 34px; border-radius: .5rem; background: rgba(var(--v-theme-primary), .12); color: rgb(var(--v-theme-primary)); place-items: center; }.portal-file-picker > div:nth-child(2) { display: grid; min-inline-size: 0; flex: 1; gap: .12rem; }.portal-file-picker strong, .portal-file-picker span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.portal-file-picker strong { font-size: .8rem; }.portal-document-preview { display: flex; align-items: center; margin-block-start: .75rem; padding: .6rem; border: 1px solid rgba(var(--v-theme-secondary), .2); border-radius: .7rem; background: rgba(var(--v-theme-secondary), .06); gap: .65rem; }.portal-document-preview img { inline-size: 48px; block-size: 48px; border-radius: .45rem; object-fit: cover; }.portal-document-preview > div { display: grid; min-inline-size: 0; flex: 1; }.portal-document-preview strong { overflow: hidden; font-size: .82rem; text-overflow: ellipsis; white-space: nowrap; }
.applicant-profile-dialog__card { display: flex; max-block-size: calc(100dvh - 2rem); flex-direction: column; overflow: hidden; border: 1px solid rgba(var(--v-border-color), .32); background: rgb(var(--v-theme-surface)); }
.applicant-profile-dialog__title, .applicant-profile-dialog__actions { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.applicant-profile-dialog__title { border-block-end: 1px solid rgba(var(--v-theme-on-surface), .06); }
.applicant-profile-dialog__close { inline-size: 36px; block-size: 36px; border: 1px solid rgba(var(--v-theme-error), .38); border-radius: 50%; }
.applicant-profile-wizard { display: grid; padding: 14px 20px; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .06); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 95%, rgb(var(--v-theme-primary)) 5%); gap: 8px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.applicant-profile-wizard__step { position: relative; display: flex; align-items: center; min-inline-size: 0; padding: 4px; border: 0; border-radius: 8px; background: transparent; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); cursor: default; font: inherit; gap: 8px; text-align: start; }
.applicant-profile-wizard__step:not(:last-child)::after { position: absolute; inset-inline-start: calc(100% - 4px); inline-size: 8px; block-size: 1px; background: rgba(var(--v-theme-on-surface), .14); content: ''; }
.applicant-profile-wizard__step--complete { cursor: pointer; }.applicant-profile-wizard__step:disabled { opacity: 1; }
.applicant-profile-wizard__indicator { display: grid; flex: 0 0 auto; inline-size: 25px; block-size: 25px; border: 1px solid rgba(var(--v-theme-on-surface), .18); border-radius: 50%; place-items: center; font-size: .75rem; font-weight: 700; }
.applicant-profile-wizard__label { overflow: hidden; font-size: .8rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.applicant-profile-wizard__step--active { color: rgb(var(--v-theme-primary)); }.applicant-profile-wizard__step--active .applicant-profile-wizard__indicator { border-color: rgba(var(--v-theme-primary), .3); background: rgba(var(--v-theme-primary), .12); }.applicant-profile-wizard__step--complete .applicant-profile-wizard__indicator { border-color: transparent; background: rgba(var(--v-theme-secondary), .14); color: rgb(var(--v-theme-secondary)); }
.applicant-profile-form { display: flex; min-block-size: 0; flex: 1; flex-direction: column; }.applicant-profile-dialog__body { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; }.applicant-profile-form__grid { margin: -3px !important; row-gap: 0 !important; }.applicant-profile-form__grid > :deep(.v-col) { padding: 3px !important; }
.applicant-profile-dialog__actions { flex: 0 0 auto; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .06); }.applicant-profile-dialog__cancel { border: 1px solid rgba(var(--v-theme-error), .62) !important; background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }.applicant-profile-dialog__cancel:hover { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }.applicant-profile-dialog__submit { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.applicant-profile-step-enter-active, .applicant-profile-step-leave-active { transition: opacity .22s ease, transform .22s ease; }.applicant-profile-step-enter-from { opacity: 0; transform: translateY(10px); }.applicant-profile-step-leave-to { opacity: 0; transform: translateY(-7px); }
.applicant-upload-preview-dialog__card { overflow: hidden; }.applicant-upload-preview-dialog__body { display: grid; min-block-size: min(68dvh, 620px); padding: 0 !important; place-items: center; background: rgba(var(--v-theme-on-surface), .03); }.applicant-upload-preview-dialog__body img { max-inline-size: 100%; max-block-size: min(68dvh, 620px); object-fit: contain; }.applicant-upload-preview-dialog__body iframe { inline-size: 100%; min-block-size: min(68dvh, 620px); border: 0; background: white; }
:global(.v-overlay:has(.applicant-profile-dialog) .v-overlay__scrim) { background-color: rgba(var(--v-theme-on-surface), .25); backdrop-filter: blur(4px); }
@media (max-width: 600px) { .applicant-portal { padding: 16px; }.applicant-profile-dialog__title { padding: 1rem !important; }.applicant-profile-dialog__title :deep(.text-h5) { font-size: 1.25rem !important; }.applicant-profile-wizard { padding-inline: 1rem; }.applicant-profile-dialog__actions { padding-inline: 1rem !important; }.portal-document-preview { flex-wrap: wrap; }.portal-document-preview :deep(.v-spacer) { display: none; } }
</style>
