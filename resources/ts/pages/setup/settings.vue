<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useTheme } from 'vuetify'
import type { ApiResponse, Setting, SettingForm } from '@/api/setting'
import { useAppApi } from '@/composables/useAppApi'
import { useBranding } from '@/composables/useBranding'
import { useAutomaticAttendance } from '@/composables/useAutomaticAttendance'
import { useSettingsValidation } from '@/composables/useSettingsValidation'
import { settingsUploadError } from '@/api/settingValidation'
import { canAccess } from '@/utils/authorization'
import { contrastingTextColor } from '@/utils/themeContrast'
import { type ThemeColorField, type ThemeColorPreferences, applyThemePalette, persistThemeColors, themeColorDefaults, themeColorFields } from '@/utils/themePersistence'
import ContractDocumentDesignApi, { type ContractDocumentDesign, type ContractDocumentDesignSafeArea } from '@/api/contractDocumentDesigns'
import ContractTypeApi, { type ContractType } from '@/api/contractType'
import type { BuiltInService } from '@/api/applicants'
import OfficeSettingsPanel from '@/components/setup/OfficeSettingsPanel.vue'
import SupportPage from '@/pages/support.vue'

type UploadField = 'website_logo' | 'company_signature' | 'contract_letterhead' | 'contract_cover_design' | 'receipt_letterhead' | 'payslip_letterhead'
type DocumentLetterheadKind = 'receipt' | 'payslip'
type SettingsPage = 'company' | 'email' | 'operations' | 'appearance' | 'documents' | 'office' | 'support'

const toast = useToast()
const { settings } = useAppApi()
const { updateBranding } = useBranding()
const { setEnabled: setAutomaticAttendanceEnabled } = useAutomaticAttendance()
const vuetifyTheme = useTheme()
const route = useRoute()
const router = useRouter()
const canEditSettings = computed(() => canAccess('edit_settings'))
const canViewOffices = computed(() => canAccess('view_offices'))
const settingsPageNames: SettingsPage[] = ['company', 'email', 'operations', 'appearance', 'documents', 'office', 'support']
const validSettingsPage = (section: unknown): section is SettingsPage => settingsPageNames.includes(section as SettingsPage) && (section !== 'office' || canViewOffices.value)
const activeSettingsPage = ref<SettingsPage>(validSettingsPage(route.params.section) ? route.params.section : 'company')

const { t: translate } = useI18n()
const { errors: fieldErrors, inputProps: settingInputProps, clear: clearSettingErrors, capture: captureSettingError } = useSettingsValidation()
const { errors: designFieldErrors, inputProps: designInputProps, clear: clearDesignErrors, capture: captureDesignError } = useSettingsValidation()

const defaultForm = (): SettingForm => ({
  ...themeColorDefaults,
  off_day_allowance_days_per_year: 0,
  missing_attendance_status: 'absent',
  website_title: 'Educational Agency Portal',
  website_logo: null,
  remove_website_logo: false,
  company_signature: null,
  remove_company_signature: false,
  contract_letterhead: null,
  contract_cover_design: null,
  remove_contract_letterhead: false,
  remove_contract_cover_design: false,
  contract_letterhead_safe_x: 8.5,
  contract_letterhead_safe_y: 15,
  contract_letterhead_safe_width: 83,
  contract_letterhead_safe_height: 70,
  receipt_letterhead: null,
  remove_receipt_letterhead: false,
  receipt_letterhead_source: 'custom',
  receipt_letterhead_safe_x: 6,
  receipt_letterhead_safe_y: 18,
  receipt_letterhead_safe_width: 88,
  receipt_letterhead_safe_height: 64,
  payslip_letterhead: null,
  remove_payslip_letterhead: false,
  payslip_letterhead_source: 'custom',
  payslip_letterhead_safe_x: 8,
  payslip_letterhead_safe_y: 14,
  payslip_letterhead_safe_width: 84,
  payslip_letterhead_safe_height: 72,
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
  email_delivery_enabled: false,
  timezone: 'Asia/Kabul',
  phone: '',
  physical_address: '',
  short_company_name: 'Educational Agency',
  original_company_name: 'Educational Agency',
  business_licence: '',
  office_open_time: '09:00',
  office_close_time: '17:00',
  default_break_minutes: 0,
  automatic_attendance_enabled: false,
})

const form = ref<SettingForm>(defaultForm())
const persistedForm = ref<SettingForm>(defaultForm())
const savedSetting = ref<Setting | null>(null)
const settingId = ref<number | null>(null)
const logoPreview = ref<string | null>(null)
const signaturePreview = ref<string | null>(null)
const letterheadPreview = ref<string | null>(null)
const coverDesignPreview = ref<string | null>(null)
const receiptLetterheadPreview = ref<string | null>(null)
const payslipLetterheadPreview = ref<string | null>(null)
const isLoading = ref(false)
const isSaving = ref(false)
const objectUrls = new Set<string>()
const documentDesigns = ref<ContractDocumentDesign[]>([])
const contractTypes = ref<ContractType[]>([])
const selectedDesignTypeId = ref<number | null>(null)
const selectedDesignService = ref<BuiltInService | null>(null)
const designLetterhead = ref<File | null>(null)
const designCover = ref<File | null>(null)
const designRemoveLetterhead = ref(false)
const designRemoveCover = ref(false)
const designLetterheadPreview = ref<string | null>(null)
const designCoverPreview = ref<string | null>(null)
const designSafeArea = ref<ContractDocumentDesignSafeArea>({ x: 8.5, y: 15, width: 83, height: 70 })
const designSaving = ref(false)
const designDirty = ref(false)

const designScopeMatches = (design: ContractDocumentDesign, typeId: number | null, service: BuiltInService | null) =>
  Number(design.contract_type_id) === Number(typeId ?? 0) && design.service === service

const selectedDocumentDesign = computed(() => documentDesigns.value.find(design => designScopeMatches(design, selectedDesignTypeId.value, selectedDesignService.value)) ?? null)
const typeOptions = computed(() => [
  { title: translate('pages.settings.settings.generalArtworkScope'), value: null },
  ...contractTypes.value.filter(type => type.is_active).map(type => ({ title: type.name, value: type.id })),
])
const selectedDesignType = computed(() => contractTypes.value.find(type => type.id === selectedDesignTypeId.value) ?? null)
// Only customer contracts scope artwork per service; employment and other
// contracts carry exactly one cover/letterhead for the whole type.
const supportsServiceScope = computed(() => selectedDesignType.value?.slug === 'customer')
const isDesignScope = computed(() => selectedDesignTypeId.value !== null)
const serviceOptions = computed(() => (['scholarship', 'test_offer', 'academy', 'private'] as BuiltInService[]).map(service => ({ title: translate(`educational.builtInServices.${service === 'test_offer' ? 'testOffer' : service}`), value: service })))
// Human-readable label of the scope the artwork editor is currently writing to.
const designScopeLabel = computed(() => {
  if (!isDesignScope.value)
    return translate('pages.settings.settings.generalArtworkScope')

  const typeName = selectedDesignType.value?.name ?? ''
  const service = supportsServiceScope.value && selectedDesignService.value
    ? serviceOptions.value.find(option => option.value === selectedDesignService.value)?.title
    : null

  return service ? `${typeName} · ${service}` : typeName
})

const activeLetterheadPreview = computed(() => isDesignScope.value ? designLetterheadPreview.value : letterheadPreview.value)
const activeCoverPreview = computed(() => isDesignScope.value ? designCoverPreview.value : coverDesignPreview.value)
const activeSafeArea = computed<ContractDocumentDesignSafeArea>(() => isDesignScope.value
  ? designSafeArea.value
  : {
      x: form.value.contract_letterhead_safe_x ?? 8.5,
      y: form.value.contract_letterhead_safe_y ?? 15,
      width: form.value.contract_letterhead_safe_width ?? 83,
      height: form.value.contract_letterhead_safe_height ?? 70,
    })
const activeArtworkPreview = (field: 'contract_letterhead' | 'contract_cover_design') =>
  field === 'contract_letterhead' ? activeLetterheadPreview.value : activeCoverPreview.value

const settingsPages = computed(() => [
  { value: 'company' as const, icon: 'tabler-building', title: translate('pages.settings.settings.companyPage'), description: translate('pages.settings.settings.companyPageDescription') },
  { value: 'email' as const, icon: 'tabler-mail-cog', title: translate('pages.settings.settings.emailDeliveryPage'), description: translate('pages.settings.settings.emailDeliveryPageDescription') },
  { value: 'operations' as const, icon: 'tabler-clock', title: translate('pages.settings.settings.operationsPage'), description: translate('pages.settings.settings.operationsPageDescription') },
  { value: 'appearance' as const, icon: 'tabler-palette', title: translate('pages.settings.settings.appearancePage'), description: translate('pages.settings.settings.appearancePageDescription') },
  { value: 'documents' as const, icon: 'tabler-file-description', title: translate('pages.settings.settings.documentsPage'), description: translate('pages.settings.settings.documentsPageDescription') },
  { value: 'support' as const, icon: 'tabler-lifebuoy', title: translate('common.navigation.support'), description: translate('pages.settings.settings.supportPageDescription') },
  ...(canViewOffices.value ? [{ value: 'office' as const, icon: 'tabler-building-community', title: translate('common.fields.office'), description: translate('pages.settings.settings.officePageDescription') }] : []),
])

