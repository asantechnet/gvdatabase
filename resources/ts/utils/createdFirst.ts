export interface CreatedRow {
  id: number | string
}

/**
 * Put a just-created row first in the current table page. Later loads replace
 * the list with the server order, so this ordering only applies while the user
 * continues on the page where the record was created.
 */
export function moveCreatedRowFirst<T extends CreatedRow>(rows: T[], created: T): T[] {
  const index = rows.findIndex(row => row.id === created.id)

  if (index >= 0)
    rows.splice(index, 1)
  rows.unshift(created)

  return rows
}

/** Put an existing row first after a create-triggered server reload. */
export function moveRowIdFirst<T extends CreatedRow>(rows: T[], id: number | string): T[] {
  const index = rows.findIndex(row => row.id === id)

  if (index > 0)
    rows.unshift(...rows.splice(index, 1))

  return rows
}
