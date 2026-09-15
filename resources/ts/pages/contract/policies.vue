<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { type PlaceholderGroup, getPlaceholders } from '@/api/contractPlaceholders'
import ContractPolicyApi, { type ContractPolicy } from '@/api/contractPolicy'
import ContractTemplateApi from '@/api/contractTemplate'
import ContractTypeApi, { type ContractType, SYSTEM_TYPE_SLUGS } from '@/api/contractType'
import PositionApi, { type Position } from '@/api/position'
import ContractContentEditor from '@/components/contract/ContractContentEditor.vue'
import ContractTypeSelector from '@/components/contract/ContractTypeSelector.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { extractPlaceholderKeys, hydratePlaceholders, legacyContentToHtml, serializePlaceholders } from '@/utils/contractPlaceholders'

const route = useRoute()
const toast = useToast()
const { locale, t } = useI18n()
const tr = (key: string, values: Record<string, unknown> = {}) => t(`pages.settings.policies.policies.contractTemplate.${key}`, values)

const formRef = ref()
const contractName = ref('')
const contractTypeId = ref<number | null>(null)
const coverText = ref('')
const positionId = ref<number | null>(null)
const policyIds = ref<number[]>([])
const contentHtml = ref('')
const selectedPolicyId = ref<number | null>(null)
const contractTypes = ref<ContractType[]>([])
const allPolicies = ref<ContractPolicy[]>([])
const positions = ref<Position[]>([])
const templates = ref<any[]>([])
const templateId = ref<number | null>(null)
const rawPlaceholderGroups = ref<PlaceholderGroup[]>([])
const isLoading = ref(false)
const deleteDialog = ref(false)
const templateToDelete = ref<number | null>(null)
const editorRef = ref<InstanceType<typeof ContractContentEditor> | null>(null)
const pristineTemplateState = ref('')

const policies = computed(() => allPolicies.value.filter(policy =>
  policy.is_active && policy.type === selectedType.value?.slug && !policyIds.value.includes(policy.id)))
const selectedType = computed(() => contractTypes.value.find(type => type.id === contractTypeId.value) ?? null)
const isEmployment = computed(() => selectedType.value?.slug === 'employment')
const isSystemSlug = (slug: string) => SYSTEM_TYPE_SLUGS.includes(slug as typeof SYSTEM_TYPE_SLUGS[number])
const contractTypeName = (type: ContractType | null) => !type ? '' : isSystemSlug(type.slug) ? tr(`types.${type.slug}`) : type.name
const templateTypeName = (template: any) => template.type_slug && isSystemSlug(template.type_slug) ? tr(`types.${template.type_slug}`) : template.type || tr('custom')

const typeLabel = computed(() => {
  const name = contractTypeName(selectedType.value)

  return name && selectedType.value && isSystemSlug(selectedType.value.slug) ? `${name} — ${tr('systemType')}` : name
})

const placeholderGroups = computed<PlaceholderGroup[]>(() => rawPlaceholderGroups.value.map(group => ({
  ...group,
  label: tr(`placeholders.${group.key}.label`),
  fields: group.fields.map(field => ({ ...field, label: tr(`placeholders.${group.key}.fields.${field.key.slice(group.key.length + 1)}`) })),
})))

const knownKeys = computed(() => new Set(placeholderGroups.value.flatMap(group => group.fields.map(field => field.key))))

const templateState = computed(() => JSON.stringify({
  name: contractName.value,
  type: contractTypeId.value,
  position: positionId.value,
  policies: policyIds.value,
  cover: coverText.value,
  content: serializePlaceholders(contentHtml.value),
}))

const hasTemplateChanges = computed(() => templateState.value !== pristineTemplateState.value)

