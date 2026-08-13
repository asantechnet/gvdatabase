import { createMongoAbility } from '@casl/ability'

export type Actions = 'create' | 'read' | 'update' | 'delete' | 'manage'

// The server-provided permission identifier is the subject. Backend policies
// remain authoritative; CASL keeps the SPA routes and controls in sync.
export type Subjects = string

export interface Rule { action: Actions; subject: Subjects }

export const ability = createMongoAbility<[Actions, Subjects]>()