const activePage = computed(() => settingsPages.value.find(page => page.value === activeSettingsPage.value)!)
// Office and Support render entirely self-managed panels, so the shared Save/Reset actions do not apply.
const showsSettingsActions = computed(() => activeSettingsPage.value !== 'office' && activeSettingsPage.value !== 'support')

const appearanceGroups = computed(() => [
  {
    title: translate('pages.settings.settings.brandColors'),
    description: translate('pages.settings.settings.brandColorsHint'),
    fields: [
      { label: translate('pages.settings.settings.primaryColor'), field: 'primary_color' as const },
      { label: translate('pages.settings.settings.secondaryColor'), field: 'secondary_color' as const },
    ],
  },
  {
    title: translate('pages.settings.settings.lightModeSurfaces'),
    description: translate('pages.settings.settings.lightModeSurfacesHint'),
    fields: [
      { label: translate('pages.settings.settings.applicationBackground'), field: 'light_background_color' as const },
      { label: translate('pages.settings.settings.cardsAndMenus'), field: 'light_surface_color' as const },
    ],
  },
  {
    title: translate('pages.settings.settings.darkModeSurfaces'),
    description: translate('pages.settings.settings.darkModeSurfacesHint'),
    fields: [
      { label: translate('pages.settings.settings.applicationBackground'), field: 'dark_background_color' as const },
      { label: translate('pages.settings.settings.cardsAndMenus'), field: 'dark_surface_color' as const },
    ],
  },
])

const pageFields: Record<SettingsPage, (keyof SettingForm)[]> = {
  company: ['website_title', 'website_logo', 'remove_website_logo', 'company_signature', 'remove_company_signature', 'website_address', 'timezone', 'phone', 'physical_address', 'short_company_name', 'original_company_name'],
  email: ['email_delivery_enabled', 'company_email', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'clear_mail_password', 'mail_from_name', 'mail_reply_to'],
  operations: ['off_day_allowance_days_per_year', 'business_licence', 'office_open_time', 'office_close_time', 'default_break_minutes', 'automatic_attendance_enabled', 'missing_attendance_status'],
  appearance: themeColorFields,
  documents: ['contract_letterhead', 'contract_cover_design', 'remove_contract_letterhead', 'remove_contract_cover_design', 'contract_letterhead_safe_x', 'contract_letterhead_safe_y', 'contract_letterhead_safe_width', 'contract_letterhead_safe_height', 'receipt_letterhead', 'remove_receipt_letterhead', 'receipt_letterhead_source', 'receipt_letterhead_safe_x', 'receipt_letterhead_safe_y', 'receipt_letterhead_safe_width', 'receipt_letterhead_safe_height', 'payslip_letterhead', 'remove_payslip_letterhead', 'payslip_letterhead_source', 'payslip_letterhead_safe_x', 'payslip_letterhead_safe_y', 'payslip_letterhead_safe_width', 'payslip_letterhead_safe_height'],
  office: [],
  // The Support sub-page owns its own API and form; nothing here is part of the settings record.
  support: [],
}
const hasEmailErrors = computed(() => pageFields.email.some(field => !!fieldErrors.value[field]))
const artworkKey = (field: 'contract_letterhead' | 'contract_cover_design') => field === 'contract_letterhead' ? 'letterhead' : 'cover_design'
const uploadError = (field: UploadField) => fieldErrors.value[field] || fieldErrors.value[`remove_${field}`]
const artworkInputProps = (field: 'contract_letterhead' | 'contract_cover_design') => isDesignScope.value ? designInputProps(artworkKey(field)) : settingInputProps(field)
const artworkError = (field: 'contract_letterhead' | 'contract_cover_design') => isDesignScope.value ? designFieldErrors.value[artworkKey(field)] || designFieldErrors.value[`remove_${artworkKey(field)}`] : uploadError(field)
const safeInputProps = (field: keyof ContractDocumentDesignSafeArea) => isDesignScope.value ? designInputProps(`safe_${field}`) : settingInputProps(`contract_letterhead_safe_${field}`)
const documentSafeField = (kind: DocumentLetterheadKind, field: keyof ContractDocumentDesignSafeArea) => `${kind}_letterhead_safe_${field}`

function revealInvalidSection(field: string) {
  const section = settingsPageNames.find(page => pageFields[page].includes(field as keyof SettingForm))
  if (section) activeSettingsPage.value = section
}

function validateUpload(file: File, field: UploadField, design = false): boolean {
  const key = design ? artworkKey(field as 'contract_letterhead' | 'contract_cover_design') : field
  const errors = design ? designFieldErrors : fieldErrors
  const message = settingsUploadError(file, key)
  if (!message) {
    if (design) { clearDesignErrors(key); clearDesignErrors(`remove_${key}`) }
    else { clearSettingErrors(key); clearSettingErrors(`remove_${key}`) }
    return true
  }
  // Keep the invalid selection in the draft so Save cannot silently ignore it.
  if (design) {
    if (key === 'letterhead') { designLetterhead.value = file; designRemoveLetterhead.value = false }
    else { designCover.value = file; designRemoveCover.value = false }
    designDirty.value = true
  }
  else {
    form.value[field] = file
    form.value[`remove_${field}` as keyof SettingForm] = false as never
  }
  errors.value = { ...errors.value, [key]: message }
  toast.error(message)
  return false
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
  const palette = themeColorFields.reduce<ThemeColorPreferences>((colors, field) => {
    const color = form.value[field]
    if (typeof color === 'string')
      colors[field] = color

    return colors
  }, {})

  Object.values(vuetifyTheme.themes.value).forEach(theme => applyThemePalette(theme, palette))
}

function applyRouteSection(section: unknown) {
  activeSettingsPage.value = validSettingsPage(section) ? section : 'company'
}

watch(() => route.params.section, applyRouteSection)
watch(activeSettingsPage, section => {
  if (route.params.section !== section)
    router.replace({ name: 'setup-settings', params: { section } })
})

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

  const savedForm: SettingForm = {
    ...defaultForm(),
    primary_color: setting.primary_color,
    secondary_color: setting.secondary_color,
    light_background_color: setting.light_background_color,
    light_surface_color: setting.light_surface_color,
    dark_background_color: setting.dark_background_color,
    dark_surface_color: setting.dark_surface_color,
    off_day_allowance_days_per_year: Number(setting.off_day_allowance_days_per_year ?? 0),
    missing_attendance_status: setting.missing_attendance_status ?? 'absent',
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
    email_delivery_enabled: setting.email_delivery_enabled ?? false,
    timezone: setting.timezone || 'UTC',
    phone: setting.phone || '',
    physical_address: setting.physical_address || '',
    short_company_name: setting.short_company_name || setting.website_title,
    original_company_name: setting.original_company_name || setting.website_title,
    business_licence: setting.business_licence || '',
    office_open_time: setting.office_open_time || '09:00',
    office_close_time: setting.office_close_time || '17:00',
    default_break_minutes: setting.default_break_minutes ?? 0,
    automatic_attendance_enabled: setting.automatic_attendance_enabled,
    contract_letterhead_safe_x: setting.contract_letterhead_safe_x ?? 8.5,
    contract_letterhead_safe_y: setting.contract_letterhead_safe_y ?? 15,
    contract_letterhead_safe_width: setting.contract_letterhead_safe_width ?? 83,
    contract_letterhead_safe_height: setting.contract_letterhead_safe_height ?? 70,
    receipt_letterhead_source: setting.receipt_letterhead_source ?? 'custom',
    receipt_letterhead_safe_x: setting.receipt_letterhead_safe_x ?? 6,
    receipt_letterhead_safe_y: setting.receipt_letterhead_safe_y ?? 18,
    receipt_letterhead_safe_width: setting.receipt_letterhead_safe_width ?? 88,
    receipt_letterhead_safe_height: setting.receipt_letterhead_safe_height ?? 64,
    payslip_letterhead_source: setting.payslip_letterhead_source ?? 'custom',
    payslip_letterhead_safe_x: setting.payslip_letterhead_safe_x ?? 8,
    payslip_letterhead_safe_y: setting.payslip_letterhead_safe_y ?? 14,
    payslip_letterhead_safe_width: setting.payslip_letterhead_safe_width ?? 84,
    payslip_letterhead_safe_height: setting.payslip_letterhead_safe_height ?? 72,
  }

  form.value = savedForm
  setAutomaticAttendanceEnabled(setting.automatic_attendance_enabled)
  persistedForm.value = { ...savedForm }
  logoPreview.value = setting.website_logo_url || setting.website_logo || null
  signaturePreview.value = setting.company_signature_url
  letterheadPreview.value = setting.contract_letterhead_url
  coverDesignPreview.value = setting.contract_cover_design_url
  receiptLetterheadPreview.value = setting.receipt_letterhead_url
  payslipLetterheadPreview.value = setting.payslip_letterhead_url
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
  finally {
    isLoading.value = false
  }
}

async function loadDocumentDesigns() {
  const [types, designs] = await Promise.all([ContractTypeApi.getAll(), ContractDocumentDesignApi.getAll()])
  contractTypes.value = types
  documentDesigns.value = designs
}

function releasePreview(url: string | null) {
  if (url && objectUrls.has(url)) {
    URL.revokeObjectURL(url)
    objectUrls.delete(url)
  }
}

