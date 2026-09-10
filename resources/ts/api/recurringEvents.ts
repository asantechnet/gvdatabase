import api from './axios'

export type RecurringEventType = 'income' | 'expense' | 'task'
export type RecurringFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly'
export interface RecurringOccurrence { id: number; occurrence_date: string; status: 'pending' | 'notified' | 'completed' | 'skipped'; completed_at: string | null; skipped_at: string | null; result_type: string | null; result_id: number | null }
export interface RecurringEvent { id: number; name: string; description: string | null; event_type: RecurringEventType; status: 'active' | 'paused' | 'completed'; frequency: RecurringFrequency; interval: number; start_date: string; next_occurrence_date: string; last_occurrence_date: string | null; end_date: string | null; payload: Record<string, unknown>; owner?: { id: number; name: string }; occurrences?: RecurringOccurrence[] }
export type RecurringEventInput = Omit<RecurringEvent, 'id' | 'owner' | 'occurrences' | 'last_occurrence_date' | 'status'> & { status?: 'active' | 'paused' }

const data = <T>(response: { data: { data: T } }) => response.data.data

const RecurringEventsApi = {
  async all(): Promise<RecurringEvent[]> { return data(await api.get('/recurring-events')) },
  async get(id: number): Promise<RecurringEvent> { return data(await api.get(`/recurring-events/${id}`)) },
  async create(input: RecurringEventInput): Promise<RecurringEvent> { return data(await api.post('/recurring-events', input)) },
  async update(id: number, input: Partial<RecurringEventInput>): Promise<RecurringEvent> { return data(await api.put(`/recurring-events/${id}`, input)) },
  async remove(id: number): Promise<void> { await api.delete(`/recurring-events/${id}`) },
  async pause(id: number): Promise<RecurringEvent> { return data(await api.post(`/recurring-events/${id}/pause`)) },
  async resume(id: number): Promise<RecurringEvent> { return data(await api.post(`/recurring-events/${id}/resume`)) },
  async skip(occurrenceId: number): Promise<RecurringOccurrence> { return data(await api.post(`/recurring-event-occurrences/${occurrenceId}/skip`)) },
  async execute(occurrenceId: number): Promise<RecurringOccurrence> { return data(await api.post(`/recurring-event-occurrences/${occurrenceId}/execute`)) },
}

export default RecurringEventsApi
