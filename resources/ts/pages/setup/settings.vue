<script setup lang="ts">
import type { Setting, SettingForm } from '@/api/setting'
import { useAppApi } from '@/composables/useAppApi'
import { useBranding } from '@/composables/useBranding'
import { canAccess } from '@/utils/authorization'
import { applyAccessibleThemeColors, contrastingTextColor } from '@/utils/themeContrast'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useTheme } from 'vuetify'

type UploadField = 'website_logo' | 'company_signature' | 'contract_letterhead' | 'contract_cover_design'

const toast = useToast()
const { settings } = useAppApi()
const { updateBranding } = useBranding()
const vuetifyTheme = useTheme()
const currentThemeName = computed(() => vuetifyTheme.name.value)
const canEditSettings = computed(() => canAccess('edit_settings'))

const defaultForm = (): SettingForm => ({
  navigation_clr: '#ffffff',
  cards_clr: '#ffffff',
  footer_clr: '#ffffff',
  paid_off_days_per_year: 0,
  website_title: 'Asantech',
  website_logo: null,
  company_signature: null,
  contract_letterhead: null,
  contract_cover_design: null,
  remove_contract_letterhead: false,
  remove_contract_cover_design: false,
  website_address: '',
  company_email: '',
  timezone: 'Asia/Kabul',
  phone: '',
  physical_address: '',
  short_company_name: 'Asantech',
  original_company_name: 'Asantech',
  business_licence: '',
  office_open_time: '09:00',
  office_close_time: '17:00',
  default_break_minutes: 0,
})

const form = ref<SettingForm>(defaultForm())
const savedSetting = ref<Setting | null>(null)
const settingId = ref<number | null>(null)
const logoPreview = ref('/images/logo.png')
const signaturePreview = ref<string | null>(null)
const letterheadPreview = ref<string | null>(null)
const coverDesignPreview = ref<string | null>(null)
const isLoading = ref(false)
const isSaving = ref(false)
const objectUrls = new Set<string>()

const timezoneOptions = (Intl.supportedValuesOf?.('timeZone') ?? ['UTC']).map(timezone => ({
  title: timezone.replaceAll('_', ' '),
  value: timezone,
}))
const timeOptions = Array.from({ length: 96 }, (_, index) => {
  const value = `${String(Math.floor(index / 4)).padStart(2, '0')}:${String((index % 4) * 15).padStart(2, '0')}`
  return { title: value, value }
})

const applyTheme = () => {
  const theme = vuetifyTheme.themes.value[currentThemeName.value]
  if (!theme) return

  applyAccessibleThemeColors(theme, {
    navigation: form.value.navigation_clr,
    footer: form.value.footer_clr,
    surface: form.value.cards_clr,
  })

  localStorage.setItem('theme-header-color', form.value.navigation_clr)
  localStorage.setItem('theme-footer-color', form.value.footer_clr)
  localStorage.setItem('theme-card-color', form.value.cards_clr)
}

function revokeObjectUrls() {
  objectUrls.forEach(url => URL.revokeObjectURL(url))
  objectUrls.clear()
}

function previewFile(file: File): string {
  const url = URL.createObjectURL(file)
  objectUrls.add(url)
  return url
}

function applySavedSetting(setting: Setting) {
  savedSetting.value = setting
  settingId.value = setting.id
  form.value = {
    ...defaultForm(),
    navigation_clr: setting.navigation_clr,
    cards_clr: setting.cards_clr,
    footer_clr: setting.footer_clr,
    paid_off_days_per_year: Number(setting.paid_off_days_per_year ?? 0),
    website_title: setting.website_title,
    website_address: setting.website_address || '',
    company_email: setting.company_email || '',
    timezone: setting.timezone || 'UTC',
    phone: setting.phone || '',
    physical_address: setting.physical_address || '',
    short_company_name: setting.short_company_name || setting.website_title,
    original_company_name: setting.original_company_name || setting.website_title,
    business_licence: setting.business_licence || '',
    office_open_time: setting.office_open_time || '09:00',
    office_close_time: setting.office_close_time || '17:00',
    default_break_minutes: setting.default_break_minutes ?? 0,
  }
  logoPreview.value = setting.website_logo_url || setting.website_logo || '/images/logo.png'
  signaturePreview.value = setting.company_signature_url
  letterheadPreview.value = setting.contract_letterhead_url
  coverDesignPreview.value = setting.contract_cover_design_url
}