/** Mirror the selected scope's saved design (or the settings fallback) into the artwork editor. */
function syncDesignScopeFromSelection() {
  clearDesignErrors()
  const design = selectedDocumentDesign.value

  releasePreview(designLetterheadPreview.value)
  releasePreview(designCoverPreview.value)
  designLetterhead.value = null
  designCover.value = null
  designRemoveLetterhead.value = false
  designRemoveCover.value = false
  designLetterheadPreview.value = design?.letterhead_url ?? null
  designCoverPreview.value = design?.cover_design_url ?? null
  designSafeArea.value = {
    x: Number(design?.safe_x ?? 8.5),
    y: Number(design?.safe_y ?? 15),
    width: Number(design?.safe_width ?? 83),
    height: Number(design?.safe_height ?? 70),
  }
  designDirty.value = false
}

// The service scope exists for customer contracts only.
watch(selectedDesignTypeId, () => {
  if (!supportsServiceScope.value)
    selectedDesignService.value = null
})

watch([selectedDesignTypeId, selectedDesignService, documentDesigns], syncDesignScopeFromSelection)

function resetDesignScopeChanges() {
  syncDesignScopeFromSelection()
}

async function saveDocumentDesign() {
  if (designSaving.value || !canEditSettings.value || !selectedDesignTypeId.value)
    return
  clearDesignErrors()
  designSaving.value = true
  try {
    const saved = await ContractDocumentDesignApi.save(selectedDocumentDesign.value?.id ?? null, {
      contractTypeId: selectedDesignTypeId.value,
      service: supportsServiceScope.value ? selectedDesignService.value : null,
      letterhead: designLetterhead.value,
      coverDesign: designCover.value,
      removeLetterhead: designRemoveLetterhead.value,
      removeCoverDesign: designRemoveCover.value,
      safeArea: { ...designSafeArea.value },
    }, { quiet: true })

    designLetterhead.value = null
    designCover.value = null
    designRemoveLetterhead.value = false
    designRemoveCover.value = false
    designDirty.value = false
    documentDesigns.value = [...documentDesigns.value.filter(design => design.id !== saved.id), saved]
    toast.success(translate('pages.settings.settings.artworkScopeSaved'))
  }
  catch (error) {
    // Keep the draft artwork and dimensions available for correction.
    await captureDesignError(error, translate('settingsValidation.saveFailed'))
  }
  finally { designSaving.value = false }
}

/**
 * The artwork cards upload into whichever scope is selected: a contract-design
 * row (type, optionally narrowed to a service) or the general settings record.
 */
function handleArtworkUpload(event: Event, field: 'contract_letterhead' | 'contract_cover_design') {
  if (!isDesignScope.value) {
    handleImageUpload(event, field)

    return
  }

  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file)
    return

  if (!validateUpload(file, field, true)) {
    ;(event.target as HTMLInputElement).value = ''
    return
  }

  const preview = previewFile(file)

  if (field === 'contract_letterhead') {
    releasePreview(designLetterheadPreview.value)
    designLetterhead.value = file
    designLetterheadPreview.value = preview
    designRemoveLetterhead.value = false
  }
  else {
    releasePreview(designCoverPreview.value)
    designCover.value = file
    designCoverPreview.value = preview
    designRemoveCover.value = false
  }

  designDirty.value = true
  ;(event.target as HTMLInputElement).value = ''
}

function handleImageUpload(event: Event, field: UploadField) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file)
    return

  if (!validateUpload(file, field)) {
    ;(event.target as HTMLInputElement).value = ''
    return
  }

  const oldPreview = field === 'website_logo'
    ? logoPreview.value
    : field === 'company_signature'
      ? signaturePreview.value
      : field === 'contract_letterhead'
        ? letterheadPreview.value
        : coverDesignPreview.value

  if (oldPreview && oldPreview.startsWith('blob:')) {
    URL.revokeObjectURL(oldPreview)
    objectUrls.delete(oldPreview)
  }

  form.value[field] = file
  form.value[`remove_${field}` as keyof SettingForm] = false as never

  const preview = previewFile(file)
  if (field === 'website_logo')
    logoPreview.value = preview
  if (field === 'company_signature')
    signaturePreview.value = preview
  if (field === 'contract_letterhead') {
    letterheadPreview.value = preview
    form.value.remove_contract_letterhead = false
  }
  if (field === 'contract_cover_design') {
    coverDesignPreview.value = preview
    form.value.remove_contract_cover_design = false
  }
  if (field === 'receipt_letterhead') {
    receiptLetterheadPreview.value = preview
    form.value.remove_receipt_letterhead = false
  }
  if (field === 'payslip_letterhead') {
    payslipLetterheadPreview.value = preview
    form.value.remove_payslip_letterhead = false
  }

  ; (event.target as HTMLInputElement).value = ''
}

/**
 * Per-document letterhead form keys. Receipt and payslip share one editor;
 * only the field names and safe-zone defaults differ.
 */
const documentLetterheadConfig: Record<DocumentLetterheadKind, {
  file: 'receipt_letterhead' | 'payslip_letterhead'
  remove: 'remove_receipt_letterhead' | 'remove_payslip_letterhead'
  source: 'receipt_letterhead_source' | 'payslip_letterhead_source'
  safeX: 'receipt_letterhead_safe_x' | 'payslip_letterhead_safe_x'
  safeY: 'receipt_letterhead_safe_y' | 'payslip_letterhead_safe_y'
  safeWidth: 'receipt_letterhead_safe_width' | 'payslip_letterhead_safe_width'
  safeHeight: 'receipt_letterhead_safe_height' | 'payslip_letterhead_safe_height'
  defaults: { x: number; y: number; width: number; height: number }
}> = {
  receipt: {
    file: 'receipt_letterhead', remove: 'remove_receipt_letterhead', source: 'receipt_letterhead_source',
    safeX: 'receipt_letterhead_safe_x', safeY: 'receipt_letterhead_safe_y', safeWidth: 'receipt_letterhead_safe_width', safeHeight: 'receipt_letterhead_safe_height',
    defaults: { x: 6, y: 18, width: 88, height: 64 },
  },
  payslip: {
    file: 'payslip_letterhead', remove: 'remove_payslip_letterhead', source: 'payslip_letterhead_source',
    safeX: 'payslip_letterhead_safe_x', safeY: 'payslip_letterhead_safe_y', safeWidth: 'payslip_letterhead_safe_width', safeHeight: 'payslip_letterhead_safe_height',
    defaults: { x: 8, y: 14, width: 84, height: 72 },
  },
}

const documentLetterheadPreviewRef = (kind: DocumentLetterheadKind) => kind === 'receipt'
  ? receiptLetterheadPreview
  : payslipLetterheadPreview

/** A document-letterhead card always writes its own file, never a design row. */
function handleDocumentLetterheadUpload(event: Event, kind: DocumentLetterheadKind) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file)
    return

  const config = documentLetterheadConfig[kind]
  if (!validateUpload(file, config.file)) {
    ;(event.target as HTMLInputElement).value = ''
    return
  }
  const previewRef = documentLetterheadPreviewRef(kind)
  const preview = previewFile(file)

  releasePreview(previewRef.value)
  form.value[config.file] = file
  previewRef.value = preview
  form.value[config.remove] = false

  ;(event.target as HTMLInputElement).value = ''
}

function removeDocumentLetterhead(kind: DocumentLetterheadKind) {
  if (!confirm(translate('pages.settings.settings.confirmRemoveFile')))
    return

  const config = documentLetterheadConfig[kind]
  clearSettingErrors(config.file)
  clearSettingErrors(config.remove)
  const previewRef = documentLetterheadPreviewRef(kind)

  releasePreview(previewRef.value)
  form.value[config.file] = null
  previewRef.value = null
  form.value[config.remove] = true
}

const documentLetterheadSource = (kind: DocumentLetterheadKind) => form.value[documentLetterheadConfig[kind].source] ?? 'custom'

function setDocumentLetterheadSource(kind: DocumentLetterheadKind, value: unknown) {
  const source = (value === 'contract' || value === 'none') ? value : 'custom'

  form.value[documentLetterheadConfig[kind].source] = source
}

/** The preview shown for a document letterhead, honouring the selected source. */
const documentLetterheadPreview = (kind: DocumentLetterheadKind): string | null => {
  const source = documentLetterheadSource(kind)

  if (source === 'contract')
    return letterheadPreview.value
  if (source === 'none')
    return null

  return documentLetterheadPreviewRef(kind).value
}

const documentSafeArea = (kind: DocumentLetterheadKind) => {
  const config = documentLetterheadConfig[kind]

  return {
    x: Number(form.value[config.safeX] ?? config.defaults.x),
    y: Number(form.value[config.safeY] ?? config.defaults.y),
    width: Number(form.value[config.safeWidth] ?? config.defaults.width),
    height: Number(form.value[config.safeHeight] ?? config.defaults.height),
  }
}

const documentSafeAreaStyle = (kind: DocumentLetterheadKind) => ({
  left: `${documentSafeArea(kind).x}%`,
  top: `${documentSafeArea(kind).y}%`,
  width: `${documentSafeArea(kind).width}%`,
  height: `${documentSafeArea(kind).height}%`,
})

function updateDocumentSafeArea(kind: DocumentLetterheadKind, patch: Partial<ContractDocumentDesignSafeArea>) {
  const config = documentLetterheadConfig[kind]

  if (patch.x !== undefined)
    form.value[config.safeX] = patch.x
  if (patch.y !== undefined)
    form.value[config.safeY] = patch.y
  if (patch.width !== undefined)
    form.value[config.safeWidth] = patch.width
  if (patch.height !== undefined)
    form.value[config.safeHeight] = patch.height
}

