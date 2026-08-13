export type TaskPriority = "low" | "medium" | "high" | "urgent";

export interface TaskTag {
  id: string;
  name: string;
  color: string; // Hex color or Vuexy theme variable (e.g. 'primary', 'error', 'success')
}

export interface TaskAssignee {
  id: string;
  name: string;
  avatar: string;
  email: string;
  contributionPercent?: number | null;
}

export interface SubTask {
  id: string;
  taskId: string;
  userId: string;
  title: string;
  category: string;
  priority: TaskPriority;
  description?: string;
  tag?: string;
  isCompleted: boolean;
  createdAt: string;
  editableUntil: string;
  canEdit: boolean;
  editWindowExpired: boolean;
}

export interface KanbanBoardColumn {
  id: string;
  title: string;
  order: number;
}

// --------------------------------------------------------------------------
// Master Task Interface (Backend Ground Truth)
// --------------------------------------------------------------------------
export interface Task {
  id: string;
  boardId: string; // Maps to Kanban Column ID ('todo', 'in_progress', 'review', 'done')
  order: number; // Drag-and-drop ordering within column
  title: string;
  description: string; // Rich HTML string from Quill Editor
  priority: TaskPriority;
  startDate: string; // ISO format: 'YYYY-MM-DD'
  dueDate: string; // ISO format: 'YYYY-MM-DD'
  dependencies: string[]; // Array of prerequisite Task IDs (used for Gantt dependency arrows)
  tags: TaskTag[];
  assignees: TaskAssignee[];
  subtasks: SubTask[]; // Granular Mini-Todos
  progress: number; // Computed percentage: 0 to 100
  createdAt: string;
  updatedAt: string;
  completedAt: string | null;
  reopenedCount: number;
  rejectedCount: number;
  managerQualityScore: number | null;
}

// --------------------------------------------------------------------------
// View-Specific Adapter Interfaces
// --------------------------------------------------------------------------

// Frappe Gantt JS expected task shape
export interface FrappeGanttTask {
  id: string;
  name: string;
  start: string;
  end: string;
  progress: number;
  dependencies: string; // Comma-separated string of IDs: "task-1, task-2"
  custom_class?: string; // Priority styling class (e.g., 'gantt-priority-high')
}

// FullCalendar expected event shape
export interface CalendarEventAdapter {
  id: string;
  title: string;
  start: string;
  end: string;
  allDay: boolean;
  extendedProps: {
    description: string;
    priority: TaskPriority;
    progress: number;
    assignees: TaskAssignee[];
    tags: TaskTag[];
    rawTask: Task;
  };
}

// Vuexy Kanban Data Structure
export interface KanbanCardAdapter {
  id: string;
  title: string;
  boardId: string;
  dueDate: string;
  commentsCount: number;
  badgeText: string[];
  assigned: TaskAssignee[];
  rawTask: Task;
}

export interface KanbanDataGroup {
  id: string;
  title: string;
  cards: KanbanCardAdapter[];
}
