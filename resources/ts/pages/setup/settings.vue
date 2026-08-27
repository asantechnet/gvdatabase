<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useTheme } from 'vuetify'
import type { ApiResponse, Setting, SettingForm } from '@/api/setting'
import { useAppApi } from '@/composables/useAppApi'
import { useBranding } from '@/composables/useBranding'
import { canAccess } from '@/utils/authorization'
import { contrastingTextColor } from '@/utils/themeContrast'
import { type ThemeColorField, persistThemeColors, themeColorFields } from '@/utils/themePersistence'

type UploadField = 'website_logo' | 'company_signature' | 'contract_letterhead' | 'contract_cover_design'
type SettingsPage = 'company' | 'email' | 'operations' | 'appearance' | 'documents'

const toast = useToast()
const { settings } = useAppApi()
const { updateBranding } = useBranding()
const vuetifyTheme = useTheme()
const currentThemeName = computed(() => vuetifyTheme.name.value)
const canEditSettings = computed(() => canAccess('edit_settings'))
const activeSettingsPage = ref<SettingsPage>('company')

const { t: translate } = useI18n()

const defaultForm = (): SettingForm => ({
  primary_color: null,
  secondary_color: null,
  tertiary_color: null,
  paid_off_days_per_year: 0,
  website_title: 'Asantech',
  website_logo: null,
  company_signature: null,
  contract_letterhead: null,
  contract_cover_design: null,
  remove_contract_letterhead: false,
  remove_contract_cover_design: false,
  contract_letterhead_safe_x: 8.5,
  contract_letterhead_safe_y: 15,
  contract_letterhead_safe_width: 83,
  contract_letterhead_safe_height: 70,
  website_address: '',
  company_email: '',
  mail_host: '',
  mail_port: 587,
  mail_encryption: 'tls',
  mail_username: '',
  mail_password: '',
  clear_mail_password: false,
  mail_from_name: '',
  mail_reply_to: '',
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
const touchedColorFields = reactive<Record<ThemeColorField, boolean>>({
  primary_color: false,
  secondary_color: false,
  tertiary_color: false,
})
const objectUrls = new Set<string>()

const settingsPages = computed(() => [
  { value: 'company' as const, icon: 'tabler-building', title: translate('pages.settings.settings.companyPage'), description: translate('pages.settings.settings.companyPageDescription') },
  { value: 'email' as const, icon: 'tabler-mail-cog', title: translate('pages.settings.settings.emailDeliveryPage'), description: translate('pages.settings.settings.emailDeliveryPageDescription') },
  { value: 'operations' as const, icon: 'tabler-clock', title: translate('pages.settings.settings.operationsPage'), description: translate('pages.settings.settings.operationsPageDescription') },
  { value: 'appearance' as const, icon: 'tabler-palette', title: translate('pages.settings.settings.appearancePage'), description: translate('pages.settings.settings.appearancePageDescription') },
  { value: 'documents' as const, icon: 'tabler-file-description', title: translate('pages.settings.settings.documentsPage'), description: translate('pages.settings.settings.documentsPageDescription') },
])

const activePage = computed(() => settingsPages.value.find(page => page.value === activeSettingsPage.value)!)

const pageFields: Record<SettingsPage, (keyof SettingForm)[]> = {
  company: ['website_title', 'website_logo', 'company_signature', 'website_address', 'timezone', 'phone', 'physical_address', 'short_company_name', 'original_company_name'],
  email: ['company_email', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'clear_mail_password', 'mail_from_name', 'mail_reply_to'],
  operations: ['paid_off_days_per_year', 'business_licence', 'office_open_time', 'office_close_time', 'default_break_minutes'],
  appearance: ['primary_color', 'secondary_color', 'tertiary_color'],
  documents: ['contract_letterhead', 'contract_cover_design', 'remove_contract_letterhead', 'remove_contract_cover_design', 'contract_letterhead_safe_x', 'contract_letterhead_safe_y', 'contract_letterhead_safe_width', 'contract_letterhead_safe_height'],
}

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

  const colors = {
    primary_color: 'primary',
    secondary_color: 'secondary',
    tertiary_color: 'tertiary',
  } as const

  themeColorFields.forEach(field => {
    const color = form.value[field]
    if (!color) return
    const themeKey = colors[field]
    theme.colors[themeKey] = color
    theme.colors[`on-${themeKey}`] = contrastingTextColor(color)
  })
}

function clearTouchedColorFields() {
  themeColorFields.forEach(field => touchedColorFields[field] = false)
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
    primary_color: setting.primary_color,
    secondary_color: setting.secondary_color,
    tertiary_color: setting.tertiary_color,
    paid_off_days_per_year: Number(setting.paid_off_days_per_year ?? 0),
    website_title: setting.website_title,
    website_address: setting.website_address || '',
    company_email: setting.company_email || '',
    mail_host: setting.mail_host || '',
    mail_port: setting.mail_port || 587,
    mail_encryption: setting.mail_encryption || 'tls',
    mail_username: setting.mail_username || '',
    mail_password: '',
    clear_mail_password: false,
    mail_from_name: setting.mail_from_name || '',
    mail_reply_to: setting.mail_reply_to || '',
    timezone: setting.timezone || 'UTC',
    phone: setting.phone || '',
    physical_address: setting.physical_address || '',
    short_company_name: setting.short_company_name || setting.website_title,
    original_company_name: setting.original_company_name || setting.website_title,
    business_licence: setting.business_licence || '',
    office_open_time: setting.office_open_time || '09:00',
    office_close_time: setting.office_close_time || '17:00',
    default_break_minutes: setting.default_break_minutes ?? 0,
    contract_letterhead_safe_x: setting.contract_letterhead_safe_x ?? 8.5,
    contract_letterhead_safe_y: setting.contract_letterhead_safe_y ?? 15,
    contract_letterhead_safe_width: setting.contract_letterhead_safe_width ?? 83,
    contract_letterhead_safe_height: setting.contract_letterhead_safe_height ?? 70,
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
    clearTouchedColorFields()
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

  const oldPreview = field === 'website_logo' ? logoPreview.value :
    field === 'company_signature' ? signaturePreview.value :
      field === 'contract_letterhead' ? letterheadPreview.value :
        coverDesignPreview.value

  if (oldPreview && oldPreview.startsWith('blob:')) {
    URL.revokeObjectURL(oldPreview)
    objectUrls.delete(oldPreview)
  }

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

  ; (event.target as HTMLInputElement).value = ''
}

function removeArtwork(field: 'contract_letterhead' | 'contract_cover_design') {
  const currentPreview = field === 'contract_letterhead' ? letterheadPreview.value : coverDesignPreview.value
  if (currentPreview && currentPreview.startsWith('blob:')) {
    URL.revokeObjectURL(currentPreview)
    objectUrls.delete(currentPreview)
  }

  form.value[field] = null
  if (field === 'contract_letterhead') {
    letterheadPreview.value = null
    form.value.remove_contract_letterhead = true
  } else {
    coverDesignPreview.value = null
    form.value.remove_contract_cover_design = true
  }
}

const letterheadSafeAreaStyle = computed(() => ({
  left: `${form.value.contract_letterhead_safe_x ?? 8.5}%`,
  top: `${form.value.contract_letterhead_safe_y ?? 15}%`,
  width: `${form.value.contract_letterhead_safe_width ?? 83}%`,
  height: `${form.value.contract_letterhead_safe_height ?? 70}%`,
}))

function resizeLetterheadSafeArea(event: PointerEvent, edge: 'top' | 'right' | 'bottom' | 'left') {
  const preview = (event.currentTarget as HTMLElement).closest('.letterhead-safe-preview') as HTMLElement | null
  if (!preview) return
  event.preventDefault()
  const initial = {
    x: form.value.contract_letterhead_safe_x ?? 8.5,
    y: form.value.contract_letterhead_safe_y ?? 15,
    width: form.value.contract_letterhead_safe_width ?? 83,
    height: form.value.contract_letterhead_safe_height ?? 70,
  }
  const rect = preview.getBoundingClientRect()
  const update = (move: PointerEvent) => {
    const horizontal = ((move.clientX - event.clientX) / rect.width) * 100
    const vertical = ((move.clientY - event.clientY) / rect.height) * 100
    let { x, y, width, height } = initial
    if (edge === 'left') { x = Math.max(0, Math.min(initial.x + horizontal, initial.x + initial.width - 5)); width = initial.width + initial.x - x }
    if (edge === 'right') width = Math.max(5, Math.min(100 - initial.x, initial.width + horizontal))
    if (edge === 'top') { y = Math.max(0, Math.min(initial.y + vertical, initial.y + initial.height - 5)); height = initial.height + initial.y - y }
    if (edge === 'bottom') height = Math.max(5, Math.min(100 - initial.y, initial.height + vertical))
    Object.assign(form.value, {
      contract_letterhead_safe_x: Number(x.toFixed(2)),
      contract_letterhead_safe_y: Number(y.toFixed(2)),
      contract_letterhead_safe_width: Number(width.toFixed(2)),
      contract_letterhead_safe_height: Number(height.toFixed(2)),
    })
  }
  const stop = () => window.removeEventListener('pointermove', update)
  window.addEventListener('pointermove', update)
  window.addEventListener('pointerup', stop, { once: true })
}

const colorPreviewStyle = (color: string | null | undefined) => color
  ? { backgroundColor: color, color: contrastingTextColor(color) }
  : {}

// Do not apply values while loading the form. Only an actual picker update
// should preview colors across the application before saving.
function handleColorPickerUpdate(field: ThemeColorField) {
  touchedColorFields[field] = true
  applyTheme()
}

function resetSettings(apply: boolean = false, page: SettingsPage | null = null) {
  if (!page) revokeObjectUrls()

  // Properly revert to saved state to prevent data loss bug where UI shows existing
  // assets but form payload sends null (potentially deleting them on the backend)
  if (savedSetting.value && !page) {
    applySavedSetting(savedSetting.value)
  } else if (savedSetting.value && page) {
    const draft = { ...form.value }
    const previews = { logo: logoPreview.value, signature: signaturePreview.value, letterhead: letterheadPreview.value, cover: coverDesignPreview.value }
    applySavedSetting(savedSetting.value)
    const resetFields = new Set(pageFields[page])
    // Restore drafts outside the page being reset.
    ;(Object.keys(draft) as (keyof SettingForm)[]).forEach(field => {
      if (!resetFields.has(field)) form.value[field] = draft[field] as never
    })
    if (page !== 'company') {
      logoPreview.value = previews.logo
      signaturePreview.value = previews.signature
    }
    if (page !== 'documents') {
      letterheadPreview.value = previews.letterhead
      coverDesignPreview.value = previews.cover
    }
  } else {
    form.value = defaultForm()
    settingId.value = null
    logoPreview.value = '/images/logo.png'
    signaturePreview.value = null
    letterheadPreview.value = null
    coverDesignPreview.value = null
  }

  clearTouchedColorFields()
  if (apply) applyTheme()
}

function activePagePayload(): Partial<SettingForm> {
  const payload: Partial<SettingForm> = {}
  pageFields[activeSettingsPage.value].forEach(field => {
    const value = form.value[field]
    if (value === null || value instanceof File || typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean')
      payload[field] = value as never
  })

  if (activeSettingsPage.value === 'appearance') {
    themeColorFields.forEach(field => {
      if (!touchedColorFields[field]) delete payload[field]
    })
  }

  return payload
}

async function saveSettings() {
  if (!canEditSettings.value) return
  isSaving.value = true

  try {
    const payload = activePagePayload()

    const isCreating = settingId.value === null
    let response: ApiResponse<Setting>

    if (settingId.value) {
      response = await settings.update(settingId.value, payload)
    }
    else {
      // A first record must satisfy the server's required company defaults.
      response = await settings.create(form.value)
    }

    const colorsToPersist = isCreating
      ? themeColorFields
      : themeColorFields.filter(field => touchedColorFields[field])

    persistThemeColors(Object.fromEntries(colorsToPersist.map(field => [field, response.data[field]])))

    const previews = { logo: logoPreview.value, signature: signaturePreview.value, letterhead: letterheadPreview.value, cover: coverDesignPreview.value }
    // The API returns the complete record. Keep drafts from other pages intact.
    const otherPageDraft = { ...form.value }
    applySavedSetting(response.data)
    const activeFields = new Set(pageFields[activeSettingsPage.value])
    ;(Object.keys(otherPageDraft) as (keyof SettingForm)[]).forEach(field => {
      if (!activeFields.has(field)) form.value[field] = otherPageDraft[field] as never
    })
    if (activeSettingsPage.value !== 'company') {
      logoPreview.value = previews.logo
      signaturePreview.value = previews.signature
    }
    if (activeSettingsPage.value !== 'documents') {
      letterheadPreview.value = previews.letterhead
      coverDesignPreview.value = previews.cover
    }
    updateBranding(response.data)
    clearTouchedColorFields()
    toast.success(translate('pages.settings.settings.settingsSavedSuccessfully'))
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
  <VAlert v-if="!canEditSettings" type="info" variant="tonal" class="mb-6">
    {{ $t('pages.settings.settings.youCanViewCompanySettingsButYouDoNotHavePermissionToEditThem') }}
  </VAlert>

  <VCard class="settings-shell" :loading="isLoading">
    <VRow no-gutters>
      <VCol cols="12" md="3" class="settings-sidebar">
        <div class="pa-6 pb-4">
          <div class="text-overline text-primary font-weight-bold">{{ $t('pages.settings.settings.settings') }}</div>
          <div class="text-h6 font-weight-bold">{{ $t('pages.settings.settings.companySettings') }}</div>
          <p class="text-body-2 text-medium-emphasis mb-0 mt-1">{{ $t('pages.settings.settings.manageCompanyPreferences') }}</p>
        </div>
        <VList nav density="comfortable" class="px-3 pb-5">
          <VListItem v-for="page in settingsPages" :key="page.value" :active="activeSettingsPage === page.value"
            :prepend-icon="page.icon" rounded="lg" @click="activeSettingsPage = page.value">
            <VListItemTitle>{{ page.title }}</VListItemTitle>
            <VListItemSubtitle>{{ page.description }}</VListItemSubtitle>
          </VListItem>
        </VList>
      </VCol>

      <VCol cols="12" md="9" class="settings-content">
        <div class="settings-heading pa-6 pb-2 d-flex align-start justify-space-between ga-4">
          <div>
            <div class="d-flex align-center ga-3">
              <VAvatar size="38" color="primary" variant="tonal"><VIcon :icon="activePage.icon" /></VAvatar>
              <div>
                <h1 class="text-h5 font-weight-bold">{{ activePage.title }}</h1>
                <p class="text-body-2 text-medium-emphasis mb-0 mt-1">{{ activePage.description }}</p>
              </div>
            </div>
          </div>
          <VChip v-if="activeSettingsPage === 'email'" :color="savedSetting?.mail_password_configured ? 'success' : 'secondary'" size="small" variant="tonal">
            {{ savedSetting?.mail_password_configured ? $t('pages.settings.settings.smtpPasswordConfigured') : $t('pages.settings.settings.usingDefaultMailConfiguration') }}
          </VChip>
        </div>

        <VCardText class="pa-6 pt-4">
          <VRow v-if="activeSettingsPage === 'company'">
            <VCol cols="12" md="4" class="text-center d-flex flex-column align-center justify-center border-md-e">
              <VAvatar rounded size="120" class="mb-4 elevation-2 bg-light">
                <VImg :src="logoPreview" alt="Website logo preview" cover />
              </VAvatar>
              <VBtn color="primary" variant="tonal" density="comfortable" class="position-relative"
                :disabled="!canEditSettings">
                <VIcon icon="tabler-upload" class="me-2" />{{ $t('pages.settings.settings.uploadNewLogo') }}
                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="file-overlay"
                  :disabled="!canEditSettings" @change="handleImageUpload($event, 'website_logo')">
              </VBtn>
              <p class="text-caption text-medium-emphasis mt-2">{{ $t('pages.settings.settings.jpgPNGGIFOrWEBPUpTo2MB')
              }}</p>

              <VDivider class="my-5 w-100" />
              <div class="text-subtitle-2 mb-2">{{ $t('pages.settings.settings.authorizedSignature') }}</div>
              <VImg v-if="signaturePreview" :src="signaturePreview" alt="Company signature preview" width="180"
                height="80" contain class="asset-preview mb-3" />
              <VIcon v-else icon="tabler-signature" size="54" class="text-disabled mb-3" />
              <VBtn color="primary" variant="tonal" density="comfortable" class="position-relative"
                :disabled="!canEditSettings">
                <VIcon icon="tabler-upload" class="me-2" />{{ $t('pages.settings.settings.uploadSignature') }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="file-overlay"
                  :disabled="!canEditSettings" @change="handleImageUpload($event, 'company_signature')">
              </VBtn>
              <p class="text-caption text-medium-emphasis mt-2">{{ $t('pages.settings.settings.shownOnPrintedContracts')
              }}</p>
            </VCol>

            <VCol cols="12" md="8" class="ps-md-6">
              <VRow>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.website_title" :label="$t('pages.settings.settings.websiteTitle')" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.short_company_name"
                    :label="$t('pages.settings.settings.shortCompanyName')" />
                </VCol>
                <VCol cols="12">
                  <VTextField v-model="form.original_company_name"
                    :label="$t('pages.settings.settings.originalCompanyName')" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.website_address" :label="$t('pages.settings.settings.websiteAddress')"
                    :placeholder="$t('pages.settings.settings.httpsExampleCom')" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.phone" :label="$t('common.fields.phone')" />
                </VCol>
                <VCol cols="12" sm="6">
                  <AppSelect v-model="form.timezone" :items="timezoneOptions"
                    :label="$t('pages.settings.settings.companyTimezone')" autocomplete="off" />
                </VCol>
                <VCol cols="12">
                  <VTextarea v-model="form.physical_address" :label="$t('pages.settings.settings.physicalAddress')"
                    rows="2" auto-grow />
                </VCol>
              </VRow>
            </VCol>
          </VRow>

          <VRow v-else-if="activeSettingsPage === 'email'">
            <VCol cols="12"><VAlert type="info" variant="tonal" icon="tabler-shield-lock">{{ $t('pages.settings.settings.emailDeliveryHint') }}</VAlert></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="form.company_email" :label="$t('pages.settings.settings.fromEmailAddress')" type="email" :placeholder="$t('pages.settings.settings.helloExampleCom')" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="form.mail_from_name" :label="$t('pages.settings.settings.fromName')" :placeholder="form.website_title" /></VCol>
            <VCol cols="12"><div class="text-overline text-medium-emphasis">{{ $t('pages.settings.settings.smtpServer') }}</div></VCol>
            <VCol cols="12" sm="8"><VTextField v-model="form.mail_host" :label="$t('pages.settings.settings.smtpHost')" placeholder="smtp.example.com" /></VCol>
            <VCol cols="12" sm="4"><VTextField v-model.number="form.mail_port" :label="$t('pages.settings.settings.smtpPort')" type="number" min="1" max="65535" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="form.mail_username" :label="$t('pages.settings.settings.smtpUsername')" autocomplete="username" /></VCol>
            <VCol cols="12" sm="6"><AppSelect v-model="form.mail_encryption" :items="[{ title: $t('pages.settings.settings.tlsRecommended'), value: 'tls' }, { title: 'SSL', value: 'ssl' }]" :label="$t('pages.settings.settings.encryption')" clearable /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="form.mail_password" :label="$t('pages.settings.settings.smtpPassword')" type="password" autocomplete="new-password" :hint="savedSetting?.mail_password_configured ? $t('pages.settings.settings.smtpPasswordSavedHint') : $t('pages.settings.settings.smtpPasswordGmailHint')" persistent-hint /><VCheckbox v-if="savedSetting?.mail_password_configured" v-model="form.clear_mail_password" :label="$t('pages.settings.settings.removeSavedSmtpPassword')" density="compact" hide-details /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model="form.mail_reply_to" :label="$t('pages.settings.settings.replyToEmail')" type="email" /></VCol>
          </VRow>

          <VRow v-else-if="activeSettingsPage === 'operations'">
            <VCol cols="12" sm="6"><VTextField v-model="form.business_licence" :label="$t('pages.settings.settings.businessLicence')" :placeholder="$t('pages.settings.settings.licenceNumber')" /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model.number="form.paid_off_days_per_year" :label="$t('pages.settings.settings.paidOffDaysPerYear')" type="number" min="0" max="366" /></VCol>
            <VCol cols="12" sm="6"><AppSelect v-model="form.office_open_time" :items="timeOptions" :label="$t('pages.settings.settings.officeOpens')" clearable /></VCol>
            <VCol cols="12" sm="6"><AppSelect v-model="form.office_close_time" :items="timeOptions" :label="$t('pages.settings.settings.officeCloses')" clearable /></VCol>
            <VCol cols="12" sm="6"><VTextField v-model.number="form.default_break_minutes" :label="$t('pages.settings.settings.defaultBreakMinutes')" type="number" min="0" max="720" /></VCol>
          </VRow>

          <template v-else-if="activeSettingsPage === 'appearance'">
              <h4 class="text-h6 mb-2 font-weight-medium">{{ $t('pages.settings.settings.vuexyThemeColors') }}</h4>
              <p class="text-body-2 text-medium-emphasis mb-4">{{ $t('pages.settings.settings.themeColorsHint') }}</p>
              <VRow>
                <VCol v-for="item in [
                  { label: translate('pages.settings.settings.primaryColor'), field: 'primary_color' as const },
                  { label: translate('pages.settings.settings.secondaryColor'), field: 'secondary_color' as const },
                  { label: translate('pages.settings.settings.tertiaryColor'), field: 'tertiary_color' as const },
                ]" :key="item.field" cols="12" sm="4" class="d-flex flex-column align-center">
                  <span class="text-subtitle-2 mb-2">{{ item.label }}</span>
                  <VColorPicker v-model="form[item.field]" mode="hex" hide-inputs canvas-height="100" width="100%"
                    :disabled="!canEditSettings" @update:model-value="handleColorPickerUpdate(item.field)" />
                  <div class="color-swatch text-caption font-mono mt-2 px-2 py-1 rounded"
                    :style="colorPreviewStyle(form[item.field])">{{ form[item.field] || $t('pages.settings.settings.notSet') }}</div>
                </VCol>
              </VRow>
          </template>

          <template v-else>
          <VAlert type="info" variant="tonal" class="mb-5">
            {{ $t('pages.settings.settings.uploadCleanA4PortraitArtworkContractTextIsLayeredOverTheseImagesAndThe') }}
          </VAlert>
          <VRow>
            <VCol v-for="asset in [
              { field: 'contract_letterhead' as const, title: translate('pages.settings.settings.contractLetterhead'), preview: letterheadPreview, hint: translate('pages.settings.settings.minimum10001400Pixels') },
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
                  <input type="file" accept="image/jpeg,image/png,image/webp" class="file-overlay"
                    :disabled="!canEditSettings" @change="handleImageUpload($event, asset.field)">
                </VBtn>
                <VBtn v-if="asset.preview" color="error" variant="tonal" :disabled="!canEditSettings"
                  @click="removeArtwork(asset.field)">
                  <VIcon icon="tabler-trash" class="me-2" />{{ $t('common.actions.remove') }}
                </VBtn>
              </div>
              <p class="text-caption text-medium-emphasis mt-2">{{ $t('pages.settings.settings.assetHint', {
                hint:
                  asset.hint
              }) }}</p>
              <div v-if="asset.field === 'contract_letterhead'" class="mt-4">
                <p class="text-caption text-medium-emphasis mb-2">Drag any edge of the box to define the print-safe area. Contract text and details will stay inside this area.</p>
                <div class="letterhead-safe-preview">
                  <VImg v-if="letterheadPreview" :src="letterheadPreview" cover />
                  <div v-else class="letterhead-preview-placeholder">Upload a full-page letterhead to position its safe area.</div>
                  <div class="letterhead-safe-box" :style="letterheadSafeAreaStyle">
                    <div class="preview-copy" dir="rtl">این متن نمونه فارسی در محدوده امن چاپ باقی می‌ماند.</div>
                    <div class="preview-copy" dir="ltr">English lorem ipsum stays inside the print-safe area.</div>
                    <button v-for="edge in ['top', 'right', 'bottom', 'left']" :key="edge" type="button" :class="`safe-area-handle safe-area-handle--${edge}`" :aria-label="`Resize safe area ${edge}`" @pointerdown="resizeLetterheadSafeArea($event, edge as 'top' | 'right' | 'bottom' | 'left')" />
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>
          </template>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-5 px-6 justify-end ga-3">
          <VBtn color="secondary" variant="tonal" :disabled="isLoading || isSaving || !canEditSettings" @click="resetSettings(true, activeSettingsPage)">{{ $t('pages.settings.settings.resetPage') }}</VBtn>
          <VBtn color="primary" :loading="isSaving" :disabled="isLoading || !canEditSettings" @click="saveSettings">{{ $t('pages.settings.settings.saveChanges') }}</VBtn>
        </VCardActions>
      </VCol>
    </VRow>
  </VCard>
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

.letterhead-safe-preview {
  position: relative;
  width: 100%;
  max-width: 360px;
  aspect-ratio: 210 / 297;
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.letterhead-safe-preview > :first-child { height: 100%; }
.letterhead-preview-placeholder { display: grid; height: 100%; place-items: center; padding: 1rem; text-align: center; color: rgba(var(--v-theme-on-surface), 0.55); font-size: 0.78rem; }
.letterhead-safe-box { position: absolute; z-index: 1; border: 2px dashed rgb(var(--v-theme-primary)); background: rgb(var(--v-theme-primary) / 0.08); min-width: 5%; min-height: 5%; padding: 8px; display: flex; flex-direction: column; justify-content: space-between; gap: 8px; pointer-events: none; }
.preview-copy { color: rgb(var(--v-theme-on-surface)); font-size: clamp(0.55rem, 2vw, 0.75rem); line-height: 1.45; }
.safe-area-handle { position: absolute; z-index: 2; border: 0; background: transparent; pointer-events: auto; touch-action: none; }
.safe-area-handle--top, .safe-area-handle--bottom { left: 0; width: 100%; height: 12px; cursor: ns-resize; }
.safe-area-handle--top { top: -7px; }.safe-area-handle--bottom { bottom: -7px; }
.safe-area-handle--left, .safe-area-handle--right { top: 0; height: 100%; width: 12px; cursor: ew-resize; }
.safe-area-handle--left { left: -7px; }.safe-area-handle--right { right: -7px; }

.document-artwork-preview {
  width: 100%;
  max-width: 360px;
  aspect-ratio: 210 / 297;
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.03);
}

.settings-shell {
  overflow: hidden;
}

.settings-sidebar {
  background: rgba(var(--v-theme-on-surface), 0.025);
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.settings-sidebar :deep(.v-list-item) {
  margin-block: 4px;
}

.settings-sidebar :deep(.v-list-item__subtitle) {
  font-size: 0.75rem;
}

@media (max-width: 959px) {
  .settings-sidebar {
    border-inline-end: 0;
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
}
</style>
