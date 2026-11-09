<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
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
const previewShell = ref<HTMLElement | null>(null)
const previewScale = ref(1)
const CERTIFICATE_WIDTH_PX = 297 * 96 / 25.4
const CERTIFICATE_HEIGHT_PX = 210 * 96 / 25.4
const previewWidth = computed(() => Math.round(CERTIFICATE_WIDTH_PX * previewScale.value))
const previewHeight = computed(() => Math.round(CERTIFICATE_HEIGHT_PX * previewScale.value))
let previewObserver: ResizeObserver | null = null

function measurePreview() {
  const width = previewShell.value?.clientWidth ?? 0
  if (width > 0)
    previewScale.value = Math.min(1, width / CERTIFICATE_WIDTH_PX)
}

function stopPreviewObserver() {
  previewObserver?.disconnect()
  previewObserver = null
}

const visible = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

watch(() => props.modelValue, async open => {
  if (!open || !props.certificate) {
    stopPreviewObserver()
    return
  }
  signatureUrl.value = null
  try {
    const setting = (await SettingApi.getCompanyProfile()).data
    signatureUrl.value = setting?.company_signature_url ?? null
  }
  catch { /* Branding remains available when settings cannot be read. */ }

  await nextTick()
  measurePreview()
  stopPreviewObserver()
  if (previewShell.value) {
    previewObserver = new ResizeObserver(measurePreview)
    previewObserver.observe(previewShell.value)
  }
})

onBeforeUnmount(stopPreviewObserver)

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
      // The certificate sheet is exactly 297×210mm and carries its own 7mm
      // internal padding. A page margin would make it overflow onto a blank
      // second sheet, so the page itself must have no margin.
      pageStyle: 'size: A4 landscape; margin: 0;',
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
        <div v-if="certificate" ref="previewShell" class="certificate-preview">
          <div class="certificate-stage" :style="{ width: `${previewWidth}px`, height: `${previewHeight}px` }">
            <div class="certificate-transform" :style="{ transform: `scale(${previewScale})` }">
              <AcademyCertificateTemplate
                ref="documentRef"
                :certificate="certificate"
                :company-name="originalCompanyName"
                :logo-url="logoUrl"
                :signature-url="signatureUrl"
              />
            </div>
          </div>
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
.certificate-preview { inline-size: 100%; overflow: hidden; }
.certificate-stage { position: relative; direction: ltr; margin-inline: auto; }
.certificate-transform { position: absolute; inset-block-start: 0; left: 0; transform-origin: top left; }
.certificate-transform :deep(.academy-certificate) { margin: 0; }
</style>
