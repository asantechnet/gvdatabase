export interface KanbanMember {
  id: string
  name: string
  avatar?: string | null
  img?: string | null
  email?: string
}

export type KanbanId = string | number

export interface KanbanItem {
  id: KanbanId
  title: string
  dueDate: string
  labels: string[]
  labelColors?: Record<string, string>
  members: KanbanMember[]
  attachments?: number
  attachmentsCount?: number
  commentsCount: number
  image?: string
  comments?: string
  progress?: number
}

export interface KanbanBoard { id: KanbanId; title: string; itemsIds: KanbanId[] }
export interface KanbanData { boards: KanbanBoard[]; items: KanbanItem[] }
export interface AddNewKanbanItem { itemTitle: string; boardName: string; boardId: KanbanId }
export interface EditKanbanItem { item: KanbanItem; boardId: KanbanId; boardName: string }
export interface RenameKanbanBoard { oldName: string; newName: string; boardId: KanbanId }
export interface KanbanState { boardId: KanbanId; ids: KanbanId[] }
