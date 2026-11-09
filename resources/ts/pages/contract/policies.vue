<script setup lang="ts">
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { type PlaceholderGroup, getPlaceholders } from '@/api/contractPlaceholders'
import ContractPolicyApi, { type ContractPolicy } from '@/api/contractPolicy'
import ContractTemplateApi, { type ContractTemplate, type ContractTemplateCreateData, type ContractTemplateVersion } from '@/api/contractTemplate'
import type { BuiltInService } from '@/api/applicants'
import ContractTypeApi, { type ContractType, SYSTEM_TYPE_SLUGS } from '@/api/contractType'
import PositionApi, { type Position } from '@/api/position'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import ContractContentEditor from '@/components/contract/ContractContentEditor.vue'
import ContractTypeSelector from '@/components/contract/ContractTypeSelector.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { extractPlaceholderKeys, hydratePlaceholders, legacyContentToHtml, localizePlaceholderGroups, serializePlaceholders } from '@/utils/contractPlaceholders'
import { translateBackendValue, translateContractPolicyType, translateContractTypeRecord } from '@/utils/localization'
import { canAccess } from '@/utils/authorization'
import { moveRowIdFirst } from '@/utils/createdFirst'

const route = useRoute()
const toast = useToast()
const { locale, t } = useI18n()
const tr = (key: string, values: Record<string, unknown> = {}) => t(`pages.settings.policies.policies.contractTemplate.${key}`, values)
const props = withDefaults(defineProps<{ embedded?: boolean }>(), { embedded: false })

const formRef = ref()
const contractName = ref('')
const contractTypeId = ref<number | null>(null)
const coverText = ref('')
const positionId = ref<number | null>(null)
const builtInService = ref<BuiltInService | null>(null)
const savedBuiltInService = ref<BuiltInService | null>(null)
// Academy keeps creating a normal contract, but it is not managed from
// Templates & Policies: it is ensured by a hidden, seeded template instead.
const builtInServices: BuiltInService[] = ['scholarship', 'test_offer', 'private']
const builtInLabel = (service: BuiltInService) => t(`educational.builtInServices.${service === 'test_offer' ? 'testOffer' : service}`)
const policyIds = ref<number[]>([])
const contentHtml = ref('')
const selectedPolicyId = ref<number | null>(null)
const contractTypes = ref<ContractType[]>([])
const allPolicies = ref<ContractPolicy[]>([])
const positions = ref<Position[]>([])
const templates = ref<ContractTemplate[]>([])
const templateId = ref<number | null>(null)
const rawPlaceholderGroups = ref<PlaceholderGroup[]>([])
const isLoading = ref(false)
const deleteDialog = ref(false)
const templateToDelete = ref<number | null>(null)
const editorRef = ref<InstanceType<typeof ContractContentEditor> | null>(null)
const pristineTemplateState = ref('')
const countries = ref<MasterDataRecord[]>([])
const languages = ref<MasterDataRecord[]>([])
const tests = ref<MasterDataRecord[]>([])
const versions = ref<ContractTemplateVersion[]>([])
const versionsLoading = ref(false)
const versionDialog = ref(false)
const activatingVersionId = ref<number | null>(null)
const versionCountryFilter = ref<number | null>(null)
const versionTestFilter = ref<number | null>(null)
const versionLanguageFilter = ref<number | null>(null)
const versionForm = ref({ name: '', country_id: null as number | null, test_id: null as number | null, language_id: null as number | null })
const initialVersion = ref({ name: '', country_id: null as number | null, test_id: null as number | null, language_id: null as number | null })
const editingVersionId = ref<number | null>(null)
const previewVersion = ref<ContractTemplateVersion | null>(null)
const editorDialog = ref(false)
const templateSearch = ref('')
const templateTypeFilter = ref<number | null>(null)
const templateServiceFilter = ref<string | null>(null)
const templatePositionFilter = ref<number | null>(null)
const templateDateFrom = ref('')
const templateDateTo = ref('')
const resetTemplateFilters = () => { templateSearch.value = ''; templateTypeFilter.value = null; templateServiceFilter.value = null; templatePositionFilter.value = null; templateCountryFilter.value = null; templateLanguageFilter.value = null; templateDateFrom.value = ''; templateDateTo.value = '' }
const templateCountryFilter = ref<number | null>(null)
const templateLanguageFilter = ref<number | null>(null)

const policyScopeCountryId = computed(() => templateId.value ? versionCountryFilter.value : initialVersion.value.country_id)
const policyScopeLanguageId = computed(() => templateId.value ? versionLanguageFilter.value : initialVersion.value.language_id)
const policies = computed(() => allPolicies.value.filter(policy =>
  policy.is_active
  && policy.type === selectedType.value?.slug
  && (!policy.service || policy.service === builtInService.value)
  && (!policy.position_id || policy.position_id === positionId.value)
  && (!policy.country_id || policy.country_id === policyScopeCountryId.value)
  && (!policy.language_id || policy.language_id === policyScopeLanguageId.value)
  && !policyIds.value.includes(policy.id)))
const selectedType = computed(() => contractTypes.value.find(type => type.id === contractTypeId.value) ?? null)
const isEmployment = computed(() => selectedType.value?.slug === 'employment')
const isServiceType = computed(() => selectedType.value?.slug === 'customer')
// Only customer (built-in service) and employment templates are versioned.
// Other agreements keep one document that is edited directly on the template.
const usesVersioning = computed(() => isEmployment.value || isServiceType.value)
// Test-offer versions are keyed by the test, every other service by country.
const isTestOffer = computed(() => builtInService.value === 'test_offer')
const serviceBindingLocked = computed(() => !!templateId.value && !!savedBuiltInService.value)
const serviceItems = computed(() => builtInServices.map(service => ({
  title: builtInLabel(service),
  value: service,
  props: { disabled: templates.value.some(template => template.service === service && template.id !== templateId.value) },
})))
const isSystemSlug = (slug: string) => SYSTEM_TYPE_SLUGS.includes(slug as typeof SYSTEM_TYPE_SLUGS[number])
const contractTypeName = (type: ContractType | null) => translateContractTypeRecord(type)
const templateTypeName = (template: any) => template.type_slug ? translateContractPolicyType(template.type_slug, template.type) : template.type || tr('custom')
const policyTypeName = (policy: ContractPolicy) => translateContractPolicyType(policy.type)

