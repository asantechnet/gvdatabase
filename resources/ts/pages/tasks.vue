<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TaskAppShell from '@/components/task-manager/TaskAppShell.vue'
</script>

<template>
  <div class="tasks-page">
    <ErpPageHeader
      :title="$t('tasksPage.title')"
      :description="$t('tasksPage.description')"
      section="tasks"
    />
    <TaskAppShell />
  </div>
</template>

<style scoped>
.tasks-page :deep(.kanban-scroll-area) {
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  scrollbar-gutter: stable;
}

.tasks-page :deep(.kanban-main-wrapper) {
  min-inline-size: 0;
  padding-block-end: 0.5rem;
  scrollbar-width: thin;
}

@media (max-width: 959px) {
  .tasks-page :deep(.kanban-scroll-area) {
    padding: 1rem;
  }
}
</style>
