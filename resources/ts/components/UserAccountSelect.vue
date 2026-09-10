<script setup lang="ts">
import { avatarText } from '@/@core/utils/formatters'

export interface UserAccountOption {
  id: number
  name: string
  email: string
  employee: { id: number; name: string } | null
  marketer?: { id: number; name: string } | null
}

const props = withDefaults(defineProps<{
  items: UserAccountOption[]
  loading?: boolean
  currentMarketerId?: number | null
  currentEmployeeId?: number | null
}>(), { loading: false, currentMarketerId: null, currentEmployeeId: null })

const model = defineModel<number | null>()
const { t } = useI18n()

const options = computed(() => props.items.map(account => ({
  ...account,
  title: account.name,
  value: account.id,
  disabled: Boolean(
    (account.marketer && account.marketer.id !== props.currentMarketerId)
    || (props.currentEmployeeId !== null && account.employee && account.employee.id !== props.currentEmployeeId),
  ),
})))
</script>

<template>
  <VAutocomplete
    v-model="model"
    :items="options"
    item-title="title"
    item-value="value"
    :label="t('common.accountPicker.label')"
    :placeholder="t('common.accountPicker.placeholder')"
    :loading="loading"
    class="account-picker"
    clearable
    auto-select-first
  >
    <template #selection="{ item }">
      <div class="account-picker__selection min-w-0">
        <span class="font-weight-bold text-truncate">{{ item.raw.name }}</span>
        <span class="account-picker__meta text-truncate">#{{ item.raw.id }} · {{ item.raw.email }}</span>
      </div>
    </template>
    <template #item="{ props: itemProps, item }">
      <VListItem
        v-bind="itemProps"
        class="account-picker-option"
        lines="three"
      >
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            size="42"
          >
            {{ avatarText(item.raw.name) }}
          </VAvatar>
        </template>
        <template #title>
          <span class="font-weight-bold">{{ item.raw.name }}</span>
        </template>
        <template #subtitle>
          <div class="account-picker-option__details">
            <span>#{{ item.raw.id }} · {{ item.raw.email }}</span>
            <span v-if="item.raw.employee"><VIcon
              icon="tabler-id-badge-2"
              size="14"
            /> {{ t('common.accountPicker.linkedEmployee', { name: item.raw.employee.name, id: item.raw.employee.id }) }}</span>
            <span v-if="item.raw.marketer"><VIcon
              icon="tabler-speakerphone"
              size="14"
            /> {{ t('common.accountPicker.linkedMarketer', { name: item.raw.marketer.name, id: item.raw.marketer.id }) }}</span>
            <span v-if="!item.raw.employee && !item.raw.marketer">{{ t('common.accountPicker.available') }}</span>
          </div>
        </template>
        <template #append>
          <VIcon
            v-if="item.raw.disabled"
            icon="tabler-lock"
            size="18"
            color="medium-emphasis"
          />
        </template>
      </VListItem>
    </template>
    <template #no-data>
      <div class="pa-4 text-center text-medium-emphasis">
        {{ t('common.accountPicker.noResults') }}
      </div>
    </template>
  </VAutocomplete>
</template>

<style scoped>
.account-picker :deep(.v-field) { border: 1px solid rgba(var(--v-theme-on-surface), .16); border-radius: 12px; background: rgba(var(--v-theme-on-surface), .035); transition: border-color .2s ease, box-shadow .2s ease; }
.account-picker :deep(.v-field--focused) { border-color: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), .12); }
.account-picker__selection { display: grid; line-height: 1.25; }
.account-picker__meta { color: rgba(var(--v-theme-on-surface), .62); font-size: .75rem; }
.min-w-0 { min-width: 0; }
:global(.account-picker-option) { margin: 4px 6px; border: 1px solid transparent; border-radius: 11px; }
:global(.account-picker-option:hover) { border-color: rgba(var(--v-theme-primary), .18); background: rgba(var(--v-theme-primary), .08); }
:global(.account-picker-option__details) { display: grid; gap: 2px; margin-top: 2px; color: rgba(var(--v-theme-on-surface), .64); font-size: .75rem; }
</style>