const typeLabel = computed(() => {
  const name = contractTypeName(selectedType.value)

  return name && selectedType.value && isSystemSlug(selectedType.value.slug) ? `${name} — ${tr('systemType')}` : name
})

const placeholderGroups = computed(() => localizePlaceholderGroups(rawPlaceholderGroups.value))

const knownKeys = computed(() => new Set(placeholderGroups.value.flatMap(group => group.fields.map(field => field.key))))

const templateState = computed(() => JSON.stringify({
  name: contractName.value,
  type: contractTypeId.value,
  position: positionId.value,
  builtInService: builtInService.value,
  policies: policyIds.value,
  cover: coverText.value,
  content: serializePlaceholders(contentHtml.value),
  editingVersion: editingVersion.value ? { id: editingVersion.value.id, name: editingVersion.value.name } : null,
  initialVersion: templateId.value ? null : initialVersion.value,
}))

const hasTemplateChanges = computed(() => templateState.value !== pristineTemplateState.value)
const canCreateVersions = computed(() => canAccess('create_contract_template_versions'))
const canEditDraftVersions = computed(() => canAccess('edit_draft_contract_template_versions'))
const canActivateVersions = computed(() => canAccess('activate_contract_template_versions'))
const canArchiveVersions = computed(() => canAccess('archive_contract_template_versions'))
const editingVersion = computed(() => versions.value.find(version => version.id === editingVersionId.value) ?? null)
const visibleVersions = computed(() => versions.value.filter(version => (!versionCountryFilter.value || version.country_id === versionCountryFilter.value) && (!versionTestFilter.value || version.test_id === versionTestFilter.value) && (!versionLanguageFilter.value || version.language_id === versionLanguageFilter.value)))
const versionCountryName = (version: ContractTemplateVersion) => version.country?.name ?? countries.value.find(country => country.id === version.country_id)?.name ?? '—'
const versionTestName = (version: ContractTemplateVersion) => version.test?.name ?? tests.value.find(test => test.id === version.test_id)?.name ?? '—'
const versionLanguageName = (version: ContractTemplateVersion) => version.language?.name ?? languages.value.find(language => language.id === version.language_id)?.name ?? '—'
const filteredTemplates = computed(() => templates.value.filter(template => {
  const search = templateSearch.value.trim().toLowerCase()
  const matchesSearch = !search || template.name.toLowerCase().includes(search) || templateTypeName(template).toLowerCase().includes(search)
  const versions = template.versions ?? []
  return matchesSearch
    && (!templateTypeFilter.value || template.contract_type_id === templateTypeFilter.value)
    && (!templateServiceFilter.value || template.service === templateServiceFilter.value)
    && (!templatePositionFilter.value || template.position_id === templatePositionFilter.value)
    && (!templateDateFrom.value || template.created_at.slice(0, 10) >= templateDateFrom.value)
    && (!templateDateTo.value || template.created_at.slice(0, 10) <= templateDateTo.value)
    && (!templateCountryFilter.value || versions.some((version: ContractTemplateVersion) => version.country_id === templateCountryFilter.value))
    && (!templateLanguageFilter.value || versions.some((version: ContractTemplateVersion) => version.language_id === templateLanguageFilter.value))
}))

const markTemplatePristine = () => {
  pristineTemplateState.value = templateState.value
  nextTick(() => {
    const form = formRef.value?.$el as HTMLFormElement | undefined

    form?.dispatchEvent(new Event('app-form-pristine', { bubbles: true }))
  })
}

const loadPlaceholders = async () => {
  if (!contractTypeId.value) {
    rawPlaceholderGroups.value = []

    return
  }
  try { rawPlaceholderGroups.value = await getPlaceholders({ contract_type_id: contractTypeId.value }) }
  catch (error) { console.error('Failed to load placeholders:', error); rawPlaceholderGroups.value = [] }
}

watch(contractTypeId, async () => {
  if (!isEmployment.value)
    positionId.value = null
  if (!isServiceType.value)
    builtInService.value = null
  await loadPlaceholders()
})

const insertPolicy = () => {
  const policy = policies.value.find(item => item.id === selectedPolicyId.value)
  if (!policy)
    return
  const header = policy.header ? `<h2>${policy.header}</h2>` : ''

  contentHtml.value += `${header}${legacyContentToHtml(policy.content)}`
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, { emitUpdate: false })
  policyIds.value.push(policy.id)
  selectedPolicyId.value = null
}

const loadTemplates = async () => {
  try {
    const all = await ContractTemplateApi.getAll()
    templates.value = all.filter(template => template.service !== 'academy')
  }
  catch (error) { toast.error(tr('messages.loadTemplatesError')); console.error(error) }
}

const loadPolicies = async () => {
  // Include inactive clauses only to retain a template's existing history;
  // the picker itself exposes active, type-compatible clauses only.
  try { allPolicies.value = await ContractPolicyApi.getAll(true) }
  catch (error) { console.error(error) }
}

const loadPositions = async () => {
  try { positions.value = await PositionApi.getOptions() }
  catch (error) { console.error(error) }
}