function setDocumentSafeAreaFromInput(kind: DocumentLetterheadKind, field: keyof ContractDocumentDesignSafeArea, value: unknown) {
  const numeric = String(value ?? '').trim() === '' ? Number.NaN : Number(value)

  updateDocumentSafeArea(kind, { [field]: numeric } as Partial<ContractDocumentDesignSafeArea>)
}

function resizeDocumentSafeArea(event: PointerEvent, edge: 'top' | 'right' | 'bottom' | 'left', kind: DocumentLetterheadKind) {
  const preview = (event.currentTarget as HTMLElement).closest('.letterhead-safe-preview') as HTMLElement | null
  if (!preview)
    return
  event.preventDefault()

  const initial = { ...documentSafeArea(kind) }
  const rect = preview.getBoundingClientRect()

  const update = (move: PointerEvent) => {
    const horizontal = ((move.clientX - event.clientX) / rect.width) * 100
    const vertical = ((move.clientY - event.clientY) / rect.height) * 100
    let { x, y, width, height } = initial
    if (edge === 'left') { x = Math.max(0, Math.min(initial.x + horizontal, initial.x + initial.width - 5)); width = initial.width + initial.x - x }
    if (edge === 'right')
      width = Math.max(5, Math.min(100 - initial.x, initial.width + horizontal))
    if (edge === 'top') { y = Math.max(0, Math.min(initial.y + vertical, initial.y + initial.height - 5)); height = initial.height + initial.y - y }
    if (edge === 'bottom')
      height = Math.max(5, Math.min(100 - initial.y, initial.height + vertical))
    updateDocumentSafeArea(kind, {
      x: Number(x.toFixed(2)),
      y: Number(y.toFixed(2)),
      width: Number(width.toFixed(2)),
      height: Number(height.toFixed(2)),
    })
  }

  const stop = () => window.removeEventListener('pointermove', update)

  window.addEventListener('pointermove', update)
  window.addEventListener('pointerup', stop, { once: true })
}

function removeArtwork(field: 'contract_letterhead' | 'contract_cover_design') {
  if (!confirm(translate('pages.settings.settings.confirmRemoveFile')))
    return
  if (isDesignScope.value) { clearDesignErrors(artworkKey(field)); clearDesignErrors(`remove_${artworkKey(field)}`) }
  else clearSettingErrors(field)

  const currentPreview = activeArtworkPreview(field)

  if (currentPreview && currentPreview.startsWith('blob:')) {
    URL.revokeObjectURL(currentPreview)
    objectUrls.delete(currentPreview)
  }

  if (isDesignScope.value) {
    if (field === 'contract_letterhead') {
      designLetterhead.value = null
      designLetterheadPreview.value = null
      designRemoveLetterhead.value = true
    }
    else {
      designCover.value = null
      designCoverPreview.value = null
      designRemoveCover.value = true
    }

    designDirty.value = true

    return
  }

  form.value[field] = null
  if (field === 'contract_letterhead') {
    letterheadPreview.value = null
    form.value.remove_contract_letterhead = true
  }
  else {
    coverDesignPreview.value = null
    form.value.remove_contract_cover_design = true
  }
}

/**
 * Delete an uploaded asset (logo, signature or a document letterhead). The
 * stored file is removed server-side and the setting cleared; the caller is
 * always asked to confirm first.
 */
function removeUpload(field: 'website_logo' | 'company_signature') {
  if (!confirm(translate('pages.settings.settings.confirmRemoveFile')))
    return
  clearSettingErrors(field)
  clearSettingErrors(`remove_${field}`)

  const clearPreview = (target: Ref<string | null>) => {
    if (target.value?.startsWith('blob:'))
      URL.revokeObjectURL(target.value)
    target.value = null
  }

  form.value[field] = null

  if (field === 'website_logo') {
    clearPreview(logoPreview)
    form.value.remove_website_logo = true
  }
  else {
    clearPreview(signaturePreview)
    form.value.remove_company_signature = true
  }
}

const letterheadSafeAreaStyle = computed(() => ({
  left: `${activeSafeArea.value.x}%`,
  top: `${activeSafeArea.value.y}%`,
  width: `${activeSafeArea.value.width}%`,
  height: `${activeSafeArea.value.height}%`,
}))

/** Sizing writes go to the selected design row, or to the settings fallback. */
function updateSafeArea(patch: Partial<ContractDocumentDesignSafeArea>) {
  if (isDesignScope.value) {
    designSafeArea.value = { ...designSafeArea.value, ...patch }
    designDirty.value = true

    return
  }

  if (patch.x !== undefined)
    form.value.contract_letterhead_safe_x = patch.x
  if (patch.y !== undefined)
    form.value.contract_letterhead_safe_y = patch.y
  if (patch.width !== undefined)
    form.value.contract_letterhead_safe_width = patch.width
  if (patch.height !== undefined)
    form.value.contract_letterhead_safe_height = patch.height
}

/** Preserve invalid entries for validation instead of silently turning them into zero. */
function setSafeAreaFromInput(field: keyof ContractDocumentDesignSafeArea, value: unknown) {
  const numeric = String(value ?? '').trim() === '' ? Number.NaN : Number(value)

  updateSafeArea({ [field]: numeric } as Partial<ContractDocumentDesignSafeArea>)
}

function resizeLetterheadSafeArea(event: PointerEvent, edge: 'top' | 'right' | 'bottom' | 'left') {
  const preview = (event.currentTarget as HTMLElement).closest('.letterhead-safe-preview') as HTMLElement | null
  if (!preview)
    return
  event.preventDefault()

  const initial = { ...activeSafeArea.value }

  const rect = preview.getBoundingClientRect()

  const update = (move: PointerEvent) => {
    const horizontal = ((move.clientX - event.clientX) / rect.width) * 100
    const vertical = ((move.clientY - event.clientY) / rect.height) * 100
    let { x, y, width, height } = initial
    if (edge === 'left') { x = Math.max(0, Math.min(initial.x + horizontal, initial.x + initial.width - 5)); width = initial.width + initial.x - x }
    if (edge === 'right')
      width = Math.max(5, Math.min(100 - initial.x, initial.width + horizontal))
    if (edge === 'top') { y = Math.max(0, Math.min(initial.y + vertical, initial.y + initial.height - 5)); height = initial.height + initial.y - y }
    if (edge === 'bottom')
      height = Math.max(5, Math.min(100 - initial.y, initial.height + vertical))
    updateSafeArea({
      x: Number(x.toFixed(2)),
      y: Number(y.toFixed(2)),
      width: Number(width.toFixed(2)),
      height: Number(height.toFixed(2)),
    })
  }

  const stop = () => window.removeEventListener('pointermove', update)

  window.addEventListener('pointermove', update)
  window.addEventListener('pointerup', stop, { once: true })
}

const colorPreviewStyle = (color: string | null | undefined) => color
  ? { backgroundColor: color, color: contrastingTextColor(color) }
  : {}

function handleColorPickerUpdate(field: ThemeColorField) {
  clearSettingErrors(field)
  applyTheme()
}

function restoreThemeColor(field: ThemeColorField) {
  clearSettingErrors(field)
  form.value[field] = themeColorDefaults[field]
  applyTheme()
}

function restoreThemeDefaults() {
  themeColorFields.forEach(field => clearSettingErrors(field))
  themeColorFields.forEach(field => form.value[field] = themeColorDefaults[field])
  applyTheme()
}

function resetSettings(apply: boolean = false, page: SettingsPage | null = null) {
  if (page) pageFields[page].forEach(field => clearSettingErrors(field))
  else clearSettingErrors()
  if (!page)
    revokeObjectUrls()

  // Properly revert to saved state to prevent data loss bug where UI shows existing
  // assets but form payload sends null (potentially deleting them on the backend)
  if (savedSetting.value && !page) {
    applySavedSetting(savedSetting.value)
  }
  else if (savedSetting.value && page) {
    const draft = { ...form.value }
    const previews = { logo: logoPreview.value, signature: signaturePreview.value, letterhead: letterheadPreview.value, cover: coverDesignPreview.value, receipt: receiptLetterheadPreview.value }

    applySavedSetting(savedSetting.value)

    const resetFields = new Set(pageFields[page])

    // Restore drafts outside the page being reset.
    ;(Object.keys(draft) as (keyof SettingForm)[]).forEach(field => {
      if (!resetFields.has(field))
        form.value[field] = draft[field] as never
    })
    if (page !== 'company') {
      logoPreview.value = previews.logo
      signaturePreview.value = previews.signature
    }
    if (page !== 'documents') {
      letterheadPreview.value = previews.letterhead
      coverDesignPreview.value = previews.cover
      receiptLetterheadPreview.value = previews.receipt
    }
  }
  else {
    form.value = defaultForm()
    settingId.value = null
    logoPreview.value = null
    signaturePreview.value = null
    letterheadPreview.value = null
    coverDesignPreview.value = null
    receiptLetterheadPreview.value = null
  }

  if (page === null || page === 'documents')
    resetDesignScopeChanges()

  if (apply)
    applyTheme()
}

