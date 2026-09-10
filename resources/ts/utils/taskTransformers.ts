import type {
  CalendarEventAdapter,
  FrappeGanttTask,
  KanbanBoardColumn,
  KanbanDataGroup,
  Task,
} from '../types/taskManager'

/**
 * Transforms standard Tasks into Vuexy Kanban data columns and cards
 */
export function toKanbanFormat(
  tasks: Task[],
  columns: KanbanBoardColumn[],
): KanbanDataGroup[] {
  const sortedColumns = [...columns].sort((a, b) => a.order - b.order)

  return sortedColumns.map(col => {
    const colTasks = tasks
      .filter(task => task.boardId === col.id)
      .sort((a, b) => a.order - b.order)

    return {
      id: col.id,
      title: col.title,
      cards: colTasks.map(task => ({
        id: task.id,
        title: task.title,
        boardId: task.boardId,
        dueDate: task.dueDate,
        commentsCount: task.subtasks.length,
        badgeText: task.tags.map(t => t.name),
        assigned: task.assignees,
        rawTask: task,
      })),
    }
  })
}

/**
 * Transforms standard Tasks into FullCalendar event items
 */
export function toCalendarFormat(tasks: Task[]): CalendarEventAdapter[] {
  return tasks.map(task => ({
    id: task.id,
    title: task.title,
    start: task.startDate,
    end: task.dueDate,
    allDay: true,
    extendedProps: {
      description: task.description,
      priority: task.priority,
      progress: task.progress,
      assignees: task.assignees,
      tags: task.tags,
      rawTask: task,
    },
  }))
}

/**
 * Transforms standard Tasks into Frappe Gantt format
 */
export function toFrappeGanttFormat(tasks: Task[]): FrappeGanttTask[] {
  return tasks.map(task => ({
    id: task.id,
    name: task.title,
    start: task.startDate,
    end: task.dueDate,
    progress: task.progress,
    dependencies: task.dependencies.join(', '),
    custom_class: `gantt-priority-${task.priority}`,
  }))
}