const loadVersions = async (id = templateId.value) => {
  if (!id) {
    versions.value = []
    return
  }
  versionsLoading.value = true
  try { versions.value = await ContractTemplateApi.versions(id) }
  catch (error) { toast.error(tr('versions.loadError')); console.error(error) }
  finally { versionsLoading.value = false }
}

const openVersionDialog = () => {
  versionForm.value = { name: '', country_id: versionCountryFilter.value, test_id: versionTestFilter.value, language_id: versionLanguageFilter.value }
  versionDialog.value = true
}

const createVersion = async () => {
  if (!usesVersioning.value)
    return
  if (!templateId.value || !versionForm.value.name.trim()) {
    toast.warning(tr('validation.versionNameRequired'))
    return
  }
  // Employment templates are name-only: no country/language scope.
  if (!isEmployment.value) {
    const scopeMissing = isTestOffer.value ? !versionForm.value.test_id : !versionForm.value.country_id
    if (scopeMissing || !versionForm.value.language_id) {
      toast.warning(tr('versions.countryLanguageRequired'))
      return
    }
  }
  try {
    versionsLoading.value = true
    await ContractTemplateApi.createVersion(templateId.value, {
      name: versionForm.value.name.trim(),
      country_id: isEmployment.value ? null : versionForm.value.country_id,
      test_id: isEmployment.value ? null : versionForm.value.test_id,
      language_id: isEmployment.value ? null : versionForm.value.language_id,
      content: serializePlaceholders(contentHtml.value),
    })
    versionDialog.value = false
    await loadVersions()
    toast.success(tr('versions.created'))
  }
  catch (error) { toast.error(tr('versions.createError')); console.error(error) }
  finally { versionsLoading.value = false }
}

const editVersion = async (version: ContractTemplateVersion) => {
  if (version.status !== 'draft' || !canEditDraftVersions.value)
    return
  editingVersionId.value = version.id
  versionCountryFilter.value = version.country_id
  versionTestFilter.value = version.test_id
  versionLanguageFilter.value = version.language_id
  contentHtml.value = hydratePlaceholders(legacyContentToHtml(version.content), placeholderGroups.value, knownKeys.value)
  await nextTick()
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, { emitUpdate: false })
}

const archiveVersion = async (version: ContractTemplateVersion) => {
  if (!templateId.value || version.status !== 'draft') return
  try {
    await ContractTemplateApi.archiveVersion(templateId.value, version.id)
    await loadVersions()
    toast.success(tr('versions.archived'))
  }
  catch (error) { toast.error(tr('versions.archiveError')); console.error(error) }
}

const activateVersion = async (version: ContractTemplateVersion) => {
  if (!templateId.value || version.is_current)
    return
  const confirmed = await confirmActionWarning({ key: `activate-template-version-${version.id}`, title: tr('versions.activateTitle'), message: tr('versions.activateMessage', { version: version.version_number }), confirmText: t('common.actions.confirm'), cancelText: t('common.actions.cancel'), icon: 'tabler-circle-check', tone: 'success' })
  if (!confirmed)
    return
  activatingVersionId.value = version.id
  try {
    await ContractTemplateApi.activateVersion(templateId.value, version.id)
    await loadVersions()
    toast.success(tr('versions.activated'))
  }
  catch (error) { toast.error(tr('versions.activateError')); console.error(error) }
  finally { activatingVersionId.value = null }
}

const loadContractTypes = async () => {
  try { contractTypes.value = await ContractTypeApi.getAll({ include_inactive: true }) }
  catch (error) { console.error(error) }
}

onMounted(async () => {
  isLoading.value = true
  await Promise.all([
    loadTemplates(), loadPolicies(), loadPositions(), loadContractTypes(),
    MasterDataApi.list('countries').then(rows => { countries.value = rows }),
    MasterDataApi.list('languages').then(rows => { languages.value = rows }),
    MasterDataApi.list('tests').then(rows => { tests.value = rows }),
  ])
  isLoading.value = false
  if (route.query.positionId) {
    positionId.value = Number(route.query.positionId)

    const template = templates.value.find(item => item.position_id === positionId.value)
    if (template) {
      await loadTemplate(template.id)
      editorDialog.value = true
    }
    else {
      const position = positions.value.find(item => item.id === positionId.value)

      contractName.value = position ? tr('employmentContractName', { position: position.title }) : ''
      contractTypeId.value = contractTypes.value.find(type => type.slug === 'employment')?.id ?? null
    }
  }
  else if (route.params.id) {
    await loadTemplate(Number(route.params.id))
    editorDialog.value = true
  }
  markTemplatePristine()
})

const resetForm = () => {
  contractName.value = ''
  contractTypeId.value = null
  coverText.value = ''
  positionId.value = route.query.positionId ? Number(route.query.positionId) : null
  builtInService.value = null
  savedBuiltInService.value = null
  policyIds.value = []
  templateId.value = null
  contentHtml.value = ''
  rawPlaceholderGroups.value = []
  versions.value = []
  initialVersion.value = { name: '', country_id: null, test_id: null, language_id: null }
  editingVersionId.value = null
  nextTick(() => {
    formRef.value?.resetValidation()
    markTemplatePristine()
  })
}