function activePagePayload(): Partial<SettingForm> {
  const payload: Partial<SettingForm> = {}

  pageFields[activeSettingsPage.value].forEach(field => {
    const value = form.value[field]
    const savedValue = persistedForm.value[field]

    // Files and explicit removal flags are the only non-scalar updates.
    if (value instanceof File) {
      payload[field] = value as never

      return
    }
    if (field === 'remove_website_logo' || field === 'remove_company_signature' || field === 'remove_contract_letterhead' || field === 'remove_contract_cover_design' || field === 'remove_receipt_letterhead' || field === 'remove_payslip_letterhead') {
      if (value === true)
        payload[field] = true as never

      return
    }
    if (field === 'mail_password') {
      if (typeof value === 'string' && value.length > 0)
        payload[field] = value as never

      return
    }
    if (field === 'clear_mail_password') {
      if (value === true)
        payload[field] = true as never

      return
    }
    if (value !== savedValue && (value === null || typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean'))
      payload[field] = value as never
  })

  return payload
}

async function saveSettings() {
  if (isSaving.value || !canEditSettings.value)
    return
  if (settingId.value) pageFields[activeSettingsPage.value].forEach(field => clearSettingErrors(field))
  else clearSettingErrors()
  isSaving.value = true

  try {
    const payload = activePagePayload()

    const isCreating = settingId.value === null
    let response: ApiResponse<Setting>

    if (settingId.value) {
      if (Object.keys(payload).length === 0) {
        toast.info(translate('pages.settings.settings.noChangesToSave'))

        return
      }
      response = await settings.update(settingId.value, payload, { quiet: true })
    }
    else {
      if (activeSettingsPage.value !== 'company') {
        activeSettingsPage.value = 'company'
        toast.info(translate('pages.settings.settings.completeCompanyProfileFirst'))

        return
      }

      // A first record must satisfy the server's required company defaults.
      response = await settings.create(form.value, { quiet: true })
    }

    const colorsToPersist = isCreating
      ? themeColorFields
      : themeColorFields.filter(field => field in payload)

    persistThemeColors(Object.fromEntries(colorsToPersist.map(field => [field, response.data[field]])))

    const previews = { logo: logoPreview.value, signature: signaturePreview.value, letterhead: letterheadPreview.value, cover: coverDesignPreview.value, receipt: receiptLetterheadPreview.value }

    // The API returns the complete record. Keep drafts from other pages intact.
    const otherPageDraft = { ...form.value }

    applySavedSetting(response.data)

    const activeFields = new Set(pageFields[activeSettingsPage.value])

    ;(Object.keys(otherPageDraft) as (keyof SettingForm)[]).forEach(field => {
      if (!activeFields.has(field))
        form.value[field] = otherPageDraft[field] as never
    })
    if (activeSettingsPage.value !== 'company') {
      logoPreview.value = previews.logo
      signaturePreview.value = previews.signature
    }
    if (activeSettingsPage.value !== 'documents') {
      letterheadPreview.value = previews.letterhead
      coverDesignPreview.value = previews.cover
      receiptLetterheadPreview.value = previews.receipt
    }
    updateBranding(response.data)
    toast.success(translate('pages.settings.settings.settingsSavedSuccessfully'))
  }
  catch (error) {
    await captureSettingError(error, translate('settingsValidation.saveFailed'), revealInvalidSection)
  }
  finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadSettings(), loadDocumentDesigns()])
})
onBeforeUnmount(revokeObjectUrls)
</script>

