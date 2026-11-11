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
  <VDialog v-model="visible" max-width="960" scrollable content-class="academy-certificate-preview-dialog">
    <VCard class="academy-certificate-preview-card">
      <VCardTitle class="academy-certificate-preview__header d-flex align-center justify-space-between ga-3 px-5 py-4">
        <span>{{ t('academy.certificates.preview') }}</span>
        <VBtn icon="tabler-x" variant="tonal" color="error" class="academy-certificate-preview__close" :aria-label="t('common.actions.close')" @click="visible = false" />
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
      <VCardActions class="academy-certificate-preview__footer px-5 py-4">
        <VSpacer />
        <VBtn class="academy-certificate-preview__cancel" @click="visible = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" variant="flat" class="academy-certificate-preview__print" prepend-icon="tabler-printer" :loading="printing" :disabled="certificate?.status === 'revoked'" @click="print">
          {{ t('academy.certificates.print') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.academy-certificate-preview-card { overflow: hidden; }
.academy-certificate-preview__header, .academy-certificate-preview__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.academy-certificate-preview__header { border-block-end: 1px solid rgba(var(--v-border-color), .48); }
.academy-certificate-preview__footer { display: flex; align-items: center; border-block-start: 1px solid rgba(var(--v-border-color), .48); }
.academy-certificate-preview__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 999px !important; color: rgb(var(--v-theme-error)) !important; }
.academy-certificate-preview__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.academy-certificate-preview__cancel { border: 1px solid rgba(var(--v-theme-secondary), .45) !important; background: rgba(var(--v-theme-secondary), .12) !important; color: rgb(var(--v-theme-secondary)) !important; }
.academy-certificate-preview__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.academy-certificate-preview__print { color: rgb(var(--v-theme-on-primary)) !important; }
.preview-shell { background: #eef1f5; }
.certificate-preview { inline-size: 100%; overflow: hidden; }
.certificate-stage { position: relative; direction: ltr; margin-inline: auto; }
.certificate-transform { position: absolute; inset-block-start: 0; left: 0; transform-origin: top left; }
.certificate-transform :deep(.academy-certificate) { margin: 0; }
:global(.v-overlay:has(.academy-certificate-preview-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
</style>
