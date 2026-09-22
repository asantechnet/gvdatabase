<script setup lang="ts">
import { computed, ref } from 'vue'
import { useToast } from 'vue-toastification'
import ContractTypeApi, { type ContractType, SYSTEM_TYPE_SLUGS } from '@/api/contractType'
import { translateContractPolicyType } from '@/utils/localization'

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
    .map(type => ({ title: translateContractPolicyType(type.slug, type.name), value: type.id, isSystem: true, inactive: !type.is_active })))

const customItems = computed<TypeItem[]>(() =>
  types.value
    .filter(type => !isSystemSlug(type.slug) && type.is_active)
    .map(type => ({ title: type.name, value: type.id, isSystem: false, inactive: false })))

const selectItems = computed(() => [...systemItems.value.filter(item => !item.inactive), ...customItems.value])

const rules = computed(() =>
  props.required ? [(v: unknown) => !!v || tr('validation.contractTypeRequired')] : [])

const fetchTypes = async () => {
  loading.value = true
  try {
    types.value = await ContractTypeApi.getAll({ include_inactive: true })
    emit('types-changed', types.value)
  }
  catch (error) {
    console.error('Failed to load contract types:', error)
    toast.error(tr('messages.loadTypesError'))
  }
  finally {
    loading.value = false
  }
}

fetchTypes()

defineExpose({ fetchTypes })
</script>

<template>
  <div>
    <VRow
      dense
      align="center"
    >
      <VCol
        cols="12"
        sm="8"
      >
        <VSelect
          :model-value="modelValue"
          :items="selectItems"
          item-title="title"
          item-value="value"
          :loading="loading"
          :label="tr('contractType')"
          :placeholder="tr('selectContractType')"
          density="comfortable"
          :disabled="disabled"
          :rules="rules"
          clearable
          @update:model-value="emit('update:modelValue', $event)"
        >
          <template #selection="{ item }">
            <span class="d-inline-flex align-center gap-2">
              {{ item.raw.title }}
              <VChip
                size="x-small"
                :color="item.raw.isSystem ? 'primary' : 'secondary'"
                variant="tonal"
              >
                {{ item.raw.isSystem ? tr('system') : tr('custom') }}
              </VChip>
              <VChip
                v-if="item.raw.inactive"
                size="x-small"
                color="warning"
                variant="tonal"
              >
                {{ tr('inactive') }}
              </VChip>
            </span>
          </template>

          <template #item="{ item, props: itemProps }">
            <VListItem v-bind="itemProps">
              <VListItemSubtitle class="text-caption">
                {{ item.raw.isSystem ? tr('systemTypeAvailable') : tr('customType') }}
                <template v-if="item.raw.inactive">
                  · {{ tr('currentlyInactive') }}
                </template>
              </VListItemSubtitle>

              <template #append>
                <span
                  v-if="item.raw.isSystem"
                  class="d-inline-flex"
                >
                  <VIcon
                    icon="tabler-shield-lock"
                    size="16"
                    class="text-disabled"
                  />
                  <VTooltip
                    activator="parent"
                    location="top"
                  >{{ tr('protectedSystemType') }}</VTooltip>
                </span>
              </template>
            </VListItem>
          </template>
        </VSelect>
      </VCol>
    </VRow>
  </div>
</template>