const loadTemplate = async (id: number) => {
  try {
    isLoading.value = true

    const template = await ContractTemplateApi.getById(id)

    contractName.value = template.name
    contractTypeId.value = template.contract_type_id ?? null
    positionId.value = template.position_id ?? null
    builtInService.value = template.service
    savedBuiltInService.value = template.service
    policyIds.value = template.policies?.map(policy => policy.id) ?? []
    coverText.value = template.cover_text ?? ''
    templateId.value = template.id
    await loadPlaceholders()
    if (usesVersioning.value) {
      await loadVersions(template.id)
      const editable = versions.value.find(version => version.status === 'draft') ?? versions.value.find(version => version.is_current)
      contentHtml.value = hydratePlaceholders(legacyContentToHtml(editable?.content ?? template.content), placeholderGroups.value, knownKeys.value)
      editingVersionId.value = editable?.status === 'draft' ? editable.id : null
    }
    else {
      versions.value = []
      contentHtml.value = hydratePlaceholders(legacyContentToHtml(template.content), placeholderGroups.value, knownKeys.value)
      editingVersionId.value = null
    }
    markTemplatePristine()
  }
  catch (error) { toast.error(tr('messages.loadTemplateDetailsError')); console.error(error) }
  finally { isLoading.value = false }
}

const selectTemplate = async (value: number | null) => {
  if (value === templateId.value)
    return

  if (hasTemplateChanges.value) {
    const confirmed = await confirmActionWarning({
      key: 'discard-form-changes',
      title: t('common.actionWarnings.discardTitle'),
      message: t('common.actionWarnings.discardMessage'),
      confirmText: t('common.actionWarnings.discardConfirm'),
      cancelText: t('common.actionWarnings.keepEditing'),
      icon: 'tabler-file-alert',
      tone: 'primary',
    })

    if (!confirmed)
      return
  }

  if (value)
    await loadTemplate(Number(value))
  else
    resetForm()
}

const saveContract = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid || !contractTypeId.value) {
    if (!contractTypeId.value)
      toast.warning(tr('messages.selectTypeBeforeSaving'))

    return
  }
  if (!isEmployment.value)
    positionId.value = null
  if (!isServiceType.value)
    builtInService.value = null
  const serialized = serializePlaceholders(contentHtml.value)
  const invalidKeys = extractPlaceholderKeys(serialized).filter(key => !knownKeys.value.has(key))
  if (invalidKeys.length) {
    toast.warning(tr('messages.unknownPlaceholders', { placeholders: invalidKeys.join(', ') }))

    return
  }
  const isNewTemplate = !templateId.value
  const initialScopeMissing = usesVersioning.value
    ? (isEmployment.value
        ? false
        : (isTestOffer.value ? !initialVersion.value.test_id : !initialVersion.value.country_id) || !initialVersion.value.language_id)
    : false
  if (isNewTemplate && usesVersioning.value && (!initialVersion.value.name.trim() || initialScopeMissing)) {
    toast.warning(tr('messages.selectInitialVersion'))

    return
  }
  const payload: ContractTemplateCreateData = { name: contractName.value.trim(), contract_type_id: contractTypeId.value, position_id: positionId.value, policy_ids: policyIds.value, cover_text: coverText.value, content: serialized }
  if (builtInService.value)
    payload.service = builtInService.value
  try {
    isLoading.value = true

    const saved = templateId.value ? await ContractTemplateApi.update(templateId.value, payload) : await ContractTemplateApi.create(payload)
    if (isNewTemplate && usesVersioning.value) {
      await ContractTemplateApi.createVersion(saved.id, {
        name: initialVersion.value.name.trim(),
        country_id: isEmployment.value ? null : initialVersion.value.country_id,
        test_id: isEmployment.value ? null : initialVersion.value.test_id,
        language_id: isEmployment.value ? null : initialVersion.value.language_id,
        content: serialized,
      })
    }
    else if (editingVersionId.value) {
      const version = versions.value.find(item => item.id === editingVersionId.value)
      if (version) await ContractTemplateApi.updateVersion(saved.id, version.id, { name: version.name, content: serialized })
    }
    const index = templates.value.findIndex(item => item.id === saved.id)
    if (index < 0)
      templates.value.unshift(saved)
    else templates.value[index] = saved
    toast.success(tr(isNewTemplate ? 'messages.templateCreated' : 'messages.templateUpdated'))
    await loadTemplates()
    if (isNewTemplate)
      templates.value = moveRowIdFirst(templates.value, saved.id)
    resetForm()
    editorDialog.value = false
  }
  catch (error) { toast.error(tr('messages.saveTemplateError')); console.error(error) }
  finally { isLoading.value = false }
}

const confirmDelete = (id: number) => { templateToDelete.value = id; deleteDialog.value = true }

const closeTemplateEditor = () => {
  editorDialog.value = false
  resetForm()
}

const openTemplateEditor = async (id?: number) => {
  if (id)
    await loadTemplate(id)
  else
    resetForm()

  editorDialog.value = true
}

defineExpose({ openEditor: () => openTemplateEditor() })

const deleteTemplate = async () => {
  if (!templateToDelete.value)
    return
  try {
    isLoading.value = true
    await ContractTemplateApi.delete(templateToDelete.value)
    templates.value = templates.value.filter(item => item.id !== templateToDelete.value)
    toast.success(tr('messages.templateDeleted'))
    resetForm()
  }
  catch (error) { toast.warning(tr('messages.templateInUse')); console.error(error) }
  finally { isLoading.value = false; deleteDialog.value = false; templateToDelete.value = null }
}

watch(locale, async () => {
  await nextTick()
  contentHtml.value = hydratePlaceholders(serializePlaceholders(contentHtml.value), placeholderGroups.value, knownKeys.value)
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, { emitUpdate: false })
})
</script>