<template>
  <VAlert
    v-if="!canEditSettings && showsSettingsActions"
    type="info"
    variant="tonal"
    class="mb-6"
  >
    {{ $t('pages.settings.settings.youCanViewCompanySettingsButYouDoNotHavePermissionToEditThem') }}
  </VAlert>

  <VCard
    class="settings-shell"
    :loading="isLoading"
  >
    <VRow no-gutters>
      <VCol
        cols="12"
        class="settings-mobile-navigation d-md-none"
      >
        <div class="pa-4">
          <VMenu location="bottom start">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                variant="tonal"
                color="primary"
                block
                class="settings-mobile-navigation__trigger justify-space-between"
              >
                <span class="d-flex align-center text-truncate">
                  <VIcon
                    :icon="activePage.icon"
                    class="me-2"
                  />
                  {{ activePage.title }}
                </span>
                <VIcon icon="tabler-chevron-down" />
              </VBtn>
            </template>
            <VList
              nav
              density="comfortable"
              min-width="240"
            >
              <VListItem
                v-for="page in settingsPages"
                :key="page.value"
                :active="activeSettingsPage === page.value"
                :prepend-icon="page.icon"
                :to="{ name: 'setup-settings', params: { section: page.value } }"
                :title="page.title"
              />
            </VList>
          </VMenu>
        </div>
      </VCol>
      <VCol
        cols="12"
        md="3"
        class="settings-sidebar d-none d-md-block"
      >
        <div class="pa-6 pb-4">
          <div class="text-overline text-primary font-weight-bold">
            {{ $t('pages.settings.settings.settings') }}
          </div>
          <div class="text-h6 font-weight-bold">
            {{ $t('pages.settings.settings.companySettings') }}
          </div>
          <p class="text-body-2 text-medium-emphasis mb-0 mt-1">
            {{ $t('pages.settings.settings.manageCompanyPreferences') }}
          </p>
        </div>
        <VList
          nav
          density="comfortable"
          class="px-3 pb-5"
        >
          <VListItem
            v-for="page in settingsPages"
            :key="page.value"
            :active="activeSettingsPage === page.value"
            :prepend-icon="page.icon"
            rounded="lg"
            :to="{ name: 'setup-settings', params: { section: page.value } }"
          >
            <VListItemTitle>{{ page.title }}</VListItemTitle>
          </VListItem>
        </VList>
      </VCol>

      <VCol
        cols="12"
        md="9"
        class="settings-content"
      >
        <div class="settings-heading pa-6 pb-2 d-flex align-start justify-space-between ga-4">
          <div>
            <div class="d-flex align-center ga-3">
              <VAvatar
                size="38"
                color="primary"
                variant="tonal"
              >
                <VIcon :icon="activePage.icon" />
              </VAvatar>
              <div>
                <h1 class="text-h5 font-weight-bold">
                  {{ activePage.title }}
                </h1>
                <p class="text-body-2 text-medium-emphasis mb-0 mt-1">
                  {{ activePage.description }}
                </p>
              </div>
            </div>
          </div>
          <VChip
            v-if="activeSettingsPage === 'email'"
            :color="savedSetting?.mail_password_configured ? 'success' : 'secondary'"
            size="small"
            variant="tonal"
          >
            {{ savedSetting?.mail_password_configured ? $t('pages.settings.settings.smtpPasswordConfigured') : $t('pages.settings.settings.usingDefaultMailConfiguration') }}
          </VChip>
        </div>

        <VCardText class="pa-6 pt-4">
          <OfficeSettingsPanel v-if="activeSettingsPage === 'office'" :automatic-attendance-enabled="Boolean(form.automatic_attendance_enabled)" />
          <SupportPage
            v-else-if="activeSettingsPage === 'support'"
            embedded
          />
          <VRow v-else-if="activeSettingsPage === 'company'">
            <VCol
              cols="12"
              md="4"
              class="text-center d-flex flex-column align-center justify-center border-md-e"
            >
              <VAvatar
                rounded
                size="120"
                class="mb-4 elevation-2 bg-light"
              >
                <VImg
                  :src="logoPreview ?? undefined"
                  alt="Website logo preview"
                  cover
                />
              </VAvatar>
              <VBtn
                :color="uploadError('website_logo') ? 'error' : 'primary'"
                variant="tonal"
                density="comfortable"
                class="position-relative"
                :disabled="!canEditSettings"
                :data-settings-field="'website_logo'"
                :class="{ 'settings-upload-error': !!uploadError('website_logo') }"
              >
                <VIcon
                  icon="tabler-upload"
                  class="me-2"
                />{{ $t('pages.settings.settings.uploadNewLogo') }}
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  class="file-overlay"
                  :disabled="!canEditSettings"
                  @change="handleImageUpload($event, 'website_logo')"
                  :name="'website_logo'"
                  :aria-invalid="!!uploadError('website_logo')"
                >
              </VBtn>
                <p v-if="uploadError('website_logo')" role="alert" class="text-error text-caption mt-2">{{ uploadError('website_logo') }}</p>
              <p class="text-caption text-medium-emphasis mt-2">
                {{ $t('pages.settings.settings.jpgPNGGIFOrWEBPUpTo2MB')
                }}
              </p>
              <VBtn
                v-if="savedSetting?.website_logo || form.website_logo"
                color="error"
                variant="text"
                density="comfortable"
                class="mt-2"
                :disabled="!canEditSettings"
                @click="removeUpload('website_logo')"
              >
                <VIcon
                  icon="tabler-trash"
                  class="me-2"
                />{{ $t('pages.settings.settings.removeFile') }}
              </VBtn>

              <VDivider class="my-5 w-100" />
              <div class="text-subtitle-2 mb-2">
                {{ $t('pages.settings.settings.authorizedSignature') }}
              </div>
              <VImg
                v-if="signaturePreview"
                :src="signaturePreview"
                alt="Company signature preview"
                width="180"
                height="80"
                contain
                class="asset-preview mb-3"
              />
              <VIcon
                v-else
                icon="tabler-signature"
                size="54"
                class="text-disabled mb-3"
              />
              <VBtn
                :color="uploadError('company_signature') ? 'error' : 'primary'"
                variant="tonal"
                density="comfortable"
                class="position-relative"
                :disabled="!canEditSettings"
                :data-settings-field="'company_signature'"
                :class="{ 'settings-upload-error': !!uploadError('company_signature') }"
              >
                <VIcon
                  icon="tabler-upload"
                  class="me-2"
                />{{ $t('pages.settings.settings.uploadSignature') }}
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="file-overlay"
                  :disabled="!canEditSettings"
                  @change="handleImageUpload($event, 'company_signature')"
                  :name="'company_signature'"
                  :aria-invalid="!!uploadError('company_signature')"
                >
              </VBtn>
                <p v-if="uploadError('company_signature')" role="alert" class="text-error text-caption mt-2">{{ uploadError('company_signature') }}</p>
              <p class="text-caption text-medium-emphasis mt-2">
                {{ $t('pages.settings.settings.shownOnPrintedContracts')
                }}
              </p>
              <VBtn
                v-if="signaturePreview"
                color="error"
                variant="text"
                density="comfortable"
                class="mt-2"
                :disabled="!canEditSettings"
                @click="removeUpload('company_signature')"
              >
                <VIcon
                  icon="tabler-trash"
                  class="me-2"
                />{{ $t('pages.settings.settings.removeFile') }}
              </VBtn>
            </VCol>

            <VCol
              cols="12"
              md="8"
              class="ps-md-6"
            >
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="form.website_title"
                    v-bind="settingInputProps('website_title')"
                    :label="$t('pages.settings.settings.websiteTitle')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="form.short_company_name"
                    v-bind="settingInputProps('short_company_name')"
                    :label="$t('pages.settings.settings.shortCompanyName')"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextField
                    v-model="form.original_company_name"
                    v-bind="settingInputProps('original_company_name')"
                    :label="$t('pages.settings.settings.originalCompanyName')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="form.website_address"
                    v-bind="settingInputProps('website_address')"
                    :label="$t('pages.settings.settings.websiteAddress')"
                    :placeholder="$t('pages.settings.settings.httpsExampleCom')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="form.phone"
                    v-bind="settingInputProps('phone')"
                    :label="$t('common.fields.phone')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppSelect
                    v-model="form.timezone"
                    v-bind="settingInputProps('timezone')"
                    :items="timezoneOptions"
                    :label="$t('pages.settings.settings.companyTimezone')"
                    autocomplete="off"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextarea
                    v-model="form.physical_address"
                    v-bind="settingInputProps('physical_address')"
                    :label="$t('pages.settings.settings.physicalAddress')"
                    rows="2"
                    auto-grow
                  />
                </VCol>
              </VRow>
            </VCol>
          </VRow>

          <VRow v-else-if="activeSettingsPage === 'email'">
            <VCol cols="12">
              <VAlert
                type="info"
                variant="tonal"
                icon="tabler-shield-lock"
              >
                {{ $t('pages.settings.settings.emailDeliveryHint') }}
              </VAlert>
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.email_delivery_enabled"
                v-bind="settingInputProps('email_delivery_enabled')"
                color="primary"
                inset
                :label="$t('pages.settings.settings.emailDeliveryEnabled')"
                :hint="$t('pages.settings.settings.emailDeliveryEnabledHint')"
                persistent-hint
                :disabled="!canEditSettings"
              />
            </VCol>
            <template v-if="form.email_delivery_enabled || hasEmailErrors">
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.company_email"
                v-bind="settingInputProps('company_email')"
                :label="$t('pages.settings.settings.fromEmailAddress')"
                type="email"
                :placeholder="$t('pages.settings.settings.helloExampleCom')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.mail_from_name"
                v-bind="settingInputProps('mail_from_name')"
                :label="$t('pages.settings.settings.fromName')"
                :placeholder="form.website_title"
              />
            </VCol>
            <VCol cols="12">
              <div class="text-overline text-medium-emphasis">
                {{ $t('pages.settings.settings.smtpServer') }}
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="8"
            >
              <VTextField
                v-model="form.mail_host"
                v-bind="settingInputProps('mail_host')"
                :label="$t('pages.settings.settings.smtpHost')"
                placeholder="smtp.example.com"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model.number="form.mail_port"
                v-bind="settingInputProps('mail_port')"
                :label="$t('pages.settings.settings.smtpPort')"
                type="number"
                min="1"
                max="65535"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.mail_username"
                v-bind="settingInputProps('mail_username')"
                :label="$t('pages.settings.settings.smtpUsername')"
                autocomplete="username"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.mail_encryption"
                v-bind="settingInputProps('mail_encryption')"
                :items="[{ title: $t('pages.settings.settings.tlsRecommended'), value: 'tls' }, { title: 'SSL', value: 'ssl' }]"
                :label="$t('pages.settings.settings.encryption')"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.mail_password"
                v-bind="settingInputProps('mail_password')"
                :label="$t('pages.settings.settings.smtpPassword')"
                type="password"
                autocomplete="new-password"
                :hint="savedSetting?.mail_password_configured ? $t('pages.settings.settings.smtpPasswordSavedHint') : $t('pages.settings.settings.smtpPasswordGmailHint')"
                persistent-hint
              /><VCheckbox
                v-if="savedSetting?.mail_password_configured"
                v-model="form.clear_mail_password"
                v-bind="settingInputProps('clear_mail_password')"
                :label="$t('pages.settings.settings.removeSavedSmtpPassword')"
                density="compact"
                hide-details="auto"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.mail_reply_to"
                v-bind="settingInputProps('mail_reply_to')"
                :label="$t('pages.settings.settings.replyToEmail')"
                type="email"
              />
            </VCol>
            </template>
          </VRow>

          <VRow v-else-if="activeSettingsPage === 'operations'">
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.business_licence"
                v-bind="settingInputProps('business_licence')"
                :label="$t('pages.settings.settings.businessLicence')"
                :placeholder="$t('pages.settings.settings.licenceNumber')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model.number="form.off_day_allowance_days_per_year"
                v-bind="settingInputProps('off_day_allowance_days_per_year')"
                :label="$t('pages.settings.settings.offDayAllowanceDaysPerYear')"
                :hint="$t('pages.settings.settings.offDayAllowanceDaysPerYearHint')"
                persistent-hint
                type="number"
                min="0"
                max="366"
                step="0.5"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.office_open_time"
                v-bind="settingInputProps('office_open_time')"
                :items="timeOptions"
                :label="$t('pages.settings.settings.officeOpens')"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.office_close_time"
                v-bind="settingInputProps('office_close_time')"
                :items="timeOptions"
                :label="$t('pages.settings.settings.officeCloses')"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model.number="form.default_break_minutes"
                v-bind="settingInputProps('default_break_minutes')"
                :label="$t('pages.settings.settings.defaultBreakMinutes')"
                type="number"
                min="0"
                max="720"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.automatic_attendance_enabled"
                v-bind="settingInputProps('automatic_attendance_enabled')"
                color="primary"
                inset
                :label="$t('pages.settings.settings.automaticAttendanceEnabled')"
                :hint="$t('pages.settings.settings.automaticAttendanceEnabledHint')"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VSelect
                v-model="form.missing_attendance_status"
                v-bind="settingInputProps('missing_attendance_status')"
                :items="[
                  { title: $t('common.attendanceStatuses.present'), value: 'present' },
                  { title: $t('common.attendanceStatuses.absent'), value: 'absent' },
                  { title: $t('common.attendanceStatuses.offDay'), value: 'off_day' },
                ]"
                :label="$t('pages.settings.settings.missingAttendanceStatus')"
                :hint="$t('pages.settings.settings.missingAttendanceStatusHint')"
                persistent-hint
              />
            </VCol>
            <VCol cols="12">
              <VAlert
                type="warning"
                variant="tonal"
                density="compact"
                icon="tabler-cloud-storm"
                class="mb-2"
              >
                {{ $t('pages.settings.settings.attendanceWeatherWarning') }}
              </VAlert>
            </VCol>
          </VRow>

          <template v-else-if="activeSettingsPage === 'appearance'">
            <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-5">
              <div>
                <h4 class="text-h6 mb-2 font-weight-medium">
                  {{ $t('pages.settings.settings.vuexyThemeColors') }}
                </h4>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  {{ $t('pages.settings.settings.themeColorsHint') }}
                </p>
              </div>
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="!canEditSettings"
                @click="restoreThemeDefaults"
              >
                <VIcon
                  icon="tabler-restore"
                  class="me-2"
                />{{ $t('pages.settings.settings.restoreVuexyDefaults') }}
              </VBtn>
            </div>

            <VAlert
              type="info"
              variant="tonal"
              density="compact"
              class="mb-5"
            >
              {{ $t('pages.settings.settings.themeColorsPreviewHint') }}
            </VAlert>

            <VCard
              v-for="group in appearanceGroups"
              :key="group.title"
              variant="outlined"
              class="appearance-group mb-5"
            >
              <VCardItem>
                <VCardTitle class="text-subtitle-1 font-weight-medium">
                  {{ group.title }}
                </VCardTitle>
                <VCardSubtitle>{{ group.description }}</VCardSubtitle>
              </VCardItem>
              <VCardText class="pt-0">
                <VRow>
                  <VCol
                    v-for="item in group.fields"
                    :key="item.field"
                    cols="12"
                    sm="6"
                    md="4"
                    class="d-flex flex-column"
                  >
                    <div class="d-flex align-center justify-space-between mb-2 ga-2">
                      <span class="text-subtitle-2">{{ item.label }}</span>
                      <VBtn
                        icon="tabler-restore"
                        size="x-small"
                        variant="text"
                        :disabled="!canEditSettings"
                        :aria-label="`${$t('pages.settings.settings.restoreVuexyDefaults')}: ${item.label}`"
                        @click="restoreThemeColor(item.field)"
                      />
                    </div>
                    <VColorPicker
                      v-model="form[item.field]"
                      :data-settings-field="item.field"
                      :class="{ 'settings-color-error': !!fieldErrors[item.field] }"
                      :aria-invalid="!!fieldErrors[item.field]"
                      mode="hex"
                      hide-inputs
                      canvas-height="90"
                      width="100%"
                      :disabled="!canEditSettings"
                      @update:model-value="handleColorPickerUpdate(item.field)"
                    />
                    <p v-if="fieldErrors[item.field]" role="alert" class="text-error text-caption mt-2">{{ fieldErrors[item.field] }}</p>
                    <div
                      class="color-swatch text-caption font-mono mt-2 px-2 py-1 rounded d-flex align-center justify-space-between"
                      :style="colorPreviewStyle(form[item.field])"
                    >
                      <span>{{ form[item.field] }}</span><span>{{ $t('pages.settings.settings.live') }}</span>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </template>

          <template v-else>
            <VAlert
              type="info"
              variant="tonal"
              class="mb-5"
            >
              {{ $t('pages.settings.settings.documentArtworkHint') }}
            </VAlert>
            <VRow>
              <!--
                Scope selection lives with the artwork it edits: the contract
                type — and, for customer contracts, an optional service —
                decides which row the cards below read and write. This is the
                only artwork editor on the page; the file selects are below.
              -->
              <VCol cols="12">
                <VRow density="compact">
                  <VCol cols="12" md="6">
                    <VSelect
                      v-model="selectedDesignTypeId"
                      v-bind="designInputProps('contract_type_id')"
                      :items="typeOptions"
                      :label="$t('pages.settings.settings.contractTypeScope')"
                      :disabled="!canEditSettings"
                    />
                  </VCol>
                  <VCol
                    v-if="supportsServiceScope"
                    cols="12"
                    md="6"
                  >
                    <VSelect
                      v-model="selectedDesignService"
                      v-bind="designInputProps('service')"
                      :items="serviceOptions"
                      :label="$t('pages.settings.settings.serviceScopeOptional')"
                      clearable
                      :disabled="!canEditSettings"
                    />
                  </VCol>
                </VRow>
                <p
                  v-if="supportsServiceScope"
                  class="text-caption text-medium-emphasis mt-1"
                >
                  {{ $t('pages.settings.settings.serviceScopeHint') }}
                </p>
                <p
                  v-else-if="isDesignScope"
                  class="text-caption text-medium-emphasis mt-1"
                >
                  {{ $t('pages.settings.settings.singleTypeScopeHint') }}
                </p>
                <div class="d-flex flex-wrap align-center ga-2 mt-2">
                  <VChip
                    :color="isDesignScope && designDirty ? 'warning' : 'secondary'"
                    size="small"
                    variant="tonal"
                    class="me-2"
                  >
                    {{ designScopeLabel }}
                  </VChip>
                  <VBtn
                    v-if="isDesignScope"
                    color="primary"
                    size="small"
                    prepend-icon="tabler-device-floppy"
                    :loading="designSaving"
                    :disabled="!canEditSettings || !designDirty"
                    @click="saveDocumentDesign"
                  >
                    {{ $t('pages.settings.settings.saveArtworkForScope') }}
                  </VBtn>
                  <VBtn
                    v-if="isDesignScope"
                    color="secondary"
                    variant="tonal"
                    size="small"
                    prepend-icon="tabler-restore"
                    :disabled="!canEditSettings || !designDirty"
                    @click="resetDesignScopeChanges"
                  >
                    {{ $t('pages.settings.settings.resetArtworkScope') }}
                  </VBtn>
                </div>
                <p class="text-caption text-medium-emphasis mt-2 mb-0">
                  {{ $t('pages.settings.settings.artworkEditorScopeHint', { scope: designScopeLabel }) }}
                </p>
              </VCol>
              <VCol
                v-for="asset in [
                  { field: 'contract_letterhead' as const, title: translate('pages.settings.settings.contractLetterhead'), hint: translate('pages.settings.settings.documentImageHint') },
                  { field: 'contract_cover_design' as const, title: translate('pages.settings.settings.contractCoverDesign'), hint: translate('pages.settings.settings.documentImageHint') },
                ]"
                :key="asset.field"
                cols="12"
                md="6"
              >
                <section class="document-artwork-editor">
                <div class="text-subtitle-1 font-weight-medium mb-2">
                  {{ asset.title }}
                </div>
                <div class="document-artwork-preview mb-3">
                  <VImg
                    v-if="activeArtworkPreview(asset.field)"
                    :src="activeArtworkPreview(asset.field) ?? undefined"
                    :alt="$t('pages.settings.settings.documentPreviewAlt', { title: asset.title })"
                    cover
                  />
                  <div
                    v-else
                    class="h-100 d-flex flex-column align-center justify-center text-disabled"
                  >
                    <VIcon
                      icon="tabler-photo"
                      size="44"
                      class="mb-2"
                    />
                    <span>{{ $t('pages.settings.settings.builtInDesignWillBeUsed') }}</span>
                  </div>
                </div>
                <div class="d-flex flex-wrap ga-2">
                  <VBtn
                    :color="artworkError(asset.field) ? 'error' : 'primary'"
                    variant="tonal"
                    class="position-relative"
                    :disabled="!canEditSettings"
                    :data-settings-field="isDesignScope ? artworkKey(asset.field) : asset.field"
                    :class="{ 'settings-upload-error': !!artworkError(asset.field) }"
                  >
                    <VIcon
                      icon="tabler-upload"
                      class="me-2"
                    />{{ $t('common.actions.upload') }}
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      class="file-overlay"
                      :disabled="!canEditSettings"
                      @change="handleArtworkUpload($event, asset.field)"
                      :name="isDesignScope ? artworkKey(asset.field) : asset.field"
                      :aria-invalid="!!artworkError(asset.field)"
                    >
                  </VBtn>
                <p v-if="artworkError(asset.field)" role="alert" class="text-error text-caption mt-2">{{ artworkError(asset.field) }}</p>
                  <VBtn
                    v-if="activeArtworkPreview(asset.field)"
                    color="error"
                    variant="tonal"
                    :disabled="!canEditSettings"
                    @click="removeArtwork(asset.field)"
                  >
                    <VIcon
                      icon="tabler-trash"
                      class="me-2"
                    />{{ $t('common.actions.remove') }}
                  </VBtn>
                </div>
                <p class="text-caption text-medium-emphasis mt-2">
                  {{ $t('pages.settings.settings.assetHint', {
                    hint:
                      asset.hint,
                  }) }}
                </p>
                <div
                  v-if="asset.field === 'contract_letterhead'"
                  class="mt-4"
                >
                  <p class="text-caption text-medium-emphasis mb-2">
                    {{ $t('pages.settings.settings.printSafeAreaHint') }}
                  </p>
                  <div class="letterhead-safe-preview">
                    <VImg
                      v-if="activeLetterheadPreview"
                      :src="activeLetterheadPreview ?? undefined"
                      cover
                    />
                    <div
                      v-else
                      class="letterhead-preview-placeholder"
                    >
                      {{ $t('pages.settings.settings.uploadLetterheadHint') }}
                    </div>
                    <div
                      class="letterhead-safe-box"
                      :style="letterheadSafeAreaStyle"
                    >
                      <div
                        class="preview-copy"
                        dir="rtl"
                      >
                        {{ $t('pages.settings.settings.printSafeAreaSampleRtl') }}
                      </div>
                      <div
                        class="preview-copy"
                        dir="ltr"
                      >
                        {{ $t('pages.settings.settings.printSafeAreaSampleLtr') }}
                      </div>
                      <button
                        v-for="edge in ['top', 'right', 'bottom', 'left']"
                        :key="edge"
                        type="button"
                        :class="`safe-area-handle safe-area-handle--${edge}`"
                        :aria-label="$t('pages.settings.settings.resizePrintSafeArea', { edge: $t(`pages.settings.settings.edges.${edge}`) })"
                        @pointerdown="resizeLetterheadSafeArea($event, edge as 'top' | 'right' | 'bottom' | 'left')"
                      />
                    </div>
                  </div>
                  <p class="text-caption text-medium-emphasis mt-3 mb-2">
                    {{ $t('pages.settings.settings.printSafeAreaSizing') }}
                  </p>
                  <VRow density="compact">
                    <VCol
                      v-for="size in [
                        { field: 'x' as const, label: translate('pages.settings.settings.printSafeAreaOffsetX') },
                        { field: 'y' as const, label: translate('pages.settings.settings.printSafeAreaOffsetY') },
                        { field: 'width' as const, label: translate('pages.settings.settings.printSafeAreaWidth') },
                        { field: 'height' as const, label: translate('pages.settings.settings.printSafeAreaHeight') },
                      ]"
                      :key="size.field"
                      cols="6"
                      sm="3"
                    >
                      <VTextField
                        :model-value="activeSafeArea[size.field]"
                        v-bind="safeInputProps(size.field)"
                        :label="size.label"
                        type="number"
                        suffix="%"
                        density="compact"
                        hide-details="auto"
                        :disabled="!canEditSettings"
                        @update:model-value="setSafeAreaFromInput(size.field, $event)"
                      />
                    </VCol>
                  </VRow>
                </div>
                </section>
              </VCol>

              <!--
                Receipt and payslip letterheads. Same editor style as the
                contract cards above: pick the source, upload/preview artwork,
                and drag the safe-zone where auto-filled text may land.
              -->
              <VCol cols="12">
                <VDivider class="my-3" />
                <h4 class="text-subtitle-1 font-weight-medium mb-1">
                  {{ $t('pages.settings.settings.receiptPayslipDocuments') }}
                </h4>
                <p class="text-body-2 text-medium-emphasis mb-4">
                  {{ $t('pages.settings.settings.receiptPayslipDocumentsHint') }}
                </p>
              </VCol>
              <VCol
                v-for="doc in ([{ kind: 'receipt' as const }, { kind: 'payslip' as const }])"
                :key="doc.kind"
                cols="12"
                md="6"
              >
                <section class="document-artwork-editor">
                <div class="text-subtitle-1 font-weight-medium mb-2">
                  {{ doc.kind === 'receipt' ? $t('pages.settings.settings.receiptLetterhead') : $t('pages.settings.settings.payslipLetterhead') }}
                </div>
                <VSelect
                  :model-value="documentLetterheadSource(doc.kind)"
                  v-bind="settingInputProps(documentLetterheadConfig[doc.kind].source)"
                  :items="[
                    { title: $t('pages.settings.settings.letterheadSourceNone'), value: 'none' },
                    { title: $t('pages.settings.settings.letterheadSourceContract'), value: 'contract' },
                    { title: $t('pages.settings.settings.letterheadSourceCustom'), value: 'custom' },
                  ]"
                  :label="$t('pages.settings.settings.letterheadSource')"
                  density="compact"
                  hide-details="auto"
                  class="mb-3"
                  :disabled="!canEditSettings"
                  @update:model-value="setDocumentLetterheadSource(doc.kind, $event)"
                />
                <div class="document-artwork-preview mb-3">
                  <VImg
                    v-if="documentLetterheadPreview(doc.kind)"
                    :src="documentLetterheadPreview(doc.kind) ?? undefined"
                    :alt="$t('pages.settings.settings.documentPreviewAlt', { title: doc.kind })"
                    cover
                  />
                  <div
                    v-else
                    class="h-100 d-flex flex-column align-center justify-center text-disabled"
                  >
                    <VIcon
                      icon="tabler-photo"
                      size="44"
                      class="mb-2"
                    />
                    <span>{{ $t('pages.settings.settings.builtInDesignWillBeUsed') }}</span>
                  </div>
                </div>
                <div class="d-flex flex-wrap ga-2">
                  <VBtn
                    :color="uploadError(documentLetterheadConfig[doc.kind].file) ? 'error' : 'primary'"
                    variant="tonal"
                    class="position-relative"
                    :disabled="!canEditSettings"
                    :data-settings-field="documentLetterheadConfig[doc.kind].file"
                    :class="{ 'settings-upload-error': !!uploadError(documentLetterheadConfig[doc.kind].file) }"
                  >
                    <VIcon
                      icon="tabler-upload"
                      class="me-2"
                    />{{ $t('common.actions.upload') }}
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      class="file-overlay"
                      :disabled="!canEditSettings"
                      @change="handleDocumentLetterheadUpload($event, doc.kind)"
                      :name="documentLetterheadConfig[doc.kind].file"
                      :aria-invalid="!!uploadError(documentLetterheadConfig[doc.kind].file)"
                    >
                  </VBtn>
                <p v-if="uploadError(documentLetterheadConfig[doc.kind].file)" role="alert" class="text-error text-caption mt-2">{{ uploadError(documentLetterheadConfig[doc.kind].file) }}</p>
                  <VBtn
                    v-if="documentLetterheadSource(doc.kind) === 'custom' && documentLetterheadPreview(doc.kind)"
                    color="error"
                    variant="tonal"
                    :disabled="!canEditSettings"
                    @click="removeDocumentLetterhead(doc.kind)"
                  >
                    <VIcon
                      icon="tabler-trash"
                      class="me-2"
                    />{{ $t('common.actions.remove') }}
                  </VBtn>
                </div>
                <div class="mt-4">
                  <p class="text-caption text-medium-emphasis mb-2">
                    {{ $t('pages.settings.settings.printSafeAreaHint') }}
                  </p>
                  <div class="letterhead-safe-preview">
                    <VImg
                      v-if="documentLetterheadPreview(doc.kind)"
                      :src="documentLetterheadPreview(doc.kind) ?? undefined"
                      cover
                    />
                    <div
                      v-else
                      class="letterhead-preview-placeholder"
                    >
                      {{ $t('pages.settings.settings.uploadLetterheadHint') }}
                    </div>
                    <div
                      class="letterhead-safe-box"
                      :style="documentSafeAreaStyle(doc.kind)"
                    >
                      <div
                        class="preview-copy"
                        dir="rtl"
                      >
                        {{ $t('pages.settings.settings.printSafeAreaSampleRtl') }}
                      </div>
                      <div
                        class="preview-copy"
                        dir="ltr"
                      >
                        {{ $t('pages.settings.settings.printSafeAreaSampleLtr') }}
                      </div>
                      <button
                        v-for="edge in ['top', 'right', 'bottom', 'left']"
                        :key="edge"
                        type="button"
                        :class="`safe-area-handle safe-area-handle--${edge}`"
                        :aria-label="$t('pages.settings.settings.resizePrintSafeArea', { edge: $t(`pages.settings.settings.edges.${edge}`) })"
                        @pointerdown="resizeDocumentSafeArea($event, edge as 'top' | 'right' | 'bottom' | 'left', doc.kind)"
                      />
                    </div>
                  </div>
                  <p class="text-caption text-medium-emphasis mt-3 mb-2">
                    {{ $t('pages.settings.settings.printSafeAreaSizing') }}
                  </p>
                  <VRow density="compact">
                    <VCol
                      v-for="size in [
                        { field: 'x' as const, label: translate('pages.settings.settings.printSafeAreaOffsetX') },
                        { field: 'y' as const, label: translate('pages.settings.settings.printSafeAreaOffsetY') },
                        { field: 'width' as const, label: translate('pages.settings.settings.printSafeAreaWidth') },
                        { field: 'height' as const, label: translate('pages.settings.settings.printSafeAreaHeight') },
                      ]"
                      :key="size.field"
                      cols="6"
                      sm="3"
                    >
                      <VTextField
                        :model-value="documentSafeArea(doc.kind)[size.field]"
                        v-bind="settingInputProps(documentSafeField(doc.kind, size.field))"
                        :label="size.label"
                        type="number"
                        suffix="%"
                        density="compact"
                        hide-details="auto"
                        :disabled="!canEditSettings"
                        @update:model-value="setDocumentSafeAreaFromInput(doc.kind, size.field, $event)"
                      />
                    </VCol>
                  </VRow>
                </div>
                </section>
              </VCol>
            </VRow>
          </template>
        </VCardText>
        <VDivider v-if="showsSettingsActions" />
        <VCardActions v-if="showsSettingsActions" class="pa-5 px-6 justify-end ga-3">
          <VBtn
            color="secondary"
            variant="tonal"
            :disabled="isLoading || isSaving || !canEditSettings"
            @click="resetSettings(true, activeSettingsPage)"
          >
            {{ $t('pages.settings.settings.resetPage') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isSaving"
            :disabled="isLoading || !canEditSettings"
            @click="saveSettings"
          >
            {{ $t('pages.settings.settings.saveChanges') }}
          </VBtn>
        </VCardActions>
      </VCol>
    </VRow>
  </VCard>
</template>

<style scoped>
.settings-color-error,
.settings-upload-error {
  outline: 2px solid rgb(var(--v-theme-error));
  outline-offset: 2px;
  border-radius: 6px;
}

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

.appearance-group {
  background: rgba(var(--v-theme-surface), 0.62);
}

.document-artwork-editor {
  block-size: 100%;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  background: rgba(var(--v-theme-surface), 0.62);
  padding: clamp(1rem, 2vw, 1.25rem);
}

.letterhead-safe-preview {
  position: relative;
  width: clamp(180px, 65vw, 260px);
  aspect-ratio: 210 / 297;
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.letterhead-safe-preview > :first-child { height: 100%; }
.letterhead-preview-placeholder { display: grid; height: 100%; place-items: center; padding: 1rem; text-align: center; color: rgba(var(--v-theme-on-surface), 0.55); font-size: 0.78rem; }
.letterhead-safe-box { position: absolute; z-index: 1; border: 2px dashed rgb(var(--v-theme-primary)); background: rgb(var(--v-theme-primary) / 0.08); min-width: 5%; min-height: 5%; padding: 8px; display: flex; flex-direction: column; justify-content: space-between; gap: 8px; pointer-events: none; }
.preview-copy { color: #000; font-size: clamp(0.55rem, 2vw, 0.75rem); line-height: 1.45; }
.safe-area-handle { position: absolute; z-index: 2; border: 0; background: transparent; pointer-events: auto; touch-action: none; }
.safe-area-handle--top, .safe-area-handle--bottom { left: 0; width: 100%; height: 12px; cursor: ns-resize; }
.safe-area-handle--top { top: -7px; }.safe-area-handle--bottom { bottom: -7px; }
.safe-area-handle--left, .safe-area-handle--right { top: 0; height: 100%; width: 12px; cursor: ew-resize; }
.safe-area-handle--left { left: -7px; }.safe-area-handle--right { right: -7px; }

.document-artwork-preview {
  width: clamp(180px, 65vw, 260px);
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

@media (max-width: 959px) {
  .settings-mobile-navigation {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
}
</style>
