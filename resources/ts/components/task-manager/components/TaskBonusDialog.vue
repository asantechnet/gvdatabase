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
  >
    <VCard>
      <VCardItem class="px-5 py-4">
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
            variant="text"
            size="small"
            :aria-label="$t('common.actions.close')"
            :title="$t('common.actions.close')"
            @click="isOpen = false"
          />
        </template>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-5">
        <TaskBonusPanel :task="props.task" />
      </VCardText>
    </VCard>
  </VDialog>
</template>
