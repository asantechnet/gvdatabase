import { defineStore } from "pinia";
import { ref, computed } from "vue";
import KanbanApi, {
  type Board,
  type Task,
  type User,
  type Tag,
  type Comment,
  type CreateTaskData,
  type UpdateTaskData,
  type MoveTaskData
} from '@/api/kanban'
import TaskApi from '@/api/task'
import SubtaskApi from '@/api/subtask'
import BoardApi from '@/api/board'
import CommentApi from '@/api/comment'
import TagApi from '@/api/tag'
import type {
  TaskPriority,
  TaskTag,
  TaskAssignee,
  SubTask,
  KanbanBoardColumn,
  FrappeGanttTask,
  CalendarEventAdapter,
  KanbanCardAdapter,
  KanbanDataGroup
} from '@/types/taskManager'

export const useTaskStore = defineStore("taskManager", () => {
  // State
  const boards = ref<Board[]>([])
  const tasks = ref<Task[]>([])
  const activeView = ref<'kanban' | 'calendar' | 'gantt' | 'todos'>('kanban')
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
        order: board.order
      }))
  })

  const rawTasks = computed(() => tasks.value)

  const ganttTasks = computed<FrappeGanttTask[]>(() => {
    return tasks.value.map(task => ({
      id: task.id,
      name: task.title,
      start: task.startDate,
      end: task.dueDate,
      progress: task.progress,
      dependencies: task.dependencies.join(','),
      custom_class: `gantt-priority-${task.priority}`
    }))
  })

  const calendarEvents = computed<CalendarEventAdapter[]>(() => {
    return tasks.value.map(task => ({
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
        rawTask: task
      }
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
              email: assignee.email
            })),
            rawTask: task
          }))
      }))

    return kanbanBoards
  })

  // Generate fallback avatar URL
  const getFallbackAvatar = (id: string) => {
    const num = parseInt(id) % 8 + 1
    return `/images/avatars/avatar-${num}.png`
  }

  // Actions
  const fetchBoardData = async () => {
    try {
      isLoading.value = true
      error.value = null
      const response = await KanbanApi.getBoard()
      boards.value = response.data.data
      tasks.value = response.data.data.flatMap(board => board.tasks)
    } catch (err) {
      error.value = 'Failed to fetch board data'
      console.error('Failed to fetch board data:', err)
    } finally {
      isLoading.value = false
    }
  }

  const fetchTags = async () => {
    try {
      isLoading.value = true
      error.value = null
      tags.value = await TagApi.getAll()
    } catch (err) {
      error.value = 'Failed to fetch tags'
      console.error('Failed to fetch tags:', err)
    } finally {
      isLoading.value = false
    }
  }

  const fetchBoardsOnly = async () => {
    try {
      isLoading.value = true
      error.value = null
      const response = await BoardApi.getAll()
      boards.value = response.data.data
    } catch (err) {
      error.value = 'Failed to fetch boards'
      console.error('Failed to fetch boards:', err)
    } finally {
      isLoading.value = false
    }
  }

  const addTask = async (taskData: Omit<CreateTaskData, 'board_id'> & { boardId: string }) => {
    try {
      isLoading.value = true
      error.value = null
      const response = await TaskApi.create({
        ...taskData,
        board_id: taskData.boardId
      })
      tasks.value.push(response.data.data)
    } catch (err) {
      error.value = 'Failed to create task'
      console.error('Failed to create task:', err)
    } finally {
      isLoading.value = false
    }
  }

  const updateTask = async (id: string, data: UpdateTaskData) => {
    try {
      isLoading.value = true
      error.value = null
      await TaskApi.update(id, data)
      const index = tasks.value.findIndex(task => task.id === id)
      if (index !== -1) {
        // Create a new object with the updated data
        const updatedTask = { ...tasks.value[index] }

        // Update basic fields
        if (data.title) updatedTask.title = data.title
        if (data.description) updatedTask.description = data.description
        if (data.priority) updatedTask.priority = data.priority
        if (data.progress !== undefined) updatedTask.progress = data.progress
        if (data.start_date) updatedTask.startDate = data.start_date
        if (data.due_date) updatedTask.dueDate = data.due_date

        // Update assignees if provided
        if (data.assignees) {
          updatedTask.assignees = data.assignees.map(assigneeId => {
            // Check if assignee already exists
            const existingAssignee = updatedTask.assignees.find(a => a.id === assigneeId)
            if (existingAssignee) return existingAssignee

            // Create a new assignee object with fallback avatar
            return {
              id: assigneeId,
              name: 'Unknown',
              email: '',
              avatar: getFallbackAvatar(assigneeId)
            }
          })
        }

        // Update tags if provided
        if (data.tags) {
          updatedTask.tags = data.tags.map(tagId => {
            // Check if tag already exists in our local tags list
            const existingTag = tags.value.find(t => t.id === tagId)
            if (existingTag) return existingTag

            // Check if tag exists in the current task's tags
            const taskTag = updatedTask.tags.find(t => t.id === tagId)
            if (taskTag) return taskTag

            // Create a new tag object with default values
            return {
              id: tagId,
              name: 'New Tag',
              color: 'primary'
            }
          })
        }

        // Replace the task in the array
        tasks.value[index] = updatedTask
      }
    } catch (err) {
      error.value = 'Failed to update task'
      console.error('Failed to update task:', err)
    } finally {
      isLoading.value = false
    }
  }

  const updateTaskDates = async (id: string, startDate: string, dueDate: string) => {
    try {
      isLoading.value = true
      error.value = null
      await TaskApi.update(id, { start_date: startDate, due_date: dueDate })
      const index = tasks.value.findIndex(task => task.id === id)
      if (index !== -1) {
        tasks.value[index].startDate = startDate
        tasks.value[index].dueDate = dueDate
      }
    } catch (err) {
      error.value = 'Failed to update task dates'
      console.error('Failed to update task dates:', err)
    } finally {
      isLoading.value = false
    }
  }

  const deleteTask = async (id: string) => {
    try {
      isLoading.value = true
      error.value = null
      await TaskApi.delete(id)
      tasks.value = tasks.value.filter(task => task.id !== id)
      if (selectedTaskId.value === id) {
        selectedTaskId.value = null
      }
    } catch (err) {
      error.value = 'Failed to delete task'
      console.error('Failed to delete task:', err)
    } finally {
      isLoading.value = false
    }
  }

  const moveTaskBoard = async (taskId: string, boardId: string, order: number) => {
    try {
      isLoading.value = true
      error.value = null
      const data: MoveTaskData = { board_id: boardId, order }
      await TaskApi.move(taskId, data)

      const taskIndex = tasks.value.findIndex(task => task.id === taskId)
      if (taskIndex !== -1) {
        tasks.value[taskIndex].boardId = boardId
        tasks.value[taskIndex].order = order
      }
    } catch (err) {
      error.value = 'Failed to move task'
      console.error('Failed to move task:', err)
    } finally {
      isLoading.value = false
    }
  }

  const addSubTask = async (
    taskId: string,
    title: string,
    category: string = 'General',
    priority: TaskPriority = 'medium'
  ) => {
    try {
      isLoading.value = true
      error.value = null
      const task = tasks.value.find(t => t.id === taskId)
      if (!task) return

      const response = await SubtaskApi.create(taskId, { title, category, priority })
      task.subtasks.push(response.data.data)
      task.progress = response.data.taskProgress
    } catch (err) {
      error.value = 'Failed to add subtask'
      console.error('Failed to add subtask:', err)
    } finally {
      isLoading.value = false
    }
  }

  const toggleSubTask = async (taskId: string, subtaskId: string) => {
    try {
      isLoading.value = true
      error.value = null
      const task = tasks.value.find(t => t.id === taskId)
      if (!task) return

      const subtask = task.subtasks.find(st => st.id === subtaskId)
      if (!subtask) return

      const response = await SubtaskApi.update(taskId, subtaskId, {
        is_completed: !subtask.isCompleted,
      })
      subtask.isCompleted = response.data.data.isCompleted
      task.progress = response.data.taskProgress
    } catch (err) {
      error.value = 'Failed to toggle subtask'
      console.error('Failed to toggle subtask:', err)
    } finally {
      isLoading.value = false
    }
  }

  const deleteSubTask = async (taskId: string, subtaskId: string) => {
    try {
      isLoading.value = true
      error.value = null
      const task = tasks.value.find(t => t.id === taskId)
      if (!task) return

      const response = await SubtaskApi.delete(taskId, subtaskId)
      task.subtasks = task.subtasks.filter(st => st.id !== subtaskId)
      task.progress = response.data.taskProgress
    } catch (err) {
      error.value = 'Failed to delete subtask'
      console.error('Failed to delete subtask:', err)
    } finally {
      isLoading.value = false
    }
  }

  const addComment = async (taskId: string, content: string) => {
    try {
      isLoading.value = true
      error.value = null
      const response = await CommentApi.create(taskId, { content })
      const task = tasks.value.find(t => t.id === taskId)
      if (task) {
        if (!task.comments) {
          task.comments = []
        }
        task.comments.push(response.data.data)
      }
    } catch (err) {
      error.value = 'Failed to add comment'
      console.error('Failed to add comment:', err)
    } finally {
      isLoading.value = false
    }
  }

  const createBoard = async (title: string) => {
    try {
      isLoading.value = true
      error.value = null
      const response = await BoardApi.create({ title })
      boards.value.push(response.data.data)
    } catch (err) {
      error.value = 'Failed to create board'
      console.error('Failed to create board:', err)
    } finally {
      isLoading.value = false
    }
  }

  const updateBoard = async (id: string, title: string) => {
    try {
      isLoading.value = true
      error.value = null
      await BoardApi.update(id, { title })
      const index = boards.value.findIndex(board => board.id === id)
      if (index !== -1) {
        boards.value[index].title = title
      }
    } catch (err) {
      error.value = 'Failed to update board'
      console.error('Failed to update board:', err)
    } finally {
      isLoading.value = false
    }
  }

  const deleteBoard = async (id: string) => {
    try {
      isLoading.value = true
      error.value = null
      await BoardApi.delete(id)
      boards.value = boards.value.filter(board => board.id !== id)
      tasks.value = tasks.value.filter(task => task.boardId !== id)
    } catch (err) {
      error.value = 'Failed to delete board'
      console.error('Failed to delete board:', err)
    } finally {
      isLoading.value = false
    }
  }

  const setView = (view: 'kanban' | 'calendar' | 'gantt' | 'todos') => {
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
    deleteSubTask,
    addComment,
    createBoard,
    updateBoard,
    deleteBoard,
    setView,
    selectTask
  }
})
