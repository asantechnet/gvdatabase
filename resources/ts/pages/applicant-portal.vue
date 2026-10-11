<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantPortalApi, { type ApplicantPortalAttachment, type ApplicantPortalData } from '@/api/applicantPortal'
import { canAccess } from '@/utils/authorization'
import { formatLocalizedDate } from '@/utils/localization'

const toast = useToast()
const { t } = useI18n()
const portal = ref<ApplicantPortalData | null>(null)
const loading = ref(true)
const saving = ref(false)
const profileDialog = ref(false)
const canEditProfile = computed(() => canAccess('edit_own_applicant_profile'))
const attachments = ref<ApplicantPortalAttachment[]>([])
const attachmentFile = ref<File | File[] | null>(null)
const attachmentLabel = ref('')
const uploadingAttachment = ref(false)
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
  }
  catch { toast.error(t('applicantPortal.loadFailed')) }
  finally { loading.value = false }
}

async function uploadAttachment() {
  const file = Array.isArray(attachmentFile.value) ? attachmentFile.value[0] : attachmentFile.value
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

onMounted(load)
</script>

<template>
  <div class="education-workspace applicant-portal">
    <VProgressLinear v-if="loading" indeterminate color="primary" rounded />
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
              <VBtn v-if="nextAction.action" color="primary" size="small" @click="profileDialog = true">{{ nextAction.action }}</VBtn>
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
            <VCardText v-else class="education-empty"><VIcon icon="tabler-building-community" size="34" /><strong>{{ $t('applicantPortal.noUniversityYet') }}</strong><span>{{ $t('applicantPortal.noUniversityHelp') }}</span></VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" lg="4">
          <VCard class="education-panel">
            <VCardItem :title="$t('applicantPortal.yourDetails')" :subtitle="$t('applicantPortal.profileComplete', { value: profileCompletion })">
              <template v-if="canEditProfile" #append><VBtn icon="tabler-pencil" size="small" variant="text" :aria-label="$t('applicantPortal.editProfile')" @click="profileDialog = true" /></template>
            </VCardItem>
            <VCardText><VProgressLinear :model-value="profileCompletion" :color="profileCompletion === 100 ? 'success' : 'primary'" height="7" rounded class="mb-4" /><div v-for="item in profileFields" :key="item.key" class="profile-check"><VIcon :color="item.complete ? 'success' : 'medium-emphasis'" :icon="item.complete ? 'tabler-circle-check-filled' : 'tabler-circle-dashed'" /><span>{{ item.label }}</span><small>{{ item.complete ? $t('applicantPortal.complete') : $t('applicantPortal.missing') }}</small></div><VBtn v-if="canEditProfile" block variant="tonal" color="primary" class="mt-4" prepend-icon="tabler-user-edit" @click="profileDialog = true">{{ $t('applicantPortal.reviewDetails') }}</VBtn></VCardText>
          </VCard>

          <VCard class="education-panel mt-4">
            <VCardItem :title="$t('applicantPortal.caseSummary')" />
            <VList density="compact" lines="two">
              <VListItem :title="$t('educational.service')" :subtitle="$t(`educational.builtInServices.${portal.case.service === 'test_offer' ? 'testOffer' : portal.case.service}`)" />
              <template v-if="['scholarship', 'private'].includes(portal.case.service)">
              <VListItem :title="$t('applicantPortal.destination')" :subtitle="portal.case.destination_country || $t('applicantPortal.toBeConfirmed')"><template #prepend><VIcon icon="tabler-map-pin" /></template></VListItem>
              <VListItem :title="$t('applicantPortal.primaryUniversity')" :subtitle="portal.case.primary_university || $t('applicantPortal.toBeConfirmed')"><template #prepend><VIcon icon="tabler-building-community" /></template></VListItem>
              <VListItem :title="$t('applicantPortal.studyLevel')" :subtitle="portal.case.education_level || $t('applicantPortal.toBeConfirmed')"><template #prepend><VIcon icon="tabler-school" /></template></VListItem>
              <VListItem :title="$t('educational.serviceFields.gradeScore')" :subtitle="portal.case.grade_score !== null ? `${portal.case.grade_score}` : $t('applicantPortal.toBeConfirmed')"><template #prepend><VIcon icon="tabler-percentage" /></template></VListItem>
              </template>
              <VListItem v-if="portal.case.service === 'test_offer'" :title="$t('educational.masterData.tests')" :subtitle="portal.case.service_details.test || $t('applicantPortal.toBeConfirmed')" />
              <template v-if="portal.case.service === 'academy'">
                <VListItem :title="$t('educational.masterData.programs')" :subtitle="portal.case.service_details.program || $t('applicantPortal.toBeConfirmed')" />
                <VListItem :title="$t('educational.masterData.times')" :subtitle="portal.case.service_details.time || $t('applicantPortal.toBeConfirmed')" />
                <VListItem :title="$t('educational.masterData.durations')" :subtitle="portal.case.service_details.duration || $t('applicantPortal.toBeConfirmed')" />
              </template>
              <VListItem :title="$t('educational.serviceFields.createdAt')" :subtitle="formatDate(portal.case.created_at) || $t('applicantPortal.toBeConfirmed')"><template #prepend><VIcon icon="tabler-calendar" /></template></VListItem>
            </VList>
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
          <VAlert v-else type="info" variant="tonal" class="mt-4" :title="$t('applicantPortal.needHelp')" :text="$t('applicantPortal.helpText')" />

          <VCard class="education-panel mt-4">
            <VCardItem :title="$t('applicantPortal.documents')" :subtitle="$t('applicantPortal.documentsHelp')" />
            <VList v-if="attachments.length" density="compact">
              <VListItem v-for="attachment in attachments" :key="attachment.id" :title="attachment.label" :subtitle="attachment.name">
                <template #prepend><VIcon icon="tabler-paperclip" /></template>
              </VListItem>
            </VList>
            <VCardText v-else class="text-body-2 text-medium-emphasis">{{ $t('applicantPortal.noDocuments') }}</VCardText>
            <VCardText v-if="canEditProfile" class="pt-0">
              <VTextField v-model="attachmentLabel" :label="$t('applicantPortal.documentLabel')" density="compact" class="mb-2" />
              <VFileInput v-model="attachmentFile" :label="$t('applicantPortal.selectDocument')" density="compact" accept=".pdf,.txt,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" show-size />
              <VBtn color="primary" block class="mt-2" :loading="uploadingAttachment" :disabled="!attachmentFile" prepend-icon="tabler-upload" @click="uploadAttachment">{{ $t('applicantPortal.uploadDocument') }}</VBtn>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <VDialog v-if="canEditProfile" v-model="profileDialog" max-width="820" scrollable>
      <VCard class="app-form-dialog">
        <VCardItem :title="$t('applicantPortal.profileTitle')" :subtitle="$t('applicantPortal.profileSubtitle')"><template #append><VBtn icon="tabler-x" variant="text" @click="profileDialog = false" /></template></VCardItem>
        <VDivider />
        <VCardText>
          <div class="form-section-title"><VIcon icon="tabler-user" /><span>{{ $t('applicantPortal.personalContact') }}</span></div>
          <VRow dense><VCol cols="12" md="6"><VTextField v-model="form.name" :label="$t('applicantPortal.fullName')" /></VCol><VCol cols="12" md="6"><VTextField v-model="form.email" :label="$t('applicantPortal.email')" type="email" /></VCol><VCol cols="12" md="6"><VTextField v-model="form.phone" :label="$t('applicantPortal.phone')" /></VCol><VCol cols="12" md="6"><VTextField v-model="form.father_name" :label="$t('educational.fatherName')" /></VCol><VCol cols="12" md="6"><VTextField v-model="form.father_phone" :label="$t('educational.fatherPhone')" /></VCol><VCol cols="12" md="6"><VSelect v-model="form.preferred_contact_method" :items="[{ title: $t('applicantPortal.email'), value: 'email' }, { title: $t('applicantPortal.phone'), value: 'phone' }, { title: 'WhatsApp', value: 'whatsapp' }]" :label="$t('applicantPortal.contactMethod')" clearable /></VCol><VCol cols="12" md="6"><VTextField v-model="form.english_test" :label="$t('applicantPortal.englishTest')" :placeholder="$t('applicantPortal.testExample')" /></VCol><VCol cols="12"><VTextarea v-model="form.address" :label="$t('applicantPortal.address')" rows="2" /></VCol></VRow>
          <div class="form-section-title mt-4"><VIcon icon="tabler-id" /><span>{{ $t('applicantPortal.identity') }}</span></div>
          <VRow dense><VCol cols="12" md="4"><VTextField v-model="form.date_of_birth" :label="$t('applicantPortal.dateOfBirth')" type="date" /></VCol><VCol cols="12" md="4"><VTextField v-model="form.nationality" :label="$t('applicantPortal.nationality')" /></VCol><VCol cols="12" md="4"><VTextField v-model="form.passport_number" :label="$t('applicantPortal.passportNumber')" /></VCol></VRow>
          <div class="form-section-title mt-4"><VIcon icon="tabler-school" /><span>{{ $t('applicantPortal.studyPlans') }}</span></div>
          <VRow dense><VCol cols="12" md="6"><VTextField v-model="form.intended_intake" :label="$t('applicantPortal.preferredIntake')" :placeholder="$t('applicantPortal.intakeExample')" /></VCol><VCol cols="12"><VTextarea v-model="form.education_background" :label="$t('applicantPortal.educationBackground')" rows="3" :placeholder="$t('applicantPortal.educationHelp')" /></VCol></VRow>
        </VCardText>
        <VDivider /><VCardActions class="pa-4"><VBtn variant="text" @click="profileDialog = false">{{ $t('applicantPortal.cancel') }}</VBtn><VSpacer /><VBtn color="primary" :loading="saving" :disabled="!form.name || !form.email" prepend-icon="tabler-device-floppy" @click="saveProfile">{{ $t('applicantPortal.save') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
