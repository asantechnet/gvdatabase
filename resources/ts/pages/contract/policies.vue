<script setup lang="ts">
import { getPlaceholders, type PlaceholderGroup } from '@/api/contractPlaceholders'
import ContractTemplateApi from '@/api/contractTemplate'
import ContractPolicyApi, { type ContractPolicy } from '@/api/contractPolicy'
import ContractTypeApi, { SYSTEM_TYPE_SLUGS, type ContractType } from '@/api/contractType'
import PositionApi, { type Position } from '@/api/position'
import ContractContentEditor from '@/components/contract/ContractContentEditor.vue'
import ContractTypeSelector from '@/components/contract/ContractTypeSelector.vue'
import { extractPlaceholderKeys, hydratePlaceholders, legacyContentToHtml, serializePlaceholders } from '@/utils/contractPlaceholders'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

const route = useRoute()
const toast = useToast()
const { locale, t } = useI18n()
const tr = (key: string, values: Record<string, unknown> = {}) =>
  t(`pages.settings.policies.policies.contractTemplate.${key}`, values)

// ---------------------------------------------------------------------
// State
// ---------------------------------------------------------------------
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
const policies = computed(() =>
  allPolicies.value.filter(policy => !policyIds.value.includes(policy.id)),
)
const positions = ref<Position[]>([])
const templateId = ref<number | null>(null)
const templates = ref<any[]>([])
const isLoading = ref(false)
const deleteDialog = ref(false)
const templateToDelete = ref<number | null>(null)
const rawPlaceholderGroups = ref<PlaceholderGroup[]>([])

const placeholderGroups = computed<PlaceholderGroup[]>(() => rawPlaceholderGroups.value.map(group => ({
  ...group,
  label: tr(`placeholders.${group.key}.label`),
  fields: group.fields.map(field => ({
    ...field,
    label: tr(`placeholders.${group.key}.fields.${field.key.slice(group.key.length + 1)}`),
  })),
})))

const typeSelectorRef = ref<InstanceType<typeof ContractTypeSelector> | null>(null)
const editorRef = ref<InstanceType<typeof ContractContentEditor> | null>(null)

// ---------------------------------------------------------------------
// Context: the contract type drives which selectors and placeholders
// appear. No scattered `if employment ... else if customer` chains.
// ---------------------------------------------------------------------
const selectedType = computed(() => contractTypes.value.find(type => type.id === contractTypeId.value) ?? null)

const isSystemSlug = (slug: string) => SYSTEM_TYPE_SLUGS.includes(slug as typeof SYSTEM_TYPE_SLUGS[number])

const contractTypeName = (type: ContractType | null) => {
  if (!type) return ''
  return isSystemSlug(type.slug) ? tr(`types.${type.slug}`) : type.name
}

const templateTypeName = (template: any) =>
  template.type_slug && isSystemSlug(template.type_slug)
    ? tr(`types.${template.type_slug}`)
    : template.type || tr('custom')

const isEmployment = computed(() => {
  const slug = selectedType.value?.slug
  return slug ? SYSTEM_TYPE_SLUGS.includes(slug as typeof SYSTEM_TYPE_SLUGS[number]) && slug === 'employment' : false
})

const typeLabel = computed(() => {
  const name = contractTypeName(selectedType.value)
  return name && selectedType.value && isSystemSlug(selectedType.value.slug)
    ? `${name} — ${tr('systemType')}`
    : name
})

watch(contractTypeId, async () => {
  // Employment templates require a position; other types must never keep
  // employment-specific data attached.
  if (!isEmployment.value) positionId.value = null

  await loadPlaceholders()
})

const loadPlaceholders = async () => {
  if (!contractTypeId.value) {
    rawPlaceholderGroups.value = []
    return
  }
  try {
    rawPlaceholderGroups.value = await getPlaceholders({ contract_type_id: contractTypeId.value })
  } catch (error) {
    console.error('Failed to load placeholders:', error)
    rawPlaceholderGroups.value = []
  }
}

const insertPolicy = () => {
  const policy = policies.value.find(item => item.id === selectedPolicyId.value)
  if (!policy) return
  const header = policy.header ? `<h2>${policy.header}</h2>` : ''
  const html = `${header}<p>${policy.content}</p>`
  contentHtml.value += html
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, false)
  if (!policyIds.value.includes(policy.id)) policyIds.value.push(policy.id)
  selectedPolicyId.value = null
}

const knownKeys = computed(() => new Set(placeholderGroups.value.flatMap(group => group.fields.map(field => field.key))))

const onTypesChanged = (types: ContractType[]) => {
  contractTypes.value = types
}

