<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Task } from '@/api/kanban'
import TaskBonusPanel from './TaskBonusPanel.vue'

const props = defineProps<{ task: Task }>()
const { t } = useI18n()
const isOpen = ref(false)
const label = computed(() => t('components.taskManager.taskbonuspanel.open'))
</script>

<template>
  <VTooltip
    :text="label"
    location="bottom"
  >
    <template #activator="{ props: tooltipProps }">
      <VBtn
        v-bind="tooltipProps"
        icon="tabler-settings"
        variant="text"
        size="small"
        :aria-label="label"
        :title="label"
        @click="isOpen = true"
      />
    </template>
  </VTooltip>

  <VDialog
    v-model="isOpen"
    max-width="640"
    scrollable
    content-class="task-bonus-dialog"
  >
    <VCard class="task-bonus-dialog__card">
      <VCardItem class="task-bonus-dialog__header px-5 py-4">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            size="38"
          >
            <VIcon icon="tabler-calculator" />
          </VAvatar>
        </template>
        <VCardTitle>{{ $t('components.taskManager.taskbonuspanel.title') }}</VCardTitle>
        <VCardSubtitle>{{ $t('components.taskManager.taskbonuspanel.subtitle') }}</VCardSubtitle>
        <template #append>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="error"
            size="small"
            :aria-label="$t('common.actions.close')"
            :title="$t('common.actions.close')"
            @click="isOpen = false"
          />
        </template>
      </VCardItem>

      <VCardText class="pa-5">
        <TaskBonusPanel :task="props.task" />
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.task-bonus-dialog__card { overflow: hidden; }
.task-bonus-dialog__header { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); border-block-end: 1px solid rgba(var(--v-border-color), .5); }
:global(.v-overlay:has(.task-bonus-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
</style>
