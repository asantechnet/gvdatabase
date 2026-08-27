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
    .map(type => ({ title: type.name, value: type.id, isSystem: true, inactive: !type.is_active })))

const customItems = computed<TypeItem[]>(() =>
  types.value
    .filter(type => !isSystemSlug(type.slug))
    .map(type => ({ title: type.name, value: type.id, isSystem: false, inactive: !type.is_active })))

const selectItems = computed(() => [
  ...systemItems.value,
  ...customItems.value,
])

const rules = computed(() =>
  props.required ? [(v: unknown) => !!v || 'Contract type is required'] : [])

const fetchTypes = async () => {
  loading.value = true
  try {
    types.value = await ContractTypeApi.getAll({ include_inactive: true })
    emit('types-changed', types.value)
  } catch (error) {
    console.error('Failed to load contract types:', error)
    toast.error('Failed to load contract types.')
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
    toast.success(`Contract type "${created.name}" was created.`)
  } catch (error) {
    console.error('Failed to create contract type:', error)
    toast.error('Failed to create the contract type.')
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
    toast.success('Contract type deleted.')
  } catch {
    // Referenced or protected types are rejected by the backend with a
    // meaningful message; the backend deactivates in-use types instead.
    await fetchTypes()
    toast.warning('This contract type is in use and has been deactivated instead of deleted.')
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
          :loading="loading" label="Contract Type" placeholder="Select a contract type" density="comfortable"
          :disabled="disabled" :rules="rules" clearable @update:model-value="emit('update:modelValue', $event)">
          <template #selection="{ item }">
            <span class="d-inline-flex align-center gap-2">
              {{ item.raw.title }}
              <VChip size="x-small" :color="item.raw.isSystem ? 'primary' : 'secondary'" variant="tonal">
                {{ item.raw.isSystem ? 'System' : 'Custom' }}
              </VChip>
              <VChip v-if="item.raw.inactive" size="x-small" color="warning" variant="tonal">
                inactive
              </VChip>
            </span>
          </template>

          <template #item="{ item, props: itemProps }">
            <VListItem v-bind="itemProps">
              <VListItemSubtitle class="text-caption">
                {{ item.raw.isSystem ? 'System type — always available' : 'Custom type' }}
                <template v-if="item.raw.inactive"> · currently inactive</template>
              </VListItemSubtitle>

              <template #append>
                <span v-if="item.raw.isSystem" class="d-inline-flex">
                  <VIcon icon="tabler-shield-lock" size="16" class="text-disabled" />
                  <VTooltip activator="parent" location="top">Protected system type</VTooltip>
                </span>
                <VBtn v-else-if="findType(item.raw.value)?.id" icon size="x-small" variant="text" color="error"
                  @click.stop="requestDelete(findType(item.raw.value)!)">
                  <VIcon icon="tabler-trash" />
                  <VTooltip activator="parent" location="top">Delete custom type</VTooltip>
                </VBtn>
              </template>
            </VListItem>
          </template>
        </VSelect>
      </VCol>
      <VCol cols="12" sm="4" class="pt-sm-7">
        <VBtn variant="tonal" color="primary" prepend-icon="tabler-plus" block :disabled="disabled"
          @click="isAddDialogVisible = true">
          Add Contract Type
        </VBtn>
      </VCol>
    </VRow>

    <!-- Inline "add new type" dialog; no navigation away required -->
    <VDialog v-model="isAddDialogVisible" max-width="420px">
      <VCard>
        <VCardTitle>Add Contract Type</VCardTitle>
        <VCardText>
          <VForm ref="newNameForm" @submit.prevent="addType">
            <VTextField v-model="newTypeName" label="Type name" autofocus placeholder="e.g. Service Agreement"
              :rules="[v => !!v?.trim() || 'A name is required',
                       v => v.trim().length <= 100 || 'Maximum 100 characters']" counter="100" />
          </VForm>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" color="secondary" @click="isAddDialogVisible = false">Cancel</VBtn>
          <VBtn color="primary" @click="addType">Create</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog :model-value="typePendingDeletion !== null" max-width="440px" @update:model-value="typePendingDeletion = null">
      <VCard>
        <VCardTitle>Delete contract type</VCardTitle>
        <VCardText>
          Delete the custom contract type
          <strong>{{ typePendingDeletion?.name }}</strong>?
          Types used by templates or contracts cannot be removed and will be deactivated instead.
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" color="secondary" @click="typePendingDeletion = null">Cancel</VBtn>
          <VBtn color="error" @click="confirmDelete">Delete</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