// ---------------------------------------------------------------------
// Data loading
// ---------------------------------------------------------------------
onMounted(async () => {
  await Promise.all([loadTemplates(), loadPolicies(), loadPositions(), loadContractTypes()])

  if (route.query.positionId) {
    positionId.value = Number(route.query.positionId)
    const position = positions.value.find(item => item.id === positionId.value)
    const template = templates.value.find(item => item.position_id === positionId.value)
    if (template) loadTemplate(template.id)
    else if (position) {
      const employmentType = contractTypes.value.find(type => type.slug === 'employment')
      contractName.value = tr('employmentContractName', { position: position.title })
      contractTypeId.value = employmentType?.id ?? null
    }
  } else if (route.params.id) {
    await loadTemplate(Number(route.params.id))
  }
})

const showToast = (message: string, color: 'success' | 'error' | 'warning' = 'success') => {
  toast[color](message)
}

const loadContractTypes = async () => {
  try {
    contractTypes.value = await ContractTypeApi.getAll({ include_inactive: true })
  } catch (error) {
    console.error('Load contract types failed:', error)
  }
}

const loadTemplates = async () => {
  try {
    isLoading.value = true
    templates.value = await ContractTemplateApi.getAll()
  } catch (error) {
    showToast(tr('messages.loadTemplatesError'), 'error')
    console.error('Load templates failed:', error)
  } finally {
    isLoading.value = false
  }
}

const loadPolicies = async () => {
  try {
    allPolicies.value = await ContractPolicyApi.getAll()
  } catch (error) {
    console.error('Load policies failed:', error)
  }
}

const loadPositions = async () => {
  try {
    positions.value = (await PositionApi.getAll()).data.data
  } catch (error) {
    console.error('Load positions failed:', error)
  }
}

// ---------------------------------------------------------------------
// Save / edit / delete
// ---------------------------------------------------------------------
const saveContract = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  if (!contractTypeId.value) {
    toast.warning(tr('messages.selectTypeBeforeSaving'))
    return
  }

  if (!isEmployment.value) positionId.value = null

  // Editor chips become stable machine keys; unknown/out-of-context tokens
  // are rejected by the backend before anything is persisted.
  const serialized = serializePlaceholders(contentHtml.value)
  const usedKeys = extractPlaceholderKeys(serialized).filter(key => !knownKeys.value.has(key))
  if (usedKeys.length > 0) {
    toast.warning(tr('messages.unknownPlaceholders', { placeholders: usedKeys.join(', ') }))
    return
  }

  const payload = {
    name: contractName.value.trim(),
    contract_type_id: contractTypeId.value!,
    position_id: positionId.value,
    policy_ids: policyIds.value,
    cover_text: coverText.value,
    content: serialized,
  }

  try {
    isLoading.value = true
    let savedTemplate
    if (templateId.value) {
      savedTemplate = await ContractTemplateApi.update(templateId.value, payload)
      showToast(tr('messages.templateUpdated'), 'success')
    } else {
      savedTemplate = await ContractTemplateApi.create(payload)
      showToast(tr('messages.templateCreated'), 'success')
    }

    const index = templates.value.findIndex(template => template.id === savedTemplate.id)
    if (index === -1)
      templates.value.unshift(savedTemplate)
    else
      templates.value[index] = savedTemplate

    resetForm()
  } catch (error) {
    showToast(tr('messages.saveTemplateError'), 'error')
    console.error('Save failed:', error)
  } finally {
    isLoading.value = false
  }
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

    // Legacy Quill Delta content and plain HTML both normalize to HTML;
    // {{ key }} tokens become editable chips again.
    contentHtml.value = hydratePlaceholders(legacyContentToHtml(template.content), placeholderGroups.value, knownKeys.value)
  } catch (error) {
    showToast(tr('messages.loadTemplateDetailsError'), 'error')
    console.error('Load failed:', error)
  } finally {
    isLoading.value = false
  }
}

const confirmDelete = (id: number) => {
  templateToDelete.value = id
  deleteDialog.value = true
}

const deleteTemplate = async () => {
  if (!templateToDelete.value) return

  try {
    isLoading.value = true
    await ContractTemplateApi.delete(templateToDelete.value)
    showToast(tr('messages.templateDeleted'), 'success')
    templates.value = templates.value.filter(template => template.id !== templateToDelete.value)
    if (templateId.value === templateToDelete.value) resetForm()
  } catch (error) {
    showToast(tr('messages.templateInUse'), 'warning')
    console.error('Delete failed:', error)
  } finally {
    isLoading.value = false
    deleteDialog.value = false
    templateToDelete.value = null
  }
}