<template>
  <main class="template-workspace policy-library-layout">
    <DataTablePageKpis
      v-if="!props.embedded"
      :items="templates"
      :title="t('pages.settings.policies.policies.contractTemplates')"
      :description="tr('heroDescription')"
      icon="tabler-file-description"
    />
    <component :is="props.embedded ? 'div' : 'VCard'" :class="['template-library', { 'template-library--embedded': props.embedded }]" :loading="isLoading">
      <VCardItem v-if="!props.embedded" :title="t('pages.settings.policies.policies.contractTemplates')" :subtitle="tr('heroDescription')">
        <template #append><VBtn color="secondary" prepend-icon="tabler-plus" @click="openTemplateEditor()">{{ tr('createNewTemplate') }}</VBtn></template>
      </VCardItem>
      <VCardText class="pb-0">
        <div class="d-flex align-center ga-3 mb-4">
          <VTextField v-model="templateSearch" :label="t('common.actions.search')" prepend-inner-icon="tabler-search" density="compact" hide-details />
          <DataTableFilterMenu :active="Boolean(templateTypeFilter || templateServiceFilter || templatePositionFilter || templateCountryFilter || templateLanguageFilter || templateDateFrom || templateDateTo)" @reset="resetTemplateFilters">
            <VSelect v-model="templateTypeFilter" :items="contractTypes.map(type => ({ title: type.name, value: type.id }))" :label="t('common.fields.type')" clearable />
            <VSelect v-model="templateServiceFilter" :items="builtInServices.map(value => ({ value, title: builtInLabel(value) }))" :label="t('educational.service')" clearable />
            <VSelect v-model="templatePositionFilter" :items="positions.map(position => ({ title: position.title, value: position.id }))" :label="tr('position')" clearable />
            <VSelect v-model="templateCountryFilter" :items="countries.map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" clearable />
            <VSelect v-model="templateLanguageFilter" :items="languages.map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" clearable />
            <ErpDateRange v-model:from="templateDateFrom" v-model:to="templateDateTo" />
          </DataTableFilterMenu>
        </div>
      </VCardText>
      <ResponsiveDataTable :items="filteredTemplates" :headers="[
        { title: t('pages.settings.policies.policies.contractName'), key: 'name' }, { title: t('common.fields.type'), key: 'type_slug' }, { title: tr('position'), key: 'position.title' }, { title: tr('scope'), key: 'scope' }, { title: tr('versions.title'), key: 'versions' }, { title: t('pages.settings.policies.policies.policyLibrary.actions'), key: 'actions', sortable: false, align: 'end' },
      ]" item-value="id">
        <template #item.type_slug="{ item }"><VChip size="small" color="secondary" variant="tonal">{{ templateTypeName(item) }}</VChip></template>
        <template #item.position.title="{ item }">{{ item.position?.title || '—' }}</template>
        <template #item.versions="{ item }">{{ item.versions?.length || 0 }}</template>
        <template #item.scope="{ item }">
          <VChip v-if="item.service" size="small" color="success" variant="tonal">{{ builtInLabel(item.service) }}</VChip>
          <span v-else>—</span>
        </template>
        <template #item.actions="{ item }"><IconBtn :aria-label="t('common.actions.edit')" @click="openTemplateEditor(item.id)"><VIcon icon="tabler-pencil" /></IconBtn><IconBtn v-if="!item.service" :aria-label="t('common.actions.delete')" @click="confirmDelete(item.id)"><VIcon icon="tabler-trash" /></IconBtn></template>
        <template #no-data><div class="pa-8 text-center text-medium-emphasis">{{ tr('noMatchingTemplates') }}</div></template>
      </ResponsiveDataTable>
    </component>

    <VDialog v-model="editorDialog" max-width="1480" scrollable content-class="policy-studio-dialog">
      <VCard class="policy-studio">
        <VCardItem class="policy-studio__header" :title="templateId ? tr('updateTemplate') : tr('createNewTemplate')" :subtitle="tr('templateDetailsSubtitle')"><template #prepend><VAvatar color="secondary" variant="tonal" size="42"><VIcon icon="tabler-file-description" /></VAvatar></template><template #append><VChip color="secondary" size="small" variant="tonal">{{ tr('documentWorkspace') }}</VChip></template></VCardItem>
        <VDivider />
      <VCardText class="policy-studio__body pt-5">
    <VForm
      ref="formRef"
      class="app-form contract-template-form"
      @submit.prevent="saveContract"
    >
      <VCard
        v-if="templateId && usesVersioning"
        class="template-workspace__card"
      >
        <VCardItem>
          <template #prepend><VIcon icon="tabler-versions" color="primary" class="mr-2" /></template>
          <VCardTitle>{{ isEmployment ? tr('versions.titleEmployment') : tr('versions.title') }}</VCardTitle>
          <VCardSubtitle>{{ isEmployment ? tr('versions.subtitleEmployment') : tr('versions.subtitle') }}</VCardSubtitle>
          <template #append>
            <VBtn
              v-if="canCreateVersions"
              color="primary"
              prepend-icon="tabler-plus"
              :disabled="versionsLoading"
              @click="openVersionDialog"
            >
              {{ tr('versions.createVersion') }}
            </VBtn>
          </template>
        </VCardItem>
        <VCardText>
          <VAlert color="info" variant="tonal" density="compact" icon="tabler-info-circle" class="mb-4">
            {{ tr('versions.saveFirstHint') }}
          </VAlert>
          <VRow dense class="mb-2">
            <VCol v-if="!isEmployment && isTestOffer" cols="12" sm="5"><VSelect v-model="versionTestFilter" :items="tests.filter(test => test.is_active).map(test => ({ title: test.name, value: test.id }))" :label="t('educational.masterData.tests')" clearable hide-details /></VCol>
            <VCol v-else-if="!isEmployment" cols="12" sm="5"><VSelect v-model="versionCountryFilter" :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" clearable hide-details /></VCol>
            <VCol v-if="!isEmployment" cols="12" sm="5"><VSelect v-model="versionLanguageFilter" :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" clearable hide-details /></VCol>
            <VCol cols="12" :sm="isEmployment ? 12 : 2" class="d-flex justify-end"><VBtn icon="tabler-refresh" variant="text" :loading="versionsLoading" @click="loadVersions" /></VCol>
          </VRow>
          <VProgressLinear v-if="versionsLoading" indeterminate class="mb-3" />
          <VTable density="comfortable">
            <thead><tr><th>{{ tr('versions.version') }}</th><th v-if="!isEmployment">{{ isTestOffer ? t('educational.masterData.tests') : t('common.fields.country') }}</th><th v-if="!isEmployment">{{ t('common.fields.language') }}</th><th>{{ t('common.fields.status') }}</th><th class="text-end">{{ t('pages.settings.policies.policies.policyLibrary.actions') }}</th></tr></thead>
            <tbody>
              <tr v-for="version in visibleVersions" :key="version.id">
                <td class="font-weight-medium">{{ version.name }} <span class="text-medium-emphasis">· v{{ version.version_number }}</span></td>
                <td v-if="!isEmployment">{{ isTestOffer ? versionTestName(version) : versionCountryName(version) }}</td>
                <td v-if="!isEmployment">{{ versionLanguageName(version) }}</td>
                <td><VChip size="small" :color="version.is_current ? 'success' : version.status === 'draft' ? 'warning' : 'secondary'" variant="tonal">{{ version.is_current ? tr('versions.active') : translateBackendValue(version.status) }}</VChip></td>
                <td class="text-end text-no-wrap"><VBtn size="small" variant="text" @click="previewVersion = version">{{ tr('versions.preview') }}</VBtn><VBtn v-if="canEditDraftVersions && version.status === 'draft'" size="small" variant="text" @click="editVersion(version)">{{ tr('versions.editDraft') }}</VBtn><VBtn v-if="canActivateVersions && version.status === 'draft'" size="small" color="primary" variant="text" :loading="activatingVersionId === version.id" @click="activateVersion(version)">{{ tr('versions.activate') }}</VBtn><VBtn v-if="canArchiveVersions && version.status === 'draft'" size="small" color="error" variant="text" @click="archiveVersion(version)">{{ tr('versions.archive') }}</VBtn></td>
              </tr>
              <tr v-if="!versionsLoading && !visibleVersions.length"><td :colspan="isEmployment ? 3 : 5" class="text-center text-medium-emphasis py-6">{{ tr('versions.empty') }}</td></tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>

      <VCard class="template-workspace__card">
        <VCardItem class="pb-2">
          <template #prepend>
            <VIcon
              icon="tabler-settings"
              color="primary"
              class="mr-2"
            />
          </template><VCardTitle>{{ tr('templateDetails') }}</VCardTitle><VCardSubtitle>{{ tr('templateDetailsSubtitle') }}</VCardSubtitle><template #append>
            <VChip
              v-if="typeLabel"
              color="primary"
              variant="tonal"
              size="small"
            >
              {{ typeLabel }}
            </VChip>
          </template>
        </VCardItem>
        <VCardText class="pt-4">
          <VRow>
            <VCol
              cols="12"
              :md="isEmployment ? 4 : 6"
            >
              <VTextField
                v-model="contractName"
                :label="t('pages.settings.policies.policies.contractName')"
                :placeholder="t('pages.settings.policies.policies.eGStandardNDA2026')"
                prepend-inner-icon="tabler-file-text"
                variant="outlined"
                :rules="[v => !!v || tr('validation.nameRequired')]"
              />
            </VCol>
            <VCol
              v-if="isEmployment"
              cols="12"
              md="4"
            >
              <VSelect
                v-model="positionId"
                :items="positions"
                item-title="title"
                item-value="id"
                :label="tr('position')"
                prepend-inner-icon="tabler-briefcase"
                variant="outlined"
                :rules="[v => !!v || tr('validation.positionRequired')]"
              />
            </VCol>
            <VCol
              cols="12"
              :md="isEmployment ? 4 : 6"
            >
              <ContractTypeSelector
                v-model="contractTypeId"
                :disabled="serviceBindingLocked || isLoading"
                required
                @types-changed="types => contractTypes = types"
              />
            </VCol>
            <VCol v-if="isServiceType" cols="12" md="6">
              <VSelect
                v-model="builtInService"
                :items="serviceItems"
                :label="t('common.fields.service')"
                :disabled="serviceBindingLocked || isLoading"
                :rules="[value => !!value || t('common.validation.required')]"
                prepend-inner-icon="tabler-briefcase"
              />
            </VCol>
            <template v-if="!templateId && usesVersioning">
              <VCol cols="12">
                <VTextField v-model="initialVersion.name" :label="tr('versions.versionName')" :rules="[value => !!String(value || '').trim() || tr('validation.versionNameRequired')]" />
              </VCol>
              <VCol
                v-if="!isEmployment && isTestOffer"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="initialVersion.test_id"
                  :items="tests.filter(test => test.is_active).map(test => ({ title: test.name, value: test.id }))"
                  :label="t('educational.masterData.tests')"
                  prepend-inner-icon="tabler-certificate"
                  variant="outlined"
                  :rules="[value => !!value || t('common.validation.required')]"
                />
              </VCol>
              <VCol
                v-else-if="!isEmployment"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="initialVersion.country_id"
                  :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))"
                  :label="tr('initialVersionCountry')"
                  prepend-inner-icon="tabler-world"
                  variant="outlined"
                  :rules="[value => !!value || tr('validation.initialVersionCountryRequired')]"
                />
              </VCol>
              <VCol
                v-if="!isEmployment"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="initialVersion.language_id"
                  :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))"
                  :label="tr('initialVersionLanguage')"
                  prepend-inner-icon="tabler-language"
                  variant="outlined"
                  :rules="[value => !!value || tr('validation.initialVersionLanguageRequired')]"
                />
              </VCol>
              <VCol
                cols="12"
                class="pt-0"
              >
                <VAlert
                  color="secondary"
                  variant="tonal"
                  density="compact"
                  icon="tabler-versions"
                >
                  {{ tr('initialVersionHint') }}
                </VAlert>
              </VCol>
            </template>
            <VCol
              v-if="typeLabel"
              cols="12"
              class="pt-0"
            >
              <VAlert
                color="primary"
                variant="tonal"
                density="compact"
                icon="tabler-bulb"
              >
                {{ tr('templateContext') }} <strong>{{ typeLabel }}</strong>. <span v-if="isEmployment">{{ tr('employmentPlaceholderHint') }}</span><span v-else>{{ tr('generalPlaceholderHint') }}</span>
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard class="template-workspace__card">
        <VCardItem class="pb-2">
          <template #prepend>
            <VIcon
              icon="tabler-writing"
              color="primary"
              class="mr-2"
            />
          </template><VCardTitle>{{ tr('documentContent') }}</VCardTitle><VCardSubtitle>{{ tr('documentContentSubtitle') }}</VCardSubtitle>
        </VCardItem>
        <VCardText class="pt-4">
          <VAlert v-if="templateId && usesVersioning && !editingVersion" color="info" variant="tonal" density="compact" class="mb-4">
            {{ tr('versions.selectDraftToEdit') }}
          </VAlert>
          <VTextField
            v-if="editingVersion"
            v-model="editingVersion.name"
            :label="tr('versions.versionName')"
            class="mb-4"
            :rules="[value => !!String(value || '').trim() || tr('validation.versionNameRequired')]"
          />
          <VTextarea
            v-model="coverText"
            :label="t('pages.settings.policies.policies.coverIntroductionText')"
            :placeholder="t('pages.settings.policies.policies.briefOverviewOrIntroductoryStatementOfTheContract')"
            prepend-inner-icon="tabler-align-left"
            variant="outlined"
            rows="2"
            auto-grow
            class="mb-5"
          />
          <div class="template-workspace__editor-heading">
            <div>
              <div class="text-subtitle-2 font-weight-bold">
                {{ t('pages.settings.policies.policies.contractPOLICIESTERMS') }}
              </div><div class="text-caption text-medium-emphasis">
                {{ tr('placeholderHint') }}
              </div>
            </div><VAutocomplete
              v-if="policies.length"
              v-model="selectedPolicyId"
              :items="policies.map(p => ({ title: `${policyTypeName(p)} · ${p.label}`, value: p.id }))"
              :label="tr('insertSavedPolicy')"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              prepend-inner-icon="tabler-bookmark-plus"
              :disabled="!contractTypeId"
              @update:model-value="value => { if (value) insertPolicy() }"
            />
          </div>
          <div
            v-if="!policies.length"
            class="template-workspace__empty-policy-note text-caption text-medium-emphasis"
          >
            {{ tr('noReusablePolicies') }}
          </div>
          <ContractContentEditor
            ref="editorRef"
            v-model="contentHtml"
            :placeholder-groups="placeholderGroups"
            :disabled="usesVersioning && !!templateId && !editingVersion"
            variant="policy"
            min-height="520px"
          />
        </VCardText>
      </VCard>



      <div class="template-workspace__actions policy-studio__actions">
        <VBtn
          variant="tonal"
          :disabled="isLoading"
          @click="closeTemplateEditor"
        >
          {{ t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          v-if="templateId"
          color="error"
          variant="text"
          prepend-icon="tabler-trash"
          :disabled="isLoading"
          @click="confirmDelete(templateId)"
        >
          {{ t('common.actions.delete') }}
        </VBtn><VBtn
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-refresh"
          :disabled="isLoading"
          @click="resetForm"
        >
          {{ t('pages.settings.policies.policies.reset') }}
        </VBtn><VBtn
          type="submit"
          color="secondary"
          prepend-icon="tabler-device-floppy"
          :loading="isLoading"
        >
          {{ templateId ? tr('updateTemplate') : tr('saveTemplate') }}
        </VBtn>
      </div>
    </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </main>

  <VDialog v-model="versionDialog" max-width="620">
    <VCard>
      <VCardItem :title="tr('versions.dialogTitle')" :subtitle="tr('versions.dialogSubtitle')" />
      <VCardText>
        <VAlert v-if="hasTemplateChanges" color="warning" variant="tonal" density="compact" icon="tabler-alert-triangle" class="mb-4">
          {{ tr('versions.unsavedHint') }}
        </VAlert>
        <VAlert v-if="isEmployment" color="secondary" variant="tonal" density="compact" icon="tabler-versions" class="mb-4">
          {{ tr('versions.nameOnlyHint') }}
        </VAlert>
        <VRow>
          <VCol cols="12"><VTextField v-model="versionForm.name" :label="tr('versions.versionName')" :rules="[value => !!String(value || '').trim() || tr('validation.versionNameRequired')]" /></VCol>
          <VCol v-if="!isEmployment && isTestOffer" cols="12" sm="6"><VSelect v-model="versionForm.test_id" :items="tests.filter(test => test.is_active).map(test => ({ title: test.name, value: test.id }))" :label="t('educational.masterData.tests')" :rules="[value => !!value || t('common.validation.required')]" /></VCol>
          <VCol v-else-if="!isEmployment" cols="12" sm="6"><VSelect v-model="versionForm.country_id" :items="countries.filter(country => country.is_active).map(country => ({ title: country.name, value: country.id }))" :label="t('common.fields.country')" :rules="[value => !!value || tr('versions.countryRequired')]" /></VCol>
          <VCol v-if="!isEmployment" cols="12" sm="6"><VSelect v-model="versionForm.language_id" :items="languages.filter(language => language.is_active).map(language => ({ title: language.name, value: language.id }))" :label="t('common.fields.language')" :rules="[value => !!value || tr('versions.languageRequired')]" /></VCol>
        </VRow>
      </VCardText>
      <VCardActions><VSpacer/><VBtn variant="text" :disabled="versionsLoading" @click="versionDialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :loading="versionsLoading" :disabled="hasTemplateChanges" @click="createVersion">{{ tr('versions.createVersion') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>

  <VDialog :model-value="!!previewVersion" max-width="900" @update:model-value="value => { if (!value) previewVersion = null }">
    <VCard>
      <VCardItem :title="previewVersion ? `${previewVersion.name} · v${previewVersion.version_number}` : ''" :subtitle="previewVersion && !isEmployment ? `${versionCountryName(previewVersion)} · ${versionLanguageName(previewVersion)}` : ''" />
      <VDivider />
      <VCardText class="contract-version-preview" v-html="previewVersion?.content" />
      <VCardActions><VSpacer/><VBtn @click="previewVersion = null">{{ $t('common.actions.close') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="deleteDialog"
    max-width="400"
  >
    <VCard class="text-center pa-4">
      <VCardItem>
        <VAvatar
          color="error"
          variant="tonal"
          size="56"
          class="mb-2"
        >
          <VIcon
            icon="tabler-alert-triangle"
            size="32"
          />
        </VAvatar><VCardTitle class="text-h5">
          {{ t('pages.settings.policies.policies.deleteTemplate2') }}
        </VCardTitle>
      </VCardItem><VCardText class="text-body-2 text-medium-emphasis">
        {{ t('common.confirmations.areYouSureYouWantToDeleteThisContractTemplateThisActionCannotBeUndone') }}
      </VCardText><VCardActions class="justify-center gap-3 mt-2">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="deleteDialog = false"
        >
          {{ t('common.actions.cancel') }}
        </VBtn><VBtn
          color="error"
          variant="elevated"
          :loading="isLoading"
          @click="deleteTemplate"
        >
          {{ t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.template-workspace {

  margin-inline: auto;

  &__hero, &__card { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 0.9rem; }
  &__hero { margin-block-end: 1.25rem; background: linear-gradient(118deg, rgba(var(--v-theme-secondary), 0.11), transparent 58%); }
  &__hero-grid, &__template-picker, &__editor-heading, &__actions { display: flex; gap: 1rem; align-items: center; }
  &__hero-grid, &__editor-heading { justify-content: space-between; }
  &__template-picker { flex: 0 1 430px; inline-size: 100%; }
  &__template-picker :deep(.v-input) { flex: 1 1 auto; }
  &__card { margin-block-end: 1.25rem; }
  &__card :deep(.v-card-item) { min-block-size: 92px; padding: 1.25rem 1.5rem; background: linear-gradient(118deg, rgba(var(--v-theme-secondary), 0.11), transparent 58%); }
  &__card :deep(.v-card-item__prepend) { margin-inline-end: 1rem; }
  &__card :deep(.v-card-subtitle) { margin-block-start: 0.2rem; white-space: normal; }
  &__editor-heading { margin-block-end: 0.5rem; }
  &__editor-heading :deep(.v-input) { flex: 0 1 360px; inline-size: 360px; max-inline-size: 100%; }
  &__empty-policy-note { margin-block: 0 0.75rem; }
  &__actions { position: sticky; z-index: 2; inset-block-end: 1rem; justify-content: flex-end; flex-wrap: wrap; min-block-size: 68px; padding: 0.75rem 1.5rem; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 0.75rem; background: rgba(var(--v-theme-surface), 0.96); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); backdrop-filter: blur(12px); }

  @media (max-width: 700px) {
    &__hero-grid, &__editor-heading { align-items: stretch; flex-direction: column; }
    &__template-picker { flex-basis: auto; }
    &__editor-heading :deep(.v-input) { flex-basis: auto; inline-size: 100%; }
    &__actions { gap: 0.5rem; }
    &__actions :deep(.v-btn) { flex: 1 1 auto; }
  }
}

.contract-version-preview {
  max-block-size: 65vh;
  overflow: auto;
  line-height: 1.7;
}

.template-library {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.9rem;
}
.template-library--embedded { border: 0; border-radius: 0; }

.policy-studio-dialog { margin: 24px; max-block-size: calc(100% - 48px) !important; }
.policy-studio { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 0.9rem; }
.policy-studio__header { min-block-size: 92px; padding: 1.25rem 1.5rem; background: linear-gradient(118deg, rgba(var(--v-theme-secondary), 0.11), transparent 58%); }
.policy-studio__header :deep(.v-card-item__prepend) { margin-inline-end: 1rem; }
.policy-studio__header :deep(.v-card-subtitle) { margin-block-start: 0.2rem; white-space: normal; }
.policy-studio__body { padding-inline: 1.5rem; }

/* The template fields remain specific to contracts, but use the same single-studio
 * surface as the Policies editor rather than nested workspace cards. */
.policy-studio .template-workspace__card {
  overflow: visible;
  margin-block: 0;
  border: 0;
  border-radius: 0;
  box-shadow: none;
}

.policy-studio .template-workspace__card + .template-workspace__card {
  margin-block-start: 1.5rem;
  padding-block-start: 1.5rem;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.policy-studio .template-workspace__card :deep(.v-card-item) {
  min-block-size: 0;
  padding: 0 0 0.75rem;
  background: transparent;
}

.policy-studio .template-workspace__card :deep(.v-card-text) { padding-inline: 0; }
.policy-studio .template-workspace__card :deep(.v-card-item__prepend) { margin-inline-end: 0.75rem; }

.policy-studio .template-workspace__actions {
  position: static;
  min-block-size: 68px;
  margin: 1.5rem -1.5rem -1.25rem;
  padding: 0.75rem 1.5rem;
  border: 0;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  backdrop-filter: none;
}
</style>