async function loadSettings() {
  isLoading.value = true
  try {
    const response = await settings.getAll()
    const existing = response.data[0]
    if (existing)
      applySavedSetting(existing)
    else
      resetSettings()
  }
  catch (error: any) {
    toast.error(error?.message || 'Failed to load settings')
  }
  finally {
    isLoading.value = false
  }
}

function handleImageUpload(event: Event, field: UploadField) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  form.value[field] = file
  const preview = previewFile(file)
  if (field === 'website_logo') logoPreview.value = preview
  if (field === 'company_signature') signaturePreview.value = preview
  if (field === 'contract_letterhead') {
    letterheadPreview.value = preview
    form.value.remove_contract_letterhead = false
  }
  if (field === 'contract_cover_design') {
    coverDesignPreview.value = preview
    form.value.remove_contract_cover_design = false
  }

  ;(event.target as HTMLInputElement).value = ''
}

function removeArtwork(field: 'contract_letterhead' | 'contract_cover_design') {
  form.value[field] = null
  if (field === 'contract_letterhead') {
    letterheadPreview.value = null
    form.value.remove_contract_letterhead = true
  } else {
    coverDesignPreview.value = null
    form.value.remove_contract_cover_design = true
  }
}

const colorPreviewStyle = (color: string) => ({ backgroundColor: color, color: contrastingTextColor(color) })

function resetSettings() {
  revokeObjectUrls()
  form.value = defaultForm()
  settingId.value = savedSetting.value?.id ?? null
  logoPreview.value = savedSetting.value?.website_logo_url || '/images/logo.png'
  signaturePreview.value = savedSetting.value?.company_signature_url || null
  letterheadPreview.value = savedSetting.value?.contract_letterhead_url || null
  coverDesignPreview.value = savedSetting.value?.contract_cover_design_url || null
  applyTheme()
}

async function saveSettings() {
  if (!canEditSettings.value) return
  isSaving.value = true

  try {
    const payload: SettingForm = { ...form.value }
    for (const field of ['website_logo', 'company_signature', 'contract_letterhead', 'contract_cover_design'] as UploadField[])
      payload[field] = form.value[field] instanceof File ? form.value[field] : null

    const response = settingId.value
      ? await settings.update(settingId.value, payload)
      : await settings.create(payload)

    revokeObjectUrls()
    applySavedSetting(response.data)
    updateBranding(response.data)
    toast.success(translate('pages.settings.settings.settingsSavedSuccessfully'))
    applyTheme()
  }
  catch (error: any) {
    if (error?.errors)
      Object.values(error.errors).flat().forEach(message => toast.error(String(message)))
    else
      toast.error(error?.message || 'Failed to save settings')
  }
  finally {
    isSaving.value = false
  }
}

