import { type Component, type PropType, type VNode, cloneVNode, computed, defineComponent, h, isVNode, onBeforeUnmount, onMounted, onUpdated, ref, watch } from 'vue'
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
import TableScrollAffordance from '../TableScrollAffordance.vue'
import TablePaginationFooter from './TablePaginationFooter.vue'
import AuditTrailButton from '../audit/AuditTrailButton.vue'
import AuditTrailCell from '../audit/AuditTrailCell.vue'
import AuditTrailLegend from '../audit/AuditTrailLegend.vue'
import { useAuditTrail } from '@/composables/useAuditTrail'
import type { AuditTrailRow } from '@/api/auditTrail'
import { auditTrailRowClass, auditTrailState, changedFieldForColumn } from '@/utils/auditTrail'

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

const isActionNode = (node: VNode) => /^(?:VBtn|IconBtn|FullDetailsButton|CrmRelationshipDetails|VMenu)$/i.test(componentName(node))

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
    // Explicit decision fields for the phone card: stage, service, assignee,
    // amount, and so on. Never derive this from every column; a card with all
    // columns is just a vertical table.
    mobileFields: {
      type: Array as PropType<string[]>,
      default: () => [],
    },
    // A CRM record is a compact profile, so callers can show every field on
    // the phone card immediately instead of making the user open each card.
    mobileExpandAll: Boolean,
    // Preserve an in-cell VMenu on phone cards for pages that already expose
    // one clean actions menu on desktop.
    mobileActionsAsMenu: Boolean,
    // Keep table headers visible while a page fetches rows and fill the body
    // with row-shaped placeholders instead of replacing the whole table.
    skeletonRows: {
      type: Number,
      default: 0,
    },

    // These opt-in hooks keep the generic table useful for operational grids
    // without forcing sticky cells onto short or form-oriented tables.
    stickyFirstColumn: Boolean,
    stickyLastColumn: Boolean,
    // Sticky actions default on when the last column is an action column, and
    // the identifier column stays pinned with them unless explicitly opted out.
    stickyKeyColumn: {
      type: Boolean as PropType<boolean | undefined>,
      default: undefined,
    },
    stickyActionColumn: {
      type: Boolean as PropType<boolean | undefined>,
      default: undefined,
    },

    // One accessible footer with page size, range and first/prev/next/last.
    // Opt-in so pages that already render their own footer are not doubled.
    pagination: Boolean,
    pageSizes: {
      type: Array as PropType<number[]>,
      default: () => [10, 25, 50, 100],
    },
    showScrollAffordance: {
      type: Boolean,
      default: true,
    },
    // Audit colouring is enabled in Settings → Operations. A register that
    // cannot accommodate the rail or row action can opt out here.
    auditTrail: {
      type: Boolean,
      default: true,
    },
  },
  setup(props, { attrs, slots }) {
    const { t } = useI18n({ useScope: 'global' })

    const headerColumns = computed<DataTableColumn[]>(() => Array.isArray(attrs.headers) ? attrs.headers as DataTableColumn[] : [])
    const lastColumnIsAction = computed(() => {
      const columns = headerColumns.value

      return columns.length > 0 && isActionColumn(columns[columns.length - 1])
    })
    const stickyActions = computed(() => props.stickyActionColumn ?? lastColumnIsAction.value)
    const stickyKey = computed(() => props.stickyKeyColumn ?? stickyActions.value)

    const tableItems = computed<Record<string, any>[]>(() => Array.isArray(attrs.items)
      ? attrs.items as Record<string, any>[]
      : [])
    const auditTrailEnabled = computed(() => props.auditTrail)
    const audit = useAuditTrail(() => tableItems.value, { enabled: auditTrailEnabled })
    const auditRowId = (item: Record<string, any>) => Number(getValue(item, String(attrs.itemValue || 'id')))
    const trailForRow = (item: Record<string, any>) => audit.trailFor(auditRowId(item))
    const activeTrails = computed(() => tableItems.value
      .map(item => trailForRow(item))
      .filter((trail): trail is AuditTrailRow => Boolean(trail)))
    const showAuditTrail = computed(() => props.auditTrail
      && audit.enabled.value
      && audit.allowed.value
      && activeTrails.value.length > 0)
    const auditLegendStates = computed(() => [...new Set(activeTrails.value.map(trail => auditTrailState(trail.event)))]
      .filter(state => state !== 'neutral'))
    // Append the audit action only when the current page has at least one
    // audited row, so an unaudited register never gains an empty column.
    const renderedHeaders = computed(() => {
      const headers = headerColumns.value

      if (!showAuditTrail.value || !headers.length || headers.some(column => column.key === 'audit_trail'))
        return attrs.headers

      const column: DataTableColumn = {
        key: 'audit_trail',
        title: '',
        align: 'end',
        width: 56,
        sortable: false,
        class: 'audit-trail-column',
      }
      const last = headers[headers.length - 1]

      return last && isActionColumn(last)
        ? [...headers.slice(0, -1), column, last]
        : [...headers, column]
    })
    const auditRowProps = (data: any) => {
      const base = typeof attrs.rowProps === 'function' ? attrs.rowProps(data) : (attrs.rowProps || {})
      const trail = trailForRow(data?.item)

      if (!trail)
        return base

      const state = auditTrailState(trail.event)

      return {
        ...base,
        class: [base?.class, auditTrailRowClass(trail)],
        'data-audit-state': state,
      }
    }

    const tableRoot = ref<HTMLElement | null>(null)
    const hasHorizontalOverflow = ref(false)
    const canScrollStart = ref(false)
    const canScrollEnd = ref(false)
    let scrollWrapper: HTMLElement | null = null
    let overflowObserver: ResizeObserver | null = null

    const updateOverflow = () => {
      if (!scrollWrapper)
        return

      const maxScroll = Math.max(0, scrollWrapper.scrollWidth - scrollWrapper.clientWidth)
      const position = Math.abs(scrollWrapper.scrollLeft)
      const overflow = maxScroll > 1
      const atStart = position <= 1
      const atEnd = maxScroll - position <= 1

      if (hasHorizontalOverflow.value !== overflow)
        hasHorizontalOverflow.value = overflow
      if (canScrollStart.value !== !atStart)
        canScrollStart.value = !atStart
      if (canScrollEnd.value !== !atEnd)
        canScrollEnd.value = !atEnd
    }

    const scrollColumns = (direction: 1 | -1) => {
      if (!scrollWrapper)
        return

      const rtl = typeof getComputedStyle === 'function' && getComputedStyle(scrollWrapper).direction === 'rtl'
      const amount = Math.max(240, scrollWrapper.clientWidth * 0.6)

      scrollWrapper.scrollBy({ left: direction * amount * (rtl ? -1 : 1), behavior: 'smooth' })
    }

    onMounted(() => {
      scrollWrapper = (tableRoot.value?.querySelector('.v-table__wrapper') as HTMLElement | null) ?? null

      if (!scrollWrapper)
        return

      scrollWrapper.addEventListener('scroll', updateOverflow, { passive: true })

      if (typeof ResizeObserver !== 'undefined') {
        overflowObserver = new ResizeObserver(updateOverflow)
        overflowObserver.observe(scrollWrapper)
        const table = scrollWrapper.querySelector('table')

        if (table)
          overflowObserver.observe(table)
      }

      updateOverflow()
    })
    onUpdated(updateOverflow)
    onBeforeUnmount(() => {
      scrollWrapper?.removeEventListener('scroll', updateOverflow)
      overflowObserver?.disconnect()
      overflowObserver = null
      scrollWrapper = null
    })

    const isNarrowViewport = useMediaQuery('(max-width: 959px)')
    const mobile = computed(() => props.mobileCards && isNarrowViewport.value)
    const expandedCards = ref(new Set<string>())
    const actionSheetOpen = ref(false)
    const actionSheetState = ref<{ item: Record<string, any>; index: number; column: DataTableColumn; body: TableBodySlotProps } | null>(null)

    // The action sheet is a full-width overlay with a dark scrim. Vuetify keeps
    // the last sheet mounted, so a stale open state after a refresh, a page
    // change or a filter that removes the row would leave the scrim covering
    // the whole page. Reset it whenever the rows change and on unmount.
    watch(() => attrs.items, () => {
      actionSheetOpen.value = false
      actionSheetState.value = null
    })
    onBeforeUnmount(() => {
      actionSheetOpen.value = false
      actionSheetState.value = null
    })

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
      const trail = trailForRow(item)

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

      // Decision fields are visible without expanding the card; everything
      // else stays behind the disclosure so the phone view stays readable.
      const configuredFields = props.mobileFields
        .map(field => columns.find(column => column.key === field))
        .filter((column): column is DataTableColumn => Boolean(column))
        .filter(column => column !== titleColumn && !isActionColumn(column))

      const detailFields = columns.filter(column =>
        column !== titleColumn
        && !isActionColumn(column)
        && !configuredFields.includes(column))

      const hasExpandedRow = Boolean(slots['expanded-row']) || body.columns.some(column => column.key === 'data-table-expand')
      const expandable = hasExpandedRow || detailFields.length > 0
      const isExpanded = expandable
        && (props.mobileExpandAll || expandedCards.value.has(key) || Boolean(hasExpandedRow && internalItem && body.isExpanded(internalItem)))

      const actionProps: DataTableSlotProps | null = actionColumn
        ? {
            ...titleProps,
            value: getValue(item, actionColumn.key),
          }
        : null
      const mobileMenuAction = props.mobileActionsAsMenu && actionColumn && actionProps
        ? collectActionNodes(slots[`item.${actionColumn.key}`]?.(actionProps as any)).find(node => /VMenu/i.test(componentName(node)))
        : undefined
      const mobileActionNodes = actionColumn && actionProps
        ? collectActionNodes(slots[`item.${actionColumn.key}`]?.(actionProps as any))
        : []
      // Keep a direct action (such as Print) visible beside the menu trigger.
      // The menu still owns the remaining actions, so mobile does not render
      // a second copy of the same action list. FullDetailsButton is also a
      // direct action: filtering it out here made View Details disappear on
      // rows that also have an overflow menu.
      const mobileInlineActions = mobileMenuAction
        ? mobileActionNodes.filter(node => /^(?:VBtn|IconBtn|FullDetailsButton|CrmRelationshipDetails)$/i.test(componentName(node)))
        : []

      const toggleCard = () => {
        if (!expandable)
          return

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
        if (!actionColumn || !mobileActionNodes.length)
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
        class: [
          'responsive-data-table__card',
          isExpanded && 'responsive-data-table__card--expanded',
          trail && auditTrailRowClass(trail),
        ],
        'data-audit-state': trail ? auditTrailState(trail.event) : undefined,
        variant: 'outlined',
        elevation: 0,
      }, {
        default: () => [
          h('div', {
            'class': ['responsive-data-table__card-toggle', !expandable && 'responsive-data-table__card-toggle--static'],
            'role': expandable ? 'button' : undefined,
            'tabindex': expandable ? 0 : undefined,
            'aria-expanded': expandable ? isExpanded : undefined,
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
                mobileMenuAction
                  ? [
                      ...mobileInlineActions.map((node, actionIndex) => cloneVNode(node, { key: `inline-${key}-${actionIndex}` })),
                      cloneVNode(mobileMenuAction, { key: `menu-${key}` }),
                    ]
                  : actionColumn && mobileActionNodes.length
                  ? h(VBtn, {
                    'icon': true,
                    'size': 'small',
                    'variant': 'text',
                    'aria-label': `${localized('common.messages.actions', 'Actions')} for ${String(titleProps.value || '')}`,
                    'onClick': openActions,
                  }, { default: () => h(VIcon, { icon: 'tabler-dots-vertical' }) })
                  : !actionColumn
                    ? h(VBtn, {
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
                    }, { default: () => h(VIcon, { icon: isExpanded ? 'tabler-chevron-up' : 'tabler-chevron-down' }) })
                    : undefined,
              ]),
            }),
          ]),
          configuredFields.length
            ? h(VCardText, { class: 'responsive-data-table__card-fields py-0' }, {
              default: () => configuredFields.map(column => renderField(column, item, index, body)),
            })
            : undefined,
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
                  ...detailFields.map(column => renderField(column, item, index, body)),
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

    const skeletonColumns = (columns: DataTableColumn[]) => columns.filter(column => !isSpecialColumn(column))

    const skeletonPlaceholder = (secondary = false) => h('span', {
      class: [
        'responsive-data-table__skeleton-bar',
        secondary && 'responsive-data-table__skeleton-bar--secondary',
      ],
    })

    const renderDesktopSkeleton = (body: TableBodySlotProps) => Array.from(
      { length: Math.max(1, props.skeletonRows) },
      (_, rowIndex) => h('tr', { class: 'responsive-data-table__skeleton-row', key: `skeleton-${rowIndex}` },
        skeletonColumns(body.columns).map(column => h('td', { key: column.key }, [
          skeletonPlaceholder(),
        ])),
      ),
    )

    const renderMobileSkeleton = (body: TableBodySlotProps) => h('tr', { class: 'responsive-data-table__mobile-row' }, [
      h('td', { colspan: body.columns.length }, [
        h('div', { class: 'responsive-data-table__cards d-flex flex-column ga-4' }, Array.from(
          { length: Math.max(1, props.skeletonRows) },
          (_, rowIndex) => h(VCard, {
            key: `mobile-skeleton-${rowIndex}`,
            class: 'responsive-data-table__card responsive-data-table__skeleton-card',
            variant: 'outlined',
            elevation: 0,
          }, {
            default: () => h(VCardText, { class: 'responsive-data-table__skeleton-card-content' }, [
              skeletonPlaceholder(),
              skeletonPlaceholder(true),
              skeletonPlaceholder(),
            ]),
          }),
        )),
      ]),
    ])

    const renderDesktopBody = (body: TableBodySlotProps) => h(VDataTableRows as any, {
      items: body.groupedItems || [],
      loading: attrs.loading,
      loadingText: attrs.loadingText,
      noDataText: attrs.noDataText,
      hideNoData: attrs.hideNoData,
      rowProps: activeTrails.value.length ? auditRowProps : attrs.rowProps,
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

    const closeActionSheet = () => {
      actionSheetOpen.value = false
      actionSheetState.value = null
    }

    const renderMobileAction = (node: VNode, index: number) => {
      const name = componentName(node)
      if (/FullDetailsButton/i.test(name)) {
        return cloneVNode(node, {
          key: `action-${index}`,
          mobileAction: true,
        })
      }

      if (/VMenu/i.test(name))
        return cloneVNode(node, { key: `action-${index}` })

      if (/CrmRelationshipDetails/i.test(name))
        return cloneVNode(node, { key: `action-${index}`, mobileAction: true })

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
      const tableSlots: Record<string, any> = { ...slots }

      if (showAuditTrail.value) {
        // Only wrap columns that actually changed in the current page. Cells
        // without a mapped change keep their original slot and formatting.
        for (const column of headerColumns.value) {
          if (isSpecialColumn(column) || column.key === 'audit_trail')
            continue
          if (!activeTrails.value.some(trail => changedFieldForColumn(trail, column.key)))
            continue

          const original = slots[`item.${column.key}`]

          tableSlots[`item.${column.key}`] = (slotProps: DataTableSlotProps) => {
            const content = original
              ? original(slotProps as any)
              : String(getValue(slotProps.item, column.key) ?? '—')
            const trail = trailForRow(slotProps.item)
            const change = changedFieldForColumn(trail, column.key)

            if (!change)
              return content

            return h(AuditTrailCell as any, { change }, { default: () => content })
          }
        }

        tableSlots['item.audit_trail'] = (slotProps: DataTableSlotProps) => {
          const trail = trailForRow(slotProps.item)

          if (!trail)
            return null

          return h(AuditTrailButton as any, {
            trail,
            subject: String((slotProps.item as any)?.name ?? (slotProps.item as any)?.title ?? ''),
          })
        }
      }

      tableSlots.body = (body: TableBodySlotProps) => attrs.loading && props.skeletonRows > 0
        ? (mobile.value ? renderMobileSkeleton(body) : renderDesktopSkeleton(body))
        : slots.body
          ? slots.body(body as any)
          : mobile.value ? renderMobileBody(body) : renderDesktopBody(body)

      if (props.pagination) {
        tableSlots.footer = (footer: any) => {
          if (!footer || !footer.itemsLength)
            return null

          return h(TablePaginationFooter, {
            page: footer.page,
            itemsPerPage: footer.itemsPerPage,
            pageCount: footer.pageCount,
            itemsLength: footer.itemsLength,
            pageSizes: props.pageSizes,
            'onUpdate:page': (value: number) => {
              if (typeof footer.setPage === 'function')
                footer.setPage(value)
              else
                footer.updateOptions?.({ page: value })
            },
            'onUpdate:itemsPerPage': (value: number) => {
              if (typeof footer.setItemsPerPage === 'function')
                footer.setItemsPerPage(value)
              else
                footer.updateOptions?.({ itemsPerPage: value })
            },
          })
        }
      }

      // The row-shaped loader below replaces Vuetify's default progress
      // indicator. Do not pass `loading` through in that mode, otherwise the
      // table renders both loading treatments at once.
      const tableAttrs: Record<string, any> = { ...attrs, headers: renderedHeaders.value }
      if (activeTrails.value.length)
        tableAttrs.rowProps = auditRowProps
      if (props.skeletonRows > 0)
        delete tableAttrs.loading

      const table = h(TableComponent, {
        ...tableAttrs,
        class: [
          'responsive-data-table',
          mobile.value && 'responsive-data-table--mobile-cards',
          (props.stickyFirstColumn || stickyKey.value) && 'responsive-data-table--sticky-first',
          (props.stickyLastColumn || stickyActions.value) && 'responsive-data-table--sticky-last',
          attrs.class,
        ],
        // Card conversion is handled above. Keep Vuetify's own mobile mode
        // disabled so it does not inject its redundant "Sort by" control
        // beneath a responsive card table.
        mobileBreakpoint: 0,
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

      // Render the sheet only while it is needed. Keeping a closed sheet in
      // the tree is what lets a stale `actionSheetOpen` paint its scrim over
      // the page after navigation or a data refresh.
      const sheet = actionSheetOpen.value && actionState
        ? h(VBottomSheet, {
          'modelValue': true,
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
        : null

      return h('div', { class: 'responsive-data-table__root', ref: tableRoot }, [
        showAuditTrail.value
          ? h(AuditTrailLegend, { states: auditLegendStates.value })
          : null,
        table,
        props.showScrollAffordance && hasHorizontalOverflow.value
          ? h(TableScrollAffordance, {
            visible: true,
            canScrollStart: canScrollStart.value,
            canScrollEnd: canScrollEnd.value,
            onScrollStart: () => scrollColumns(-1),
            onScrollEnd: () => scrollColumns(1),
          })
          : null,
        sheet,
      ])
    }
  },
})

export const ResponsiveDataTable = createResponsiveDataTable(VDataTable, 'ResponsiveDataTable')