const resetForm = () => {
  contractName.value = ''
  contractTypeId.value = null
  coverText.value = ''
  positionId.value = route.query.positionId ? Number(route.query.positionId) : null
  policyIds.value = []
  templateId.value = null
  contentHtml.value = ''
  rawPlaceholderGroups.value = []

  nextTick(() => formRef.value?.resetValidation())
}

const editTemplate = (id: number) => {
  loadTemplate(id)
}

watch(locale, async () => {
  await nextTick()
  contentHtml.value = hydratePlaceholders(
    serializePlaceholders(contentHtml.value),
    placeholderGroups.value,
    knownKeys.value,
  )
  editorRef.value?.getEditor()?.commands.setContent(contentHtml.value, false)
})
</script>

<template>
  <VRow class="match-height">
    <!-- Templates List Sidebar -->
    <VCol cols="12" md="5" lg="4">
      <VCard class="d-flex flex-column h-100">
        <VCardItem class="pb-4">
          <template #prepend>
            <VIcon icon="tabler-files" size="24" class="text-primary mr-2" />
          </template>
          <VCardTitle>{{ t('pages.settings.policies.policies.contractTemplates') }}</VCardTitle>
          <VCardSubtitle>{{ t('pages.settings.policies.policies.manageSavedLegalStructures') }}</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText class="pa-0 flex-grow-1 template-list-container">
          <div v-if="isLoading && !templates.length" class="d-flex justify-center align-center pa-6">
            <VProgressCircular indeterminate color="primary" />
          </div>

          <div v-else-if="!templates.length" class="text-center pa-8 text-medium-emphasis">
            <VIcon icon="tabler-folder-off" size="48" class="mb-2 opacity-50" />
            <div class="text-body-1 font-weight-medium">{{ t('pages.settings.policies.policies.noTemplatesFound') }}</div>
            <div class="text-caption">{{ t('pages.settings.policies.policies.useTheFormToCreateYourFirstTemplate') }}</div>
          </div>

          <VList v-else lines="two" class="pa-2">
            <VListItem v-for="template in templates" :key="template.id" :active="templateId === template.id"
              color="primary" rounded="lg" class="mb-1" @click="editTemplate(template.id)">
              <template #prepend>
                <VAvatar color="primary" variant="tonal" size="38" class="mr-3">
                  <VIcon icon="tabler-file-description" size="20" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">{{ template.name }}</VListItemTitle>
              <VListItemSubtitle class="mt-1 d-flex align-center gap-2">
                <VChip size="x-small" color="secondary" variant="elevated">
                  {{ templateTypeName(template) }}
                </VChip>
                <VChip v-if="template.type_slug === 'employment' && template.position_id" size="x-small"
                  variant="tonal" color="info">
                  {{ positions.find(p => p.id === template.position_id)?.title || tr('position') }}
                </VChip>
              </VListItemSubtitle>

              <template #append>
                <div class="d-flex align-center gap-1">
                  <VBtn icon variant="text" size="small" color="primary" @click.stop="editTemplate(template.id)">
                    <VIcon icon="tabler-edit" size="18" />
                    <VTooltip activator="parent" location="top">{{ t('pages.settings.policies.policies.editTemplate') }}</VTooltip>
                  </VBtn>
                  <VBtn icon variant="text" size="small" color="error" @click.stop="confirmDelete(template.id)">
                    <VIcon icon="tabler-trash" size="18" />
                    <VTooltip activator="parent" location="top">{{ t('pages.settings.policies.policies.deleteTemplate') }}</VTooltip>
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Main Editor Form -->
    <VCol cols="12" md="7" lg="8">
      <VCard class="contract-policies-form d-flex flex-column h-100">
        <VCardItem class="pb-4">
          <template #prepend>
            <VIcon :icon="templateId ? 'tabler-edit' : 'tabler-file-plus'" size="24" class="text-primary mr-2" />
          </template>
          <VCardTitle>{{ templateId ? tr('editTemplate') : tr('createNewTemplate') }}</VCardTitle>
          <VCardSubtitle>{{ t('pages.settings.policies.policies.defineTemplatePropertiesAndContractPolicies') }}</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText class="pt-6 flex-grow-1">
          <VForm ref="formRef" class="app-form contract-template-form" @submit.prevent="saveContract">
            <VRow>
              <VCol cols="12" sm="6">
                <VTextField v-model="contractName" class="contract-template-form__name" :label="t('pages.settings.policies.policies.contractName')"
                  :placeholder="t('pages.settings.policies.policies.eGStandardNDA2026')"
                  prepend-inner-icon="tabler-file-text" variant="outlined" :rules="[v => !!v || tr('validation.nameRequired')]" />
              </VCol>

              <!-- Step 1: contract type -->
              <VCol cols="12">
                <ContractTypeSelector ref="typeSelectorRef" v-model="contractTypeId" required
                  @types-changed="onTypesChanged" />
              </VCol>

              <!-- Step 2: context entity. Employment ⇒ position (required);
                   Customer/Company contexts are handled at contract creation,
                   so no entity selector belongs on their templates. -->
              <VCol v-if="isEmployment" cols="12" sm="6">
                <VSelect v-model="positionId" :items="positions" item-title="title" item-value="id"
                  :label="tr('position')" prepend-inner-icon="tabler-briefcase" variant="outlined"
                  :rules="[v => !!v || tr('validation.positionRequired')]"
                  :hint="tr('positionHint')" persistent-hint />
              </VCol>

              <VCol v-if="typeLabel" cols="12">
                <VAlert type="info" variant="tonal" density="compact">
                  {{ tr('templateContext') }} <strong>{{ typeLabel }}</strong>.
                  <span v-if="isEmployment"> {{ tr('employmentPlaceholderHint') }}</span>
                  <span v-else> {{ tr('generalPlaceholderHint') }}</span>
                </VAlert>
              </VCol>

              <VCol cols="12">
                <div class="text-caption text-medium-emphasis mb-2 font-weight-medium">{{ tr('policyHelpers') }}</div>
                <div class="d-flex flex-wrap align-center gap-2">
                  <VAutocomplete v-model="selectedPolicyId"
                    :items="policies.map(p => ({ title: `${p.type} · ${p.label}`, value: p.id }))"
                    :label="tr('insertPolicyClause')" density="compact" variant="outlined" hide-details max-width="320"
                    clearable @update:model-value="value => { if (value) insertPolicy() }" />
                  <span v-if="!policies.length" class="text-caption text-medium-emphasis">{{ tr('noReusableClauses') }}</span>
                </div>
              </VCol>

              <VCol cols="12">
                <VTextarea v-model="coverText" :label="t('pages.settings.policies.policies.coverIntroductionText')"
                  :placeholder="t('pages.settings.policies.policies.briefOverviewOrIntroductoryStatementOfTheContract')"
                  prepend-inner-icon="tabler-align-left" variant="outlined" rows="2" auto-grow />
              </VCol>

              <!-- TipTap editor section -->
              <VCol cols="12">
                <div class="text-caption text-medium-emphasis mb-1 font-weight-medium">
                  {{ t('pages.settings.policies.policies.contractPOLICIESTERMS') }}
                </div>
                <ContractContentEditor ref="editorRef" v-model="contentHtml" :placeholder-groups="placeholderGroups" />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-6">
              <VBtn color="secondary" variant="tonal" prepend-icon="tabler-refresh" :disabled="isLoading" @click="resetForm">
                {{ t('pages.settings.policies.policies.reset') }}
              </VBtn>
              <VBtn type="submit" color="primary" variant="elevated" prepend-icon="tabler-device-floppy" :loading="isLoading">
                {{ templateId ? tr('updateTemplate') : tr('saveTemplate') }}
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <!-- Delete Confirmation Dialog -->
  <VDialog v-model="deleteDialog" max-width="400px">
    <VCard class="text-center pa-4">
      <VCardItem>
        <VAvatar color="error" variant="tonal" size="56" class="mb-2">
          <VIcon icon="tabler-alert-triangle" size="32" />
        </VAvatar>
        <VCardTitle class="text-h5">{{ t('pages.settings.policies.policies.deleteTemplate2') }}</VCardTitle>
      </VCardItem>

      <VCardText class="text-body-2 text-medium-emphasis">
        {{ t('common.confirmations.areYouSureYouWantToDeleteThisContractTemplateThisActionCannotBeUndone') }}
      </VCardText>

      <VCardActions class="justify-center gap-3 mt-2">
        <VBtn color="secondary" variant="tonal" @click="deleteDialog = false">
          {{ t('common.actions.cancel') }}
        </VBtn>
        <VBtn color="error" variant="elevated" :loading="isLoading" @click="deleteTemplate">
          {{ t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.template-list-container {
  max-height: 400px;
  overflow-y: auto;

  @media (min-width: 960px) {
    max-height: calc(100vh - 200px);
  }
}

// Keep Vuetify's normal field geometry and move only the visible value down.
.contract-template-form .v-input:not(.v-textarea) {
  .v-field__input > input,
  .v-select__selection,
  .v-autocomplete__selection {
    transform: translateY(8px);
  }
}

.contract-template-form__name .v-field__input > input {
  transform: translateY(12px) !important;
}
</style>
