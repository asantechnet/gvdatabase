import { type Component, type VNode, cloneVNode, computed, defineComponent, h, isVNode, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  VBottomSheet,
  VBtn,
  VCard,
  VCardItem,
  VCardText,
  VCardTitle,
  VCheckboxBtn,
  VDataTable,
  VDataTableRows,
  VIcon,
} from 'vuetify/components'

interface DataTableColumn {
  key: string
  title?: string
  align?: 'start' | 'center' | 'end'
  [key: string]: unknown
}

interface DataTableSlotProps {
  index: number
  item: Record<string, any>
  internalItem?: any
  columns: DataTableColumn[]
  value?: any
  isSelected?: (items: any[]) => boolean
  toggleSelect?: (item: any) => void
  isExpanded?: (item: any) => boolean
  toggleExpand?: (item: any) => void
}

interface TableBodySlotProps {
  items: Record<string, any>[]
  internalItems?: any[]
  groupedItems?: any[]
  columns: DataTableColumn[]
  isSelected: (items: any[]) => boolean
  toggleSelect: (item: any) => void
  isExpanded: (item: any) => boolean
  toggleExpand: (item: any) => void
  [key: string]: any
}

const getValue = (item: Record<string, any>, key: string) => key.split('.').reduce((value, part) => value?.[part], item)

const isSpecialColumn = (column: DataTableColumn) => [
  'data-table-select',
  'data-table-expand',
  'data-table-group',
].includes(column.key)

const isActionColumn = (column: DataTableColumn) => {
  const normalized = `${column.key} ${column.title || ''}`.toLowerCase()

  return normalized.includes('action') || normalized === 'more' || normalized === 'options'
}

const flattenNodes = (nodes: unknown): VNode[] => {
  if (!nodes)
    return []

  return (Array.isArray(nodes) ? nodes : [nodes]).flatMap(node => {
    if (!isVNode(node))
      return []

    return node.type === Symbol.for('v-fgt') && Array.isArray(node.children)
      ? flattenNodes(node.children)
      : [node]
  })
}

const componentName = (node: VNode) => {
  if (typeof node.type === 'string')
    return node.type

  const component = node.type as { name?: string; __name?: string }

  return component.name || component.__name || ''
}

const nodeChildren = (node: VNode): VNode[] => {
  const children = node.children

  if (Array.isArray(children))
    return flattenNodes(children as VNode[])
  if (children && typeof children === 'object' && 'default' in children) {
    const defaultSlot = (children as { default?: () => VNode | VNode[] }).default

    return defaultSlot ? flattenNodes(defaultSlot()) : []
  }

  return []
}

const isActionNode = (node: VNode) => /^(?:VBtn|IconBtn|FullDetailsButton)$/i.test(componentName(node))

const collectActionNodes = (nodes: unknown): VNode[] => flattenNodes(nodes).flatMap(node => {
  if (isActionNode(node))
    return [node]

  // Action slots commonly use a plain layout element to group several buttons.
  // Unwrap that element while leaving arbitrary components alone.
  return typeof node.type === 'string' ? collectActionNodes(nodeChildren(node)) : []
})

const textFromNodes = (nodes: VNode[]): string => nodes.flatMap(node => {
  if (typeof node.children === 'string')
    return [node.children]

  return textFromNodes(nodeChildren(node))
}).join(' ').replace(/\s+/g, ' ').trim()

const containsDataTable = (nodes: unknown): boolean => flattenNodes(nodes).some(node => {
  if (/DataTable|VTable/i.test(componentName(node)))
    return true

  return typeof node.type === 'string' && containsDataTable(nodeChildren(node))
})

