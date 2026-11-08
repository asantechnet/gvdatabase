<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import SettingApi from '@/api/setting'
import type { AcademyCertificateRecord } from '@/api/academyCertificates'
import { printDocument } from '@/utils/printDocument'
import { useBranding } from '@/composables/useBranding'
import AcademyCertificateTemplate from '@/pages/templates/academy_certificate_template.vue'

const props = defineProps<{ modelValue: boolean; certificate: AcademyCertificateRecord | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const toast = useToast()
const { logoUrl, originalCompanyName } = useBranding()
const signatureUrl = ref<string | null>(null)
const printing = ref(false)
const documentRef = ref<InstanceType<typeof AcademyCertificateTemplate> | null>(null)
const visible = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

watch(() => props.modelValue, async open => {
  if (!open || !props.certificate) return
  signatureUrl.value = null
  try {
    const setting = (await SettingApi.getCompanyProfile()).data
    signatureUrl.value = setting?.company_signature_url ?? null
  }
  catch { /* Branding remains available when settings cannot be read. */ }
})

async function print() {
  if (!props.certificate || !documentRef.value) return
  if (props.certificate.status === 'revoked') {
    toast.error(t('academy.certificates.revokedCannotPrint'))
    return
  }
  printing.value = true
  try {
    await printDocument({
      bodyClass: 'printing-academy-certificate',
      pageStyle: 'size: A4 landscape; margin: 6mm;',
      element: documentRef.value.$el as HTMLElement,
    })
    visible.value = false
  }
  finally {
    printing.value = false
  }
}
</script>

<template>
  <VDialog v-model="visible" max-width="960" scrollable>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between ga-3">
        <span>{{ t('academy.certificates.preview') }}</span>
        <VBtn icon="tabler-x" variant="text" :aria-label="t('common.actions.close')" @click="visible = false" />
      </VCardTitle>
      <VCardText class="preview-shell">
        <div v-if="certificate" class="certificate-preview">
          <AcademyCertificateTemplate
            ref="documentRef"
            :certificate="certificate"
            :company-name="originalCompanyName"
            :logo-url="logoUrl"
            :signature-url="signatureUrl"
          />
        </div>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="text" @click="visible = false">{{ t('common.actions.close') }}</VBtn>
        <VBtn color="primary" prepend-icon="tabler-printer" :loading="printing" :disabled="certificate?.status === 'revoked'" @click="print">
          {{ t('academy.certificates.print') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.preview-shell { background: #eef1f5; }
.certificate-preview { overflow: auto; }
.certificate-preview :deep(.academy-certificate) { transform-origin: top left; }
@media (max-width: 900px) { .certificate-preview :deep(.academy-certificate) { inline-size: 100%; } }
</style>