const markTemplatePristine = () => {
  pristineTemplateState.value = templateState.value
  nextTick(() => {
    const form = formRef.value?.$el as HTMLFormElement | undefined

    form?.dispatchEvent(new Event('project-form-pristine', { bubbles: true }))
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
  try { templates.value = await ContractTemplateApi.getAll() }
  catch (error) { toast.error(tr('messages.loadTemplatesError')); console.error(error) }
}

const loadPolicies = async () => {
  // Include inactive clauses only to retain a template's existing history;
  // the picker itself exposes active, type-compatible clauses only.
  try { allPolicies.value = await ContractPolicyApi.getAll(true) }
  catch (error) { console.error(error) }
}

const loadPositions = async () => {
  try { positions.value = (await PositionApi.getAll()).data.data }
  catch (error) { console.error(error) }
}

const loadContractTypes = async () => {
  try { contractTypes.value = await ContractTypeApi.getAll({ include_inactive: true }) }
  catch (error) { console.error(error) }
}

onMounted(async () => {
  isLoading.value = true
  await Promise.all([loadTemplates(), loadPolicies(), loadPositions(), loadContractTypes()])
  isLoading.value = false
  if (route.query.positionId) {
    positionId.value = Number(route.query.positionId)

    const template = templates.value.find(item => item.position_id === positionId.value)
    if (template) { await loadTemplate(template.id) }
    else {
      const position = positions.value.find(item => item.id === positionId.value)

      contractName.value = position ? tr('employmentContractName', { position: position.title }) : ''
      contractTypeId.value = contractTypes.value.find(type => type.slug === 'employment')?.id ?? null
    }
  }
  else if (route.params.id) { await loadTemplate(Number(route.params.id)) }
  markTemplatePristine()
})

const resetForm = () => {
  contractName.value = ''
  contractTypeId.value = null
  coverText.value = ''
  positionId.value = route.query.positionId ? Number(route.query.positionId) : null
  policyIds.value = []
  templateId.value = null
  contentHtml.value = ''
  rawPlaceholderGroups.value = []
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
    policyIds.value = template.policies?.map(policy => policy.id) ?? []
    coverText.value = template.cover_text ?? ''
    templateId.value = template.id
    await loadPlaceholders()
    contentHtml.value = hydratePlaceholders(legacyContentToHtml(template.content), placeholderGroups.value, knownKeys.value)
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
  const serialized = serializePlaceholders(contentHtml.value)
  const invalidKeys = extractPlaceholderKeys(serialized).filter(key => !knownKeys.value.has(key))
  if (invalidKeys.length) {
    toast.warning(tr('messages.unknownPlaceholders', { placeholders: invalidKeys.join(', ') }))

    return
  }
  const payload = { name: contractName.value.trim(), contract_type_id: contractTypeId.value, position_id: positionId.value, policy_ids: policyIds.value, cover_text: coverText.value, content: serialized }
  try {
    isLoading.value = true

    const saved = templateId.value ? await ContractTemplateApi.update(templateId.value, payload) : await ContractTemplateApi.create(payload)
    const index = templates.value.findIndex(item => item.id === saved.id)
    if (index < 0)
      templates.value.unshift(saved)
    else templates.value[index] = saved
    toast.success(tr(templateId.value ? 'messages.templateUpdated' : 'messages.templateCreated'))
    resetForm()
  }
  catch (error) { toast.error(tr('messages.saveTemplateError')); console.error(error) }
  finally { isLoading.value = false }
}

const confirmDelete = (id: number) => { templateToDelete.value = id; deleteDialog.value = true }

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
  <main class="template-workspace">
    <VCard class="template-workspace__hero">
      <VCardText class="pa-5 pa-md-6">
        <div class="template-workspace__hero-grid">
          <div class="d-flex align-start gap-4">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="52"
            >
              <VIcon
                icon="tabler-file-description"
                size="28"
              />
            </VAvatar>
            <div>
              <div class="d-flex flex-wrap align-center gap-2 mb-1">
                <h1 class="text-h5 font-weight-bold">
                  {{ t('pages.settings.policies.policies.contractTemplates') }}
                </h1><VChip
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ tr('documentWorkspace') }}
                </VChip>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ tr('heroDescription') }}
              </p>
            </div>
          </div>
          <div class="template-workspace__template-picker">
            <VSelect
              :model-value="templateId"
              :items="templates.map(item => ({ title: item.name, value: item.id, props: { subtitle: templateTypeName(item) } }))"
              :label="tr('openSavedTemplate')"
              :placeholder="tr('startNewTemplate')"
              prepend-inner-icon="tabler-folder-open"
              density="comfortable"
              variant="outlined"
              hide-details
              clearable
              :loading="isLoading && !templates.length"
              @update:model-value="selectTemplate"
            />
          </div>
        </div>
      </VCardText>
    </VCard>

    <VForm
      ref="formRef"
      class="app-form contract-template-form"
      @submit.prevent="saveContract"
    >
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
              md="7"
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
              md="5"
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
            <VCol cols="12">
              <ContractTypeSelector
                v-model="contractTypeId"
                required
                @types-changed="types => contractTypes = types"
              />
            </VCol>
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
              :items="policies.map(p => ({ title: `${p.type} · ${p.label}`, value: p.id }))"
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
            min-height="520px"
          />
        </VCardText>
      </VCard>

      <div class="template-workspace__actions">
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
          color="primary"
          prepend-icon="tabler-device-floppy"
          :loading="isLoading"
        >
          {{ templateId ? tr('updateTemplate') : tr('saveTemplate') }}
        </VBtn>
      </div>
    </VForm>
  </main>

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
  max-inline-size: 1180px;
  margin-inline: auto;

  &__hero, &__card { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
  &__hero { overflow: hidden; margin-block-end: 1.25rem; background: linear-gradient(118deg, rgba(var(--v-theme-primary), 0.11), rgba(var(--v-theme-surface), 1) 48%); }
  &__hero-grid, &__template-picker, &__editor-heading, &__actions { display: flex; gap: 1rem; align-items: center; }
  &__hero-grid, &__editor-heading { justify-content: space-between; }
  &__template-picker { flex: 0 1 430px; inline-size: 100%; }
  &__template-picker :deep(.v-input) { flex: 1 1 auto; }
  &__card { margin-block-end: 1.25rem; }
  &__editor-heading { margin-block-end: 0.5rem; }
  &__editor-heading :deep(.v-input) { flex: 0 1 360px; inline-size: 360px; max-inline-size: 100%; }
  &__empty-policy-note { margin-block: 0 0.75rem; }
  &__actions { position: sticky; z-index: 2; inset-block-end: 1rem; justify-content: flex-end; flex-wrap: wrap; padding: 0.75rem; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 0.75rem; background: rgba(var(--v-theme-surface), 0.96); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); backdrop-filter: blur(12px); }

  @media (max-width: 700px) {
    &__hero-grid, &__editor-heading { align-items: stretch; flex-direction: column; }
    &__template-picker { flex-basis: auto; }
    &__editor-heading :deep(.v-input) { flex-basis: auto; inline-size: 100%; }
    &__actions { gap: 0.5rem; }
    &__actions :deep(.v-btn) { flex: 1 1 auto; }
  }
}
</style>
