import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import KanbanApi, {
  type Board,
  type Comment,
  type CreateTaskData,
  type MoveTaskData,
  type Subtask,
  type Tag,
  type Task,
  type UpdateTaskData,
  type User,
} from '@/api/kanban'
import TaskApi from '@/api/task'
import SubtaskApi from '@/api/subtask'
import BoardApi from '@/api/board'
import CommentApi from '@/api/comment'
import TagApi from '@/api/tag'
import {
  canAccess,
  canCreateTaskOnBoard,
  canDeleteTaskOnBoard,
  canEditTaskOnBoard,
  canManagePersonalTasks,
} from '@/utils/authorization'
import { useCookie } from '@core/composable/useCookie'
import type {
  CalendarEventAdapter,
  FrappeGanttTask,
  KanbanBoardColumn,
  KanbanCardAdapter,
  KanbanDataGroup,
  SubTask,
  TaskAssignee,
  TaskPriority,
  TaskTag,
} from '@/types/taskManager'

const addCalendarDays = (date: string, days: number) => {
  const [year, month, day] = date.split('-').map(Number)
  const value = new Date(Date.UTC(year, month - 1, day + days))

  return value.toISOString().slice(0, 10)
}

export const useTaskStore = defineStore('taskManager', () => {
  const userData = useCookie<{ id?: string | number } | null>('userData')
  const currentUserId = () => String(userData.value?.id ?? '')
  const boardFor = (boardId: string | number) => boards.value.find(board => String(board.id) === String(boardId))

  const canUpdateSubtask = (subtask: Subtask) => canAccess('edit_all_subtasks')
    || (canAccess('edit_own_subtasks') && subtask.userId === currentUserId())

  const canDeleteSubtask = (subtask: Subtask) => canAccess('delete_all_subtasks')
    || (canAccess('delete_own_subtasks') && subtask.userId === currentUserId())

  // State
  const boards = ref<Board[]>([])
  const tasks = ref<Task[]>([])
  const activeView = ref<'kanban' | 'calendar' | 'gantt' | 'todos' | 'settings'>('kanban')
  const selectedTaskId = ref<string | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const tags = ref<Tag[]>([])

  // Getters
  const selectedTask = computed(() => {
    return tasks.value.find(task => task.id === selectedTaskId.value) || null
  })

  const columns = computed<KanbanBoardColumn[]>(() => {
    return boards.value
      .sort((a, b) => a.order - b.order)
      .map(board => ({
        id: board.id,
        title: board.title,
        order: board.order,
        isPersonal: board.is_personal,
        isOwned: board.is_owned,
      }))
  })

  const rawTasks = computed(() => tasks.value)

  const ganttTasks = computed<FrappeGanttTask[]>(() => {
    const today = new Date().toISOString().slice(0, 10)

    return tasks.value.map(task => {
      // Old tasks can legitimately have no dates. Never pass missing or
      // backwards dates to Frappe Gantt because it drops them internally
      // without rebuilding its task indexes.
      const start = task.startDate || today
      const end = task.dueDate && task.dueDate >= start ? task.dueDate : start

      return {
        id: task.id,
        name: task.title,
        start,
        end,
        progress: task.progress,
        dependencies: task.dependencies.join(','),
        custom_class: `gantt-priority-${task.priority}`,
      }
    })
  })

  const calendarEvents = computed<CalendarEventAdapter[]>(() => {
    return tasks.value.map(task => ({
      id: task.id,
      title: task.title,
      start: task.startDate,

      // FullCalendar treats all-day end dates as exclusive. Tasks store an
      // inclusive due date, so add one day to render same-day tasks correctly.
      end: task.dueDate ? addCalendarDays(task.dueDate, 1) : task.startDate,
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
  })

  const kanbanData = computed(() => {
    const kanbanBoards: KanbanDataGroup[] = boards.value
      .sort((a, b) => a.order - b.order)
      .map(board => ({
        id: board.id,
        title: board.title,
        cards: tasks.value
          .filter(task => task.boardId === board.id)
          .sort((a, b) => a.order - b.order)
          .map(task => ({
            id: task.id,
            title: task.title,
            boardId: task.boardId,
            dueDate: task.dueDate,
            commentsCount: task.comments.length,
            badgeText: task.tags.map(t => t.name),
            assigned: task.assignees.map(assignee => ({
              id: assignee.id,
              name: assignee.name,
              avatar: assignee.avatar || getFallbackAvatar(assignee.id),
              email: assignee.email,
            })),
            rawTask: task,
          })),
      }))

    return kanbanBoards
  })

  // Generate fallback avatar URL
  const getFallbackAvatar = (id: string) => {
    const num = Number.parseInt(id) % 8 + 1

    return `/images/avatars/avatar-${num}.png`
  }

  // Actions
  const fetchBoardData = async () => {
    try {
      isLoading.value = true
      error.value = null

      const response = await KanbanApi.getBoard()

      boards.value = response.data.data
      tasks.value = response.data.data.flatMap(board => board.tasks).map(task => ({
        ...task,
        attachments: task.attachments ?? [],
      }))
    }
    catch (err) {
      error.value = 'Failed to fetch board data'
      console.error('Failed to fetch board data:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const fetchTags = async () => {
    try {
      isLoading.value = true
      error.value = null
      tags.value = await TagApi.getAll()
    }
    catch (err) {
      error.value = 'Failed to fetch tags'
      console.error('Failed to fetch tags:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const fetchBoardsOnly = async () => {
    try {
      isLoading.value = true
      error.value = null

      const response = await BoardApi.getAll()

      boards.value = response.data.data
    }
    catch (err) {
      error.value = 'Failed to fetch boards'
      console.error('Failed to fetch boards:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const addTask = async (taskData: Omit<CreateTaskData, 'board_id'> & { boardId: string }): Promise<Task | null> => {
    const board = boardFor(taskData.boardId)
    if (!canCreateTaskOnBoard(board?.is_personal, board?.is_owned))
      return null
    try {
      isLoading.value = true
      error.value = null

      const response = await TaskApi.create({
        ...taskData,
        board_id: taskData.boardId,
      })

      tasks.value.push(response.data.data)

      return response.data.data
    }
    catch (err) {
      error.value = 'Failed to create task'
      console.error('Failed to create task:', err)

      return null
    }
    finally {
      isLoading.value = false
    }
  }

  const updateTask = async (id: string, data: UpdateTaskData) => {
    const existing = tasks.value.find(task => task.id === id)
    const board = existing ? boardFor(existing.boardId) : undefined
    if (!canEditTaskOnBoard(board?.is_personal, board?.is_owned))
      return
    try {
      isLoading.value = true
      error.value = null

      const response = await TaskApi.update(id, data)
      const index = tasks.value.findIndex(task => task.id === id)
      if (index !== -1) {
        const returnedTask = response.data.data
        if (returnedTask) {
          tasks.value[index] = { ...returnedTask, attachments: returnedTask.attachments ?? [] }

          return
        }

        // Create a new object with the updated data
        const updatedTask = { ...tasks.value[index] }

        // Update basic fields
        if (data.title)
          updatedTask.title = data.title
        if (data.description !== undefined)
          updatedTask.description = data.description
        if (data.priority)
          updatedTask.priority = data.priority
        if (data.progress !== undefined)
          updatedTask.progress = data.progress
        if (data.start_date)
          updatedTask.startDate = data.start_date
        if (data.due_date)
          updatedTask.dueDate = data.due_date

        // Update assignees if provided
        if (data.assignees) {
          updatedTask.assignees = data.assignees.map(assigneeId => {
            // Check if assignee already exists
            const existingAssignee = updatedTask.assignees.find(a => a.id === assigneeId)
            if (existingAssignee)
              return existingAssignee

            // Create a new assignee object with fallback avatar
            return {
              id: assigneeId,
              name: 'Unknown',
              email: '',
              avatar: getFallbackAvatar(assigneeId),
            }
          })
        }

        // Update tags if provided
        if (data.tags) {
          updatedTask.tags = data.tags.map(tagId => {
            // Check if tag already exists in our local tags list
            const existingTag = tags.value.find(t => t.id === tagId)
            if (existingTag)
              return existingTag

            // Check if tag exists in the current task's tags
            const taskTag = updatedTask.tags.find(t => t.id === tagId)
            if (taskTag)
              return taskTag

            // Create a new tag object with default values
            return {
              id: tagId,
              name: 'New Tag',
              color: 'primary',
            }
          })
        }

        if (data.dependencies !== undefined)
          updatedTask.dependencies = data.dependencies

        // Replace the task in the array
        tasks.value[index] = updatedTask
      }
    }
    catch (err) {
      error.value = 'Failed to update task'
      console.error('Failed to update task:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const updateTaskDates = async (id: string, startDate: string, dueDate: string) => {
    const existing = tasks.value.find(task => task.id === id)
    const board = existing ? boardFor(existing.boardId) : undefined
    if (!canEditTaskOnBoard(board?.is_personal, board?.is_owned))
      return false
    const index = tasks.value.findIndex(task => task.id === id)
    if (index === -1)
      return false

    const previousTask = { ...tasks.value[index] }

    tasks.value[index] = {
      ...tasks.value[index],
      startDate,
      dueDate,
    }

    try {
      isLoading.value = true
      error.value = null
      await TaskApi.update(id, { start_date: startDate, due_date: dueDate })

      return true
    }
    catch (err) {
      tasks.value[index] = previousTask
      error.value = 'Failed to update task dates'
      console.error('Failed to update task dates:', err)

      return false
    }
    finally {
      isLoading.value = false
    }
  }

  const deleteTask = async (id: string) => {
    const existing = tasks.value.find(task => task.id === id)
    const board = existing ? boardFor(existing.boardId) : undefined
    if (!canDeleteTaskOnBoard(board?.is_personal, board?.is_owned))
      return
    try {
      isLoading.value = true
      error.value = null
      await TaskApi.delete(id)
      tasks.value = tasks.value.filter(task => task.id !== id)
      if (selectedTaskId.value === id)
        selectedTaskId.value = null
    }
    catch (err) {
      error.value = 'Failed to delete task'
      console.error('Failed to delete task:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const moveTaskBoard = async (taskId: string, boardId: string, order: number) => {
    const task = tasks.value.find(candidate => candidate.id === taskId)
    const sourceBoard = task ? boardFor(task.boardId) : undefined
    const destinationBoard = boardFor(boardId)
    const personalMove = canManagePersonalTasks(sourceBoard?.is_personal, sourceBoard?.is_owned)
      && canManagePersonalTasks(destinationBoard?.is_personal, destinationBoard?.is_owned)
      && String(sourceBoard?.id ?? '') === String(destinationBoard?.id ?? '')

    if (!canAccess('move_assigned_tasks') && !personalMove)
      return
    try {
      isLoading.value = true
      error.value = null

      const data: MoveTaskData = { board_id: boardId, order }
      const response = await TaskApi.move(taskId, data)

      response.data.positions.forEach(position => {
        const task = tasks.value.find(candidate => candidate.id === position.id)
        if (task) {
          task.boardId = position.boardId
          task.order = position.order
        }
      })
    }
    catch (err) {
      error.value = 'Failed to move task'
      console.error('Failed to move task:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const addSubTask = async (
    taskId: string,
    title: string,
    category: string = 'General',
    priority: TaskPriority = 'medium',
  ) => {
    if (!canAccess('create_subtasks_on_assigned_tasks'))
      return
    try {
      isLoading.value = true
      error.value = null

      const task = tasks.value.find(t => t.id === taskId)
      if (!task)
        return

      const response = await SubtaskApi.create(taskId, { title, category, priority })

      task.subtasks.push(response.data.data)
      task.progress = response.data.taskProgress
    }
    catch (err) {
      error.value = 'Failed to add subtask'
      console.error('Failed to add subtask:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const toggleSubTask = async (taskId: string, subtaskId: string) => {
    try {
      isLoading.value = true
      error.value = null

      const task = tasks.value.find(t => t.id === taskId)
      if (!task)
        return

      const subtask = task.subtasks.find(st => st.id === subtaskId)
      if (!subtask)
        return
      if (!canUpdateSubtask(subtask))
        return

      const response = await SubtaskApi.update(taskId, subtaskId, {
        is_completed: !subtask.isCompleted,
      })

      subtask.isCompleted = response.data.data.isCompleted
      task.progress = response.data.taskProgress
    }
    catch (err) {
      error.value = 'Failed to toggle subtask'
      console.error('Failed to toggle subtask:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const deleteSubTask = async (taskId: string, subtaskId: string) => {
    try {
      isLoading.value = true
      error.value = null

      const task = tasks.value.find(t => t.id === taskId)
      if (!task)
        return
      const subtask = task.subtasks.find(st => st.id === subtaskId)
      if (!subtask || !canDeleteSubtask(subtask))
        return

      const response = await SubtaskApi.delete(taskId, subtaskId)

      task.subtasks = task.subtasks.filter(st => st.id !== subtaskId)
      task.progress = response.data.taskProgress
    }
    catch (err) {
      error.value = 'Failed to delete subtask'
      console.error('Failed to delete subtask:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const updateSubTask = async (taskId: string, subtaskId: string, data: { title: string; category: string; priority: TaskPriority }) => {
    const task = tasks.value.find(candidate => candidate.id === taskId)
    const subtask = task?.subtasks.find(candidate => candidate.id === subtaskId)
    if (!task || !subtask || !subtask.canEdit)
      return
    const response = await SubtaskApi.update(taskId, subtaskId, data)

    Object.assign(subtask, response.data.data)
    task.progress = response.data.taskProgress
  }

  const addComment = async (taskId: string, content: string) => {
    if (!canAccess('create_comments_on_assigned_tasks'))
      return
    try {
      isLoading.value = true
      error.value = null

      const response = await CommentApi.create(taskId, { content })
      const task = tasks.value.find(t => t.id === taskId)
      if (task) {
        if (!task.comments)
          task.comments = []

        task.comments.push(response.data.data)
      }
    }
    catch (err) {
      error.value = 'Failed to add comment'
      console.error('Failed to add comment:', err)
    }
    finally {
      isLoading.value = false
    }
  }

  const updateComment = async (taskId: string, commentId: string, content: string) => {
    const task = tasks.value.find(candidate => candidate.id === taskId)
    const comment = task?.comments.find(candidate => candidate.id === commentId)
    if (!comment || !comment.canEdit)
      return
    const response = await CommentApi.update(taskId, commentId, content)

    Object.assign(comment, response.data.data)
  }

  const setView = (view: 'kanban' | 'calendar' | 'gantt' | 'todos' | 'settings') => {
    activeView.value = view
  }

  const selectTask = (id: string | null) => {
    selectedTaskId.value = id
  }

  return {
    boards,
    tasks,
    tags,
    activeView,
    selectedTaskId,
    selectedTask,
    columns,
    rawTasks,
    ganttTasks,
    calendarEvents,
    kanbanData,
    isLoading,
    error,
    fetchBoardData,
    fetchTags,
    fetchBoardsOnly,
    addTask,
    updateTask,
    updateTaskDates,
    deleteTask,
    moveTaskBoard,
    addSubTask,
    toggleSubTask,
    updateSubTask,
    deleteSubTask,
    addComment,
    updateComment,
    setView,
    selectTask,
  }
})