onMounted(loadSettings)
onBeforeUnmount(revokeObjectUrls)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VAlert v-if="!canEditSettings" type="info" variant="tonal" class="mb-4">
        {{ $t('pages.settings.settings.youCanViewCompanySettingsButYouDoNotHavePermissionToEditThem') }}
      </VAlert>

      <VCard :title="$t('pages.settings.settings.companySettings')" :loading="isLoading">
        <VCardText>
          <VRow>
            <VCol cols="12" md="4" class="text-center d-flex flex-column align-center justify-center border-md-e">
              <VAvatar rounded size="120" class="mb-4 elevation-2 bg-light">
                <VImg :src="logoPreview" alt="Website logo preview" cover />
              </VAvatar>
              <VBtn color="primary" variant="tonal" density="comfortable" class="position-relative" :disabled="!canEditSettings">
                <VIcon icon="tabler-upload" class="me-2" />{{ $t('pages.settings.settings.uploadNewLogo') }}
                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="file-overlay" :disabled="!canEditSettings" @change="handleImageUpload($event, 'website_logo')">
              </VBtn>
              <p class="text-caption text-medium-emphasis mt-2">{{ $t('pages.settings.settings.jpgPNGGIFOrWEBPUpTo2MB') }}</p>

              <VDivider class="my-5 w-100" />
              <div class="text-subtitle-2 mb-2">{{ $t('pages.settings.settings.authorizedSignature') }}</div>
              <VImg v-if="signaturePreview" :src="signaturePreview" alt="Company signature preview" width="180" height="80" contain class="asset-preview mb-3" />
              <VIcon v-else icon="tabler-signature" size="54" class="text-disabled mb-3" />
              <VBtn color="primary" variant="tonal" density="comfortable" class="position-relative" :disabled="!canEditSettings">
                <VIcon icon="tabler-upload" class="me-2" />{{ $t('pages.settings.settings.uploadSignature') }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="file-overlay" :disabled="!canEditSettings" @change="handleImageUpload($event, 'company_signature')">
              </VBtn>
              <p class="text-caption text-medium-emphasis mt-2">{{ $t('pages.settings.settings.shownOnPrintedContracts') }}</p>
            </VCol>

            <VCol cols="12" md="8" class="ps-md-6">
              <VRow>
                <VCol cols="12" sm="6"><VTextField v-model="form.website_title" :label="$t('pages.settings.settings.websiteTitle')" /></VCol>
                <VCol cols="12" sm="6"><VTextField v-model="form.short_company_name" :label="$t('pages.settings.settings.shortCompanyName')" /></VCol>
                <VCol cols="12"><VTextField v-model="form.original_company_name" :label="$t('pages.settings.settings.originalCompanyName')" /></VCol>
                <VCol cols="12" sm="6"><VTextField v-model="form.website_address" :label="$t('pages.settings.settings.websiteAddress')" :placeholder="$t('pages.settings.settings.httpsExampleCom')" /></VCol>
                <VCol cols="12" sm="6"><VTextField v-model="form.company_email" :label="$t('pages.settings.settings.companyEmail')" type="email" :placeholder="$t('pages.settings.settings.helloExampleCom')" :hint="$t('pages.settings.settings.usedAsTheSenderIdentityForEmployeeVerificationEmails')" persistent-hint /></VCol>
                <VCol cols="12" sm="6"><VTextField v-model="form.phone" :label="$t('common.fields.phone')" /></VCol>
                <VCol cols="12" sm="6"><AppSelect v-model="form.timezone" :items="timezoneOptions" :label="$t('pages.settings.settings.companyTimezone')" autocomplete="off" /></VCol>
                <VCol cols="12"><VTextarea v-model="form.physical_address" :label="$t('pages.settings.settings.physicalAddress')" rows="2" auto-grow /></VCol>
                <VCol cols="12" sm="6"><VTextField v-model="form.business_licence" :label="$t('pages.settings.settings.businessLicence')" :placeholder="$t('pages.settings.settings.licenceNumber')" /></VCol>
                <VCol cols="12" sm="6"><VTextField v-model.number="form.paid_off_days_per_year" :label="$t('pages.settings.settings.paidOffDaysPerYear')" type="number" min="0" max="366" /></VCol>
                <VCol cols="12" sm="6"><AppSelect v-model="form.office_open_time" :items="timeOptions" :label="$t('pages.settings.settings.officeOpens')" clearable /></VCol>
                <VCol cols="12" sm="6"><AppSelect v-model="form.office_close_time" :items="timeOptions" :label="$t('pages.settings.settings.officeCloses')" clearable /></VCol>
                <VCol cols="12" sm="6"><VTextField v-model.number="form.default_break_minutes" :label="$t('pages.settings.settings.defaultBreakMinutes')" type="number" min="0" max="720" /></VCol>
              </VRow>

              <h4 class="text-h6 mb-4 mt-4 font-weight-medium">{{ $t('pages.settings.settings.layoutColors') }}</h4>
              <VRow>
                <VCol v-for="item in [
                  { label: translate('pages.settings.settings.headerNavbar'), field: 'navigation_clr' as const },
                  { label: translate('pages.settings.settings.footerBar'), field: 'footer_clr' as const },
                  { label: translate('pages.settings.settings.cardsSurfaces'), field: 'cards_clr' as const },
                ]" :key="item.field" cols="12" sm="4" class="d-flex flex-column align-center">
                  <span class="text-subtitle-2 mb-2">{{ item.label }}</span>
                  <VColorPicker v-model="form[item.field]" mode="hex" hide-inputs canvas-height="100" width="100%" :disabled="!canEditSettings" />
                  <div class="color-swatch text-caption font-mono mt-2 px-2 py-1 rounded" :style="colorPreviewStyle(form[item.field])">{{ form[item.field] }}</div>
                </VCol>
              </VRow>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <VCard :title="$t('pages.settings.settings.contractDocumentBranding')">
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-5">
            {{ $t('pages.settings.settings.uploadCleanA4PortraitArtworkContractTextIsLayeredOverTheseImagesAndThe') }}
          </VAlert>
          <VRow>
            <VCol v-for="asset in [
              { field: 'contract_letterhead' as const, title: translate('pages.settings.settings.contractLetterhead'), preview: letterheadPreview, hint: translate('pages.settings.settings.minimum1000300Pixels') },
              { field: 'contract_cover_design' as const, title: translate('pages.settings.settings.contractCoverDesign'), preview: coverDesignPreview, hint: translate('pages.settings.settings.minimum10001400Pixels') },
            ]" :key="asset.field" cols="12" md="6">
              <div class="text-subtitle-1 font-weight-medium mb-2">{{ asset.title }}</div>
              <div class="document-artwork-preview mb-3">
                <VImg v-if="asset.preview" :src="asset.preview" :alt="`${asset.title} preview`" cover />
                <div v-else class="h-100 d-flex flex-column align-center justify-center text-disabled">
                  <VIcon icon="tabler-photo" size="44" class="mb-2" />
                  <span>{{ $t('pages.settings.settings.builtInDesignWillBeUsed') }}</span>
                </div>
              </div>
              <div class="d-flex flex-wrap ga-2">
                <VBtn color="primary" variant="tonal" class="position-relative" :disabled="!canEditSettings">
                  <VIcon icon="tabler-upload" class="me-2" />{{ $t('common.actions.upload') }}
                  <input type="file" accept="image/jpeg,image/png,image/webp" class="file-overlay" :disabled="!canEditSettings" @change="handleImageUpload($event, asset.field)">
                </VBtn>
                <VBtn v-if="asset.preview" color="error" variant="tonal" :disabled="!canEditSettings" @click="removeArtwork(asset.field)">
                  <VIcon icon="tabler-trash" class="me-2" />{{ $t('common.actions.remove') }}
                </VBtn>
              </div>
              <p class="text-caption text-medium-emphasis mt-2">{{ $t('pages.settings.settings.assetHint', { hint: translateBackendValue(asset.hint, 'backend.values') }) }}</p>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <div class="d-flex ga-4 justify-end">
        <VBtn color="secondary" variant="tonal" :disabled="isLoading || isSaving || !canEditSettings" @click="resetSettings">{{ $t('pages.settings.settings.resetDefaults') }}</VBtn>
        <VBtn color="primary" :loading="isSaving" :disabled="isLoading || !canEditSettings" @click="saveSettings">{{ $t('pages.settings.settings.saveSettings') }}</VBtn>
      </div>
    </VCol>
  </VRow>
</template>

<style scoped>
.file-overlay {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
  opacity: 0;
}

.asset-preview,
.color-swatch,
.document-artwork-preview {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.document-artwork-preview {
  width: 100%;
  max-width: 360px;
  aspect-ratio: 210 / 297;
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.03);
}
</style>
