<script setup lang="ts">
import ContractTypeApi, { SYSTEM_TYPE_SLUGS, type ContractType } from '@/api/contractType'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps<{
  modelValue: number | null
  disabled?: boolean
  required?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void
  (e: 'types-changed', types: ContractType[]): void
}>()

const toast = useToast()
const { t } = useI18n()
const tr = (key: string, values: Record<string, unknown> = {}) =>
  t(`pages.settings.policies.policies.contractTemplate.${key}`, values)
const types = ref<ContractType[]>([])
const loading = ref(false)
const isAddDialogVisible = ref(false)
const newTypeName = ref('')
const newNameForm = ref()
const typePendingDeletion = ref<ContractType | null>(null)

const isSystemSlug = (slug: string) => SYSTEM_TYPE_SLUGS.includes(slug as typeof SYSTEM_TYPE_SLUGS[number])

// Flat, primitive-valued items: VSelect must always receive/return the
// numeric id (item-value="value"), never an item object.
interface TypeItem {
  title: string
  value: number
  isSystem: boolean
  inactive: boolean
}

const systemItems = computed<TypeItem[]>(() =>
  types.value
    .filter(type => isSystemSlug(type.slug))
    .map(type => ({ title: tr(`types.${type.slug}`), value: type.id, isSystem: true, inactive: !type.is_active })))

const customItems = computed<TypeItem[]>(() =>
  types.value
    .filter(type => !isSystemSlug(type.slug))
    .map(type => ({ title: type.name, value: type.id, isSystem: false, inactive: !type.is_active })))

const selectItems = computed(() => [
  ...systemItems.value,
  ...customItems.value,
])

const rules = computed(() =>
  props.required ? [(v: unknown) => !!v || tr('validation.contractTypeRequired')] : [])

const fetchTypes = async () => {
  loading.value = true
  try {
    types.value = await ContractTypeApi.getAll({ include_inactive: true })
    emit('types-changed', types.value)
  } catch (error) {
    console.error('Failed to load contract types:', error)
    toast.error(tr('messages.loadTypesError'))
  } finally {
    loading.value = false
  }
}

const findType = (id: unknown) => types.value.find(type => type.id === id) ?? null

const selectedType = computed(() => findType(props.modelValue))

const addType = async () => {
  const name = newTypeName.value.trim()
  if (!name) return

  try {
    const created = await ContractTypeApi.create({ name })
    await fetchTypes()
    emit('update:modelValue', created.id)
    isAddDialogVisible.value = false
    newTypeName.value = ''
    toast.success(tr('messages.typeCreated', { name: created.name }))
  } catch (error) {
    console.error('Failed to create contract type:', error)
    toast.error(tr('messages.createTypeError'))
  }
}

const requestDelete = (type: ContractType) => {
  typePendingDeletion.value = type
}

const confirmDelete = async () => {
  const target = typePendingDeletion.value
  if (!target) return
  typePendingDeletion.value = null

  try {
    await ContractTypeApi.delete(target.id)
    await fetchTypes()
    if (props.modelValue === target.id) emit('update:modelValue', null)
    toast.success(tr('messages.typeDeleted'))
  } catch {
    // Referenced or protected types are rejected by the backend with a
    // meaningful message; the backend deactivates in-use types instead.
    await fetchTypes()
    toast.warning(tr('messages.typeDeactivated'))
  }
}

watch(isAddDialogVisible, visible => {
  if (!visible) {
    newTypeName.value = ''
    newNameForm.value?.resetValidation()
  }
})

fetchTypes()

defineExpose({ fetchTypes })
</script>

<template>
  <div>
    <VRow dense align="center">
      <VCol cols="12" sm="8">
        <VSelect :model-value="modelValue" :items="selectItems" item-title="title" item-value="value"
          :loading="loading" :label="tr('contractType')" :placeholder="tr('selectContractType')" density="comfortable"
          :disabled="disabled" :rules="rules" clearable @update:model-value="emit('update:modelValue', $event)">
          <template #selection="{ item }">
            <span class="d-inline-flex align-center gap-2">
              {{ item.raw.title }}
              <VChip size="x-small" :color="item.raw.isSystem ? 'primary' : 'secondary'" variant="tonal">
                {{ item.raw.isSystem ? tr('system') : tr('custom') }}
              </VChip>
              <VChip v-if="item.raw.inactive" size="x-small" color="warning" variant="tonal">
                {{ tr('inactive') }}
              </VChip>
            </span>
          </template>

          <template #item="{ item, props: itemProps }">
            <VListItem v-bind="itemProps">
              <VListItemSubtitle class="text-caption">
                {{ item.raw.isSystem ? tr('systemTypeAvailable') : tr('customType') }}
                <template v-if="item.raw.inactive"> · {{ tr('currentlyInactive') }}</template>
              </VListItemSubtitle>

              <template #append>
                <span v-if="item.raw.isSystem" class="d-inline-flex">
                  <VIcon icon="tabler-shield-lock" size="16" class="text-disabled" />
                  <VTooltip activator="parent" location="top">{{ tr('protectedSystemType') }}</VTooltip>
                </span>
                <VBtn v-else-if="findType(item.raw.value)?.id" icon size="x-small" variant="text" color="error"
                  @click.stop="requestDelete(findType(item.raw.value)!)">
                  <VIcon icon="tabler-trash" />
                  <VTooltip activator="parent" location="top">{{ tr('deleteCustomType') }}</VTooltip>
                </VBtn>
              </template>
            </VListItem>
          </template>
        </VSelect>
      </VCol>
      <VCol cols="12" sm="4" class="pt-sm-7">
        <VBtn variant="tonal" color="primary" prepend-icon="tabler-plus" block :disabled="disabled"
          @click="isAddDialogVisible = true">
          {{ tr('addContractType') }}
        </VBtn>
      </VCol>
    </VRow>

    <!-- Inline "add new type" dialog; no navigation away required -->
    <VDialog v-model="isAddDialogVisible" max-width="420px">
      <VCard>
        <VCardTitle>{{ tr('addContractType') }}</VCardTitle>
        <VCardText>
          <VForm ref="newNameForm" @submit.prevent="addType">
            <VTextField v-model="newTypeName" :label="tr('typeName')" autofocus :placeholder="tr('typeNameExample')"
              :rules="[v => !!v?.trim() || tr('validation.nameRequired'),
                       v => v.trim().length <= 100 || tr('validation.maximumCharacters', { count: 100 })]" counter="100" />
          </VForm>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" color="secondary" @click="isAddDialogVisible = false">{{ t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" @click="addType">{{ tr('create') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog :model-value="typePendingDeletion !== null" max-width="440px" @update:model-value="typePendingDeletion = null">
      <VCard>
        <VCardTitle>{{ tr('deleteContractType') }}</VCardTitle>
        <VCardText>
          {{ tr('deleteTypePrompt', { name: typePendingDeletion?.name ?? '' }) }}
          {{ tr('deleteTypeHint') }}
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" color="secondary" @click="typePendingDeletion = null">{{ t('common.actions.cancel') }}</VBtn>
          <VBtn color="error" @click="confirmDelete">{{ t('common.actions.delete') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