export const createResponsiveDataTable = (TableComponent: Component, tableComponentName: string) => defineComponent({
  name: tableComponentName,
  inheritAttrs: false,
  props: {
    // Vuexy/Vuetify uses the same display thresholds. `sm` keeps tablet layouts
    // table-oriented while making the transformation apply to narrow screens.
    mobileBreakpoint: {
      type: [Number, String],
      default: 'sm',
    },
    mobileTitleKey: String,
    mobileActionKey: String,
    // Opt in when a data-dense operational table should become expandable
    // cards on phones instead of retaining a scrollable table grid.
    mobileCards: Boolean,

    // These opt-in hooks keep the generic table useful for operational grids
    // without forcing sticky cells onto short or form-oriented tables.
    stickyFirstColumn: Boolean,
    stickyLastColumn: Boolean,
  },
  setup(props, { attrs, slots }) {
    const { t } = useI18n({ useScope: 'global' })

    const isNarrowViewport = useMediaQuery('(max-width: 959px)')
    const mobile = computed(() => props.mobileCards && isNarrowViewport.value)
    const expandedCards = ref(new Set<string>())
    const actionSheetOpen = ref(false)
    const actionSheetState = ref<{ item: Record<string, any>; index: number; column: DataTableColumn; body: TableBodySlotProps } | null>(null)

    const localized = (key: string, fallback: string) => {
      const value = t(key)

      return value === key || value.startsWith('[missing:') ? fallback : value
    }

    const itemKey = (item: Record<string, any>, index: number) => String(
      getValue(item, String(attrs.itemValue || 'id')) ?? index,
    )

    const chooseColumns = (columns: DataTableColumn[]) => columns.filter(column => !isSpecialColumn(column))

    const renderField = (column: DataTableColumn, item: Record<string, any>, index: number, body: TableBodySlotProps) => {
      const internalItem = body.internalItems?.find(candidate => candidate.raw === item)

      const fieldProps: DataTableSlotProps = {
        index,
        item,
        internalItem,
        columns: body.columns,
        value: getValue(item, column.key),
        isSelected: body.isSelected,
        toggleSelect: body.toggleSelect,
        isExpanded: body.isExpanded,
        toggleExpand: body.toggleExpand,
      } as DataTableSlotProps

      const content = slots[`item.${column.key}`]?.(fieldProps as any) ?? String(fieldProps.value ?? '—')

      const isBlockContent = containsDataTable(content)

      return h('div', {
        class: ['responsive-data-table__field', isBlockContent && 'responsive-data-table__field--block'],
        key: column.key,
      }, [
        h('div', { class: 'responsive-data-table__field-label text-caption text-medium-emphasis' }, column.title || column.key),
        h('div', { class: 'responsive-data-table__field-value text-body-1' }, content),
      ])
    }

    const renderCard = (item: Record<string, any>, index: number, body: TableBodySlotProps) => {
      const columns = chooseColumns(body.columns)

      const titleColumn = columns.find(column => column.key === props.mobileTitleKey)
        || columns.find(column => ['name', 'title', 'number', 'order_number', 'code', 'email'].includes(column.key))
        || columns[0]

      const actionColumn = body.columns.find(column => column.key === props.mobileActionKey)
        || body.columns.find(isActionColumn)

      const internalItem = body.internalItems?.find(candidate => candidate.raw === item)
      const key = itemKey(item, index)

      const titleProps: DataTableSlotProps = {
        index,
        item,
        internalItem,
        columns: body.columns,
        value: titleColumn ? getValue(item, titleColumn.key) : '',
        isSelected: body.isSelected,
        toggleSelect: body.toggleSelect,
        isExpanded: body.isExpanded,
        toggleExpand: body.toggleExpand,
      } as DataTableSlotProps

      const titleContent = titleColumn
        ? slots[`item.${titleColumn.key}`]?.(titleProps as any) ?? String(titleProps.value ?? '—')
        : '—'

      const hasExpandedRow = Boolean(slots['expanded-row']) || body.columns.some(column => column.key === 'data-table-expand')
      const isExpanded = expandedCards.value.has(key) || Boolean(hasExpandedRow && internalItem && body.isExpanded(internalItem))

      const toggleCard = () => {
        if (hasExpandedRow && internalItem) {
          body.toggleExpand(internalItem)

          return
        }

        const next = new Set(expandedCards.value)
        if (next.has(key))
          next.delete(key)
        else next.add(key)
        expandedCards.value = next
      }

      const openActions = (event: Event) => {
        event.stopPropagation()
        if (!actionColumn)
          return
        actionSheetState.value = { item, index, column: actionColumn, body }
        actionSheetOpen.value = true
      }

      const hasSelection = body.columns.some(column => column.key === 'data-table-select')

      const selectSlot = (hasSelection && slots['item.data-table-select']?.({
        ...titleProps,
        props: {
          modelValue: internalItem ? body.isSelected([internalItem]) : false,
          onClick: (event: Event) => {
            event.stopPropagation()
            if (internalItem)
              body.toggleSelect(internalItem)
          },
        },
      } as any))
        || (hasSelection && h(VCheckboxBtn as any, {
          density: attrs.density || 'compact',
          modelValue: internalItem ? body.isSelected([internalItem]) : false,
          onClick: (event: Event) => {
            event.stopPropagation()
            if (internalItem)
              body.toggleSelect(internalItem)
          },
        } as any))

      return h(VCard, {
        key,
        class: ['responsive-data-table__card', isExpanded && 'responsive-data-table__card--expanded'],
        variant: 'outlined',
        elevation: 0,
      }, {
        default: () => [
          h('div', {
            'class': 'responsive-data-table__card-toggle',
            'role': 'button',
            'tabindex': 0,
            'aria-expanded': isExpanded,
            'onClick': toggleCard,
            'onKeydown': (event: KeyboardEvent) => {
              if (event.target !== event.currentTarget || !['Enter', ' '].includes(event.key))
                return
              event.preventDefault()
              toggleCard()
            },
          }, [
            h(VCardItem, {
              class: 'responsive-data-table__card-header',
            }, {
              prepend: selectSlot ? () => selectSlot : undefined,
              default: () => h(VCardTitle, {
                class: 'responsive-data-table__card-title text-body-1 font-weight-medium',
              }, { default: () => titleContent }),
              append: () => h('div', { class: 'd-flex align-center' }, [
                actionColumn
                  ? h(VBtn, {
                    'icon': true,
                    'size': 'small',
                    'variant': 'text',
                    'aria-label': `${localized('common.messages.actions', 'Actions')} for ${String(titleProps.value || '')}`,
                    'onClick': openActions,
                  }, { default: () => h(VIcon, { icon: 'tabler-dots-vertical' }) })
                  : h(VBtn, {
                    'icon': true,
                    'size': 'small',
                    'variant': 'text',
                    'aria-expanded': isExpanded,
                    'aria-label': isExpanded
                      ? localized('$vuetify.close', 'Collapse details')
                      : localized('$vuetify.open', 'Expand details'),
                    'onClick': (event: Event) => {
                      event.stopPropagation()
                      toggleCard()
                    },
                  }, { default: () => h(VIcon, { icon: isExpanded ? 'tabler-chevron-up' : 'tabler-chevron-down' }) }),
              ]),
            }),
          ]),
          ...(isExpanded
            ? [h(VCardText, { class: 'responsive-data-table__card-details pt-0' }, {
              default: () => {
                const expandedContent = slots['expanded-row'] && internalItem
                  ? h('div', { class: 'responsive-data-table__expanded-content' }, slots['expanded-row']({
                    item,
                    index,
                    columns: body.columns,
                    isExpanded: body.isExpanded,
                    toggleExpand: body.toggleExpand,
                  } as any))

                  : undefined

                return [
                  ...columns.filter(column => column !== titleColumn && !isActionColumn(column)).map(column => renderField(column, item, index, body)),
                  ...(expandedContent ? [expandedContent] : []),
                ]
              },
            })]
            : []),
        ],
      })
    }

    const renderMobileBody = (body: TableBodySlotProps) => {
      const items = body.items || []

      if (!items.length) {
        const emptySlot = attrs.loading ? slots.loading?.() : slots['no-data']?.()

        return h('tr', { class: 'responsive-data-table__mobile-state' }, [
          h('td', { colspan: body.columns.length }, emptySlot || (attrs.loading
            ? String(attrs.loadingText || localized('$vuetify.loading', 'Loading…'))
            : String(attrs.noDataText || localized('$vuetify.noDataText', 'No data available')))),
        ])
      }

      return h('tr', { class: 'responsive-data-table__mobile-row' }, [
        h('td', { colspan: body.columns.length }, [
          h('div', { class: 'responsive-data-table__cards d-flex flex-column ga-4' }, items.map((item, index) => renderCard(item, index, body))),
        ]),
      ])
    }

    const renderDesktopBody = (body: TableBodySlotProps) => h(VDataTableRows as any, {
      items: body.groupedItems || [],
      loading: attrs.loading,
      loadingText: attrs.loadingText,
      noDataText: attrs.noDataText,
      hideNoData: attrs.hideNoData,
      rowProps: attrs.rowProps,
      cellProps: attrs.cellProps,
      density: attrs.density,
      mobileBreakpoint: props.mobileBreakpoint,
      collapseIcon: attrs.collapseIcon,
      expandIcon: attrs.expandIcon,
    } as any, slots as any)

    const actionLabel = (node: VNode) => {
      const nodeProps = (node.props || {}) as Record<string, unknown>
      const explicitLabel = nodeProps['aria-label'] || nodeProps.ariaLabel || nodeProps.title
      if (typeof explicitLabel === 'string')
        return explicitLabel

      const slotLabel = textFromNodes(nodeChildren(node))
      if (slotLabel)
        return slotLabel

      const icon = String(nodeProps.icon || '')
      if (icon.includes('printer'))
        return localized('common.actions.print', 'Print')
      if (icon.includes('file-plus') || icon.includes('plus'))
        return localized('common.actions.create', 'Create')
      if (icon.includes('edit') || icon.includes('pencil'))
        return localized('common.actions.edit', 'Edit')
      if (icon.includes('trash') || icon.includes('delete'))
        return localized('common.actions.delete', 'Delete')
      if (icon.includes('circle-x') || icon.includes('ban'))
        return localized('common.actions.void', 'Void')
      if (icon.includes('eye'))
        return localized('common.actions.viewFullDetails', 'View details')

      return localized('common.messages.actions', 'Action')
    }

    const closeActionSheet = () => { actionSheetOpen.value = false }

    const renderMobileAction = (node: VNode, index: number) => {
      const name = componentName(node)
      if (/FullDetailsButton/i.test(name)) {
        return cloneVNode(node, {
          key: `action-${index}`,
          mobileAction: true,
        })
      }

      const nodeProps = { ...((node.props || {}) as Record<string, any>) }
      const icon = typeof nodeProps.icon === 'string' ? nodeProps.icon : undefined
      const originalClick = nodeProps.onClick

      delete nodeProps.icon

      return h(VBtn, {
        ...nodeProps,
        key: `action-${index}`,
        block: true,
        class: ['responsive-data-table__action-button', nodeProps.class],
        variant: 'text',
        onClick: (event: Event) => {
          if (Array.isArray(originalClick))
            originalClick.forEach(handler => handler?.(event))
          else originalClick?.(event)
          closeActionSheet()
        },
      }, {
        default: () => [
          ...(icon ? [h(VIcon, { icon })] : []),
          h('span', actionLabel(node)),
        ],
      })
    }

    return () => {
      const tableSlots = {
        ...slots,
        body: (body: TableBodySlotProps) => slots.body
          ? slots.body(body as any)
          : mobile.value ? renderMobileBody(body) : renderDesktopBody(body),
      }

      const table = h(TableComponent, {
        ...attrs,
        class: [
          'responsive-data-table',
          mobile.value && 'responsive-data-table--mobile-cards',
          props.stickyFirstColumn && 'responsive-data-table--sticky-first',
          props.stickyLastColumn && 'responsive-data-table--sticky-last',
          attrs.class,
        ],
        mobileBreakpoint: props.mobileBreakpoint,
      }, tableSlots)

      const actionState = actionSheetState.value

      const actionContent = actionState && slots[`item.${actionState.column.key}`]?.({
        index: actionState.index,
        item: actionState.item,
        internalItem: actionState.body.internalItems?.find(candidate => candidate.raw === actionState.item),
        columns: actionState.body.columns,
        value: getValue(actionState.item, actionState.column.key),
      } as any)

      const actions = collectActionNodes(actionContent || undefined)

      const sheet = h(VBottomSheet, {
        'modelValue': actionSheetOpen.value,
        'inset': true,
        'onUpdate:modelValue': (value: boolean) => { actionSheetOpen.value = value },
      }, {
        default: () => h(VCard, { class: 'responsive-data-table__action-sheet' }, {
          default: () => [
            h(VCardItem, {}, {
              default: () => h(VCardTitle, { class: 'text-body-1 font-weight-medium' }, { default: () => localized('common.messages.actions', 'Actions') }),
            }),
            h(VCardText, {
              class: 'responsive-data-table__action-content pt-0',
            }, { default: () => actions.map(renderMobileAction) }),
          ],
        }),
      })

      return h('div', { class: 'responsive-data-table__root' }, [table, sheet])
    }
  },
})

export const ResponsiveDataTable = createResponsiveDataTable(VDataTable, 'ResponsiveDataTable')
