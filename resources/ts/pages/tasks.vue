<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import TaskAppShell from '@/components/task-manager/TaskAppShell.vue'
</script>

<template>
  <div class="tasks-page">
    <ErpPageHeader
      :title="$t('tasksPage.title')"
      section="tasks"
    />
    <TaskAppShell />
  </div>
</template>

<style scoped>
.tasks-page :deep(.kanban-scroll-area) {
  overflow: auto hidden;
  overscroll-behavior-inline: contain;
  scrollbar-width: none;
}
.tasks-page :deep(.kanban-scroll-area::-webkit-scrollbar),
.tasks-page :deep(.kanban-main-wrapper::-webkit-scrollbar) { display: none; }

.tasks-page :deep(.kanban-main-wrapper) {
  min-inline-size: 0;
  padding-block-end: 0.5rem;
  overflow-y: hidden;
  scrollbar-width: none;
}

@media (max-width: 959px) {
  .tasks-page :deep(.kanban-scroll-area) {
    padding: 1rem;
  }
}
</style>
