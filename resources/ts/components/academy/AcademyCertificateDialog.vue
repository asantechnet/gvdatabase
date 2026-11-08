<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { AcademyClassesApi, type AcademyClassRecord } from '@/api/academyClasses'
import { AcademyEnrollmentsApi, type AcademyEnrollmentRecord } from '@/api/academyEnrollments'
import { AcademyCertificatesApi, type AcademyCertificateRecord, type AcademyCertificateTemplate } from '@/api/academyCertificates'
import { apiFieldErrors } from '@/utils/apiErrors'

const props = defineProps<{
  modelValue: boolean
  mode: 'issue' | 'bulk' | 'revoke' | 'template'
  certificate?: AcademyCertificateRecord | null
  template?: AcademyCertificateTemplate | null
}>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()

const { t } = useI18n()
const toast = useToast()
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const classes = ref<AcademyClassRecord[]>([])
const enrollments = ref<AcademyEnrollmentRecord[]>([])
const selectedClass = ref<number | null>(null)
const selectedEnrollments = ref<number[]>([])
const selectedEnrollment = ref<number | null>(null)
const revokeReason = ref('')
const form = reactive({ name: '', title: '', body: '', signatory_title: '', is_active: true })
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const title = computed(() => ({
  issue: t('academy.certificates.issue'),
  bulk: t('academy.certificates.bulkIssue'),
  revoke: t('academy.certificates.revoke'),
  template: t('academy.certificates.editTemplate'),
}[props.mode]))

watch(() => props.modelValue, async open => {
  if (!open) return
  errors.value = {}
  selectedEnrollments.value = []
  selectedEnrollment.value = null
  if (props.template) Object.assign(form, {
    name: props.template.name, title: props.template.title, body: props.template.body,
    signatory_title: props.template.signatory_title ?? '', is_active: props.template.is_active,
  })
  if (props.mode === 'issue' || props.mode === 'bulk') {
    try {
      classes.value = await AcademyClassesApi.list({ is_active: true })
      selectedClass.value = classes.value[0]?.id ?? null
      await loadEnrollments()
    }
    catch { /* Global notifier owns the API error. */ }
  }
})

async function loadEnrollments() {
  try {
    const response = await AcademyEnrollmentsApi.list({
      status: 'completed',
      class_id: props.mode === 'bulk' ? selectedClass.value ?? undefined : undefined,
      per_page: 100,
    })
    enrollments.value = response.data
  }
  catch { /* Global notifier owns the API error. */ }
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    if (props.mode === 'revoke') {
      if (!props.certificate) return
      await AcademyCertificatesApi.revoke(props.certificate.id, revokeReason.value)
      toast.success(t('academy.certificates.revokedDone'))
    }
    else if (props.mode === 'template') {
      if (!props.template) return
      await AcademyCertificatesApi.updateTemplate(props.template.id, {
        name: form.name, title: form.title, body: form.body,
        signatory_title: form.signatory_title || null, is_active: form.is_active,
      })
      toast.success(t('academy.certificates.templateSaved'))
    }
    else if (props.mode === 'bulk') {
      await AcademyCertificatesApi.issue({ class_id: selectedClass.value ?? undefined, enrollment_ids: selectedEnrollments.value })
      toast.success(t('academy.certificates.bulkSaved'))
    }
    else {
      if (!selectedEnrollment.value) return
      await AcademyCertificatesApi.issue({ academy_enrollment_id: selectedEnrollment.value })
      toast.success(t('academy.certificates.issued'))
    }
    visible.value = false
    emit('saved')
  }
  catch (error) {
    errors.value = apiFieldErrors(error)
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog v-model="visible" max-width="640" scrollable>
    <VCard>
      <VCardTitle>{{ title }}</VCardTitle>
      <VCardText>
        <template v-if="mode === 'issue'">
          <VSelect
            v-model="selectedEnrollment"
            :items="enrollments"
            :item-title="row => `${row.student?.name ?? row.applicant?.name} · ${row.class?.name ?? ''}`"
            item-value="id"
            :label="t('academy.certificates.completedEnrollment')"
            :error-messages="errors.academy_enrollment_id"
            max-height="300"
          />
          <VAlert type="info" variant="tonal" class="mt-3">{{ t('academy.certificates.issueHint') }}</VAlert>
        </template>
        <template v-else-if="mode === 'bulk'">
          <VSelect v-model="selectedClass" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" class="mb-3" @update:model-value="loadEnrollments" />
          <VSelect v-model="selectedEnrollments" :items="enrollments" :item-title="row => row.student?.name ?? row.applicant?.name" item-value="id" :label="t('academy.certificates.completedEnrollments')" multiple chips max-height="300" />
          <VAlert type="info" variant="tonal" class="mt-3">{{ t('academy.certificates.bulkHint') }}</VAlert>
        </template>
        <template v-else-if="mode === 'revoke'">
          <VAlert type="warning" variant="tonal" class="mb-4">{{ certificate?.certificate_number }} · {{ certificate?.student?.name }}</VAlert>
          <VTextarea v-model="revokeReason" :label="t('academy.certificates.revokeReason')" rows="3" :error-messages="errors.reason" />
        </template>
        <template v-else>
          <VTextField v-model="form.name" :label="t('academy.fields.name')" class="mb-3" :error-messages="errors.name" />
          <VTextField v-model="form.title" :label="t('academy.certificates.templateTitle')" class="mb-3" :error-messages="errors.title" />
          <VTextarea v-model="form.body" :label="t('academy.certificates.templateBody')" rows="8" :error-messages="errors.body" />
          <p class="text-caption text-medium-emphasis mt-2">{{ t('academy.certificates.tokens', { tokens: template?.tokens.join(', ') }) }}</p>
          <VTextField v-model="form.signatory_title" :label="t('academy.certificates.signatoryTitle')" class="mt-3" />
          <VSwitch v-model="form.is_active" :label="t('academy.fields.active')" class="mt-2" />
        </template>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="visible = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="saving" :disabled="mode === 'issue' && !selectedEnrollment" @click="save">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
