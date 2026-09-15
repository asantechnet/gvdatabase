<script setup lang="ts">
import { Placeholder } from '@tiptap/extension-placeholder'
import { Link } from '@tiptap/extension-link'
import { TextAlign } from '@tiptap/extension-text-align'
import { TextStyle } from '@tiptap/extension-text-style'
import { Underline } from '@tiptap/extension-underline'
import { StarterKit } from '@tiptap/starter-kit'
import type { Editor } from '@tiptap/core'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import type { PlaceholderGroup } from '@/api/contractPlaceholders'
import { ContractPlaceholderMark, type InsertContractPlaceholderOptions } from '@/extensions/contract/ContractPlaceholderMark'
import { ParagraphDirection } from '@/extensions/contract/ParagraphDirection'

const props = withDefaults(defineProps<{
  modelValue: string
  placeholderGroups?: PlaceholderGroup[]
  disabled?: boolean
  minHeight?: string

  /** Policy clauses share the editor, but retain their own visual identity. */
  variant?: 'contract' | 'policy'
}>(), {
  placeholderGroups: () => [],
  disabled: false,
  minHeight: '420px',
  variant: 'contract',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'placeholder-inserted', key: string): void
}>()

const { t } = useI18n()

const tr = (key: string, values: Record<string, unknown> = {}) =>
  t(`pages.settings.policies.policies.contractTemplate.${key}`, values)

const defaultFontFamily = '__default_font_family__'
const defaultFontSize = '__default_font_size__'

const fontFamilies = computed(() => [
  { title: tr('editor.font'), value: defaultFontFamily },
  { title: 'Vazirmatn', value: 'Vazirmatn' },
  { title: 'Estedad', value: 'Estedad' },
  { title: 'Sahel', value: 'Sahel' },
  { title: 'Samim', value: 'Samim' },
  { title: 'Shabnam', value: 'Shabnam' },
  { title: 'Arial', value: 'Arial' },
  { title: 'Tahoma', value: 'Tahoma' },
  { title: 'Times New Roman', value: 'Times New Roman' },
  { title: 'Courier New', value: 'Courier New' },
])

const fontSizes = computed(() => [
  { title: tr('editor.size'), value: defaultFontSize },
  ...['11px', '12px', '14px', '16px', '18px', '20px', '24px', '28px'].map(value => ({ title: value, value })),
])

const toolbarState = ref({
  fontFamily: defaultFontFamily,
  fontSize: defaultFontSize,
  color: '#000000',
  direction: null as 'rtl' | 'ltr' | null,
})

const placeholderSearch = ref('')

const filteredPlaceholderGroups = computed(() => {
  const query = placeholderSearch.value.trim().toLocaleLowerCase()
  if (!query)
    return props.placeholderGroups

  return props.placeholderGroups
    .map(group => ({
      ...group,
      fields: group.fields.filter(field =>
        `${group.label} ${field.label} ${field.key}`.toLocaleLowerCase().includes(query),
      ),
    }))
    .filter(group => group.fields.length)
})

const normalizeColor = (color?: string | null) => {
  if (!color)
    return '#000000'

  const rgbMatch = color.match(/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/)
  if (!rgbMatch)
    return color.startsWith('#') ? color : '#000000'

  return `#${rgbMatch.slice(1).map(value => Number(value).toString(16).padStart(2, '0')).join('')}`
}

const normalizeFontFamily = (family?: string | null) => {
  const value = family?.split(',')[0]?.replace(/['"]/g, '').trim()

  return fontFamilies.value.some(font => font.value === value) ? value! : defaultFontFamily
}

const normalizeFontSize = (size?: string | null) =>
  fontSizes.value.some(fontSize => fontSize.value === size) ? size! : defaultFontSize

const syncToolbarState = (editorInstance: Editor) => {
  const textStyle = editorInstance.getAttributes('textStyle') as {
    fontFamily?: string
    fontSize?: string
    color?: string
  }

  const paragraph = editorInstance.getAttributes('paragraph') as { dir?: 'rtl' | 'ltr' }

  toolbarState.value = {
    fontFamily: normalizeFontFamily(textStyle.fontFamily),
    fontSize: normalizeFontSize(textStyle.fontSize),
    color: normalizeColor(textStyle.color),
    direction: paragraph.dir ?? null,
  }
}

// Extends the asset TextStyle mark with the typographic attributes legal
// documents need (family, size, color) without new dependencies.
const ContractTextStyle = TextStyle.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      fontFamily: {
        default: null,
        parseHTML: (element: HTMLElement) => element.style.fontFamily.replace(/['"]/g, ''),
        renderHTML: (attributes: Record<string, unknown>) => {
          if (!(attributes as { fontFamily?: string | null }).fontFamily)
            return {}

          return { style: `font-family: '${(attributes as { fontFamily: string }).fontFamily}'` }
        },
      },
      fontSize: {
        default: null,
        parseHTML: (element: HTMLElement) => element.style.fontSize,
        renderHTML: (attributes: Record<string, unknown>) => {
          if (!(attributes as { fontSize?: string | null }).fontSize)
            return {}

          return { style: `font-size:${(attributes as { fontSize: string }).fontSize}` }
        },
      },
      color: {
        default: null,
        parseHTML: (element: HTMLElement) => element.style.color,
        renderHTML: (attributes: Record<string, unknown>) => {
          if (!(attributes as { color?: string | null }).color)
            return {}

          return { style: `color:${(attributes as { color: string }).color}` }
        },
      },
    }
  },
})

const editor = useEditor({
  content: props.modelValue,
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
    ContractTextStyle,
    ContractPlaceholderMark,
    ParagraphDirection,
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Underline,
    Link.configure({ openOnClick: false }),
    Placeholder.configure({ placeholder: () => tr('editor.startWriting') }),
  ],
  onCreate: ({ editor: editorInstance }) => syncToolbarState(editorInstance),
  onUpdate: ({ editor: editorInstance }) => {
    syncToolbarState(editorInstance)
    emit('update:modelValue', editorInstance.getHTML())
  },
  onSelectionUpdate: ({ editor: editorInstance }) => syncToolbarState(editorInstance),
  onTransaction: ({ editor: editorInstance }) => syncToolbarState(editorInstance),
})

watch(() => props.modelValue, value => {
  const isSame = editor.value?.getHTML() === value
  if (!isSame)
    editor.value?.commands.setContent(value || '', { emitUpdate: false })
})

watch(() => props.disabled, disabled => {
  editor.value?.setEditable(!disabled)
})

const activeFontFamily = computed(() => toolbarState.value.fontFamily)

const activeFontSize = computed(() => toolbarState.value.fontSize)

const activeColor = computed(() => toolbarState.value.color)

// At a collapsed cursor TipTap uses stored marks. Keeping the cursor collapsed
// means a brand-new/empty editor immediately adopts the selected typography
// for the next text the author types, instead of requiring placeholder text
// or an existing selection first.
const applyTextStyle = (attributes: Record<string, string | null>) => {
  const editorInstance = editor.value
  if (!editorInstance)
    return

  const { selection } = editorInstance.state
  if (selection.empty) {
    editorInstance.chain().focus().setMark('textStyle', attributes).run()
    syncToolbarState(editorInstance)
    toolbarState.value = {
      ...toolbarState.value,
      fontFamily: attributes.fontFamily === undefined ? toolbarState.value.fontFamily : normalizeFontFamily(attributes.fontFamily),
      fontSize: attributes.fontSize === undefined ? toolbarState.value.fontSize : normalizeFontSize(attributes.fontSize),
      color: attributes.color === undefined ? toolbarState.value.color : normalizeColor(attributes.color),
    }

    return
  }

  editorInstance
    .chain()
    .focus()
    .setMark('textStyle', attributes)
    .run()
}

const setFontFamily = (family: string | null) => {
  applyTextStyle({
    fontFamily: family === defaultFontFamily ? null : family,
  })
}

const setFontSize = (size: string | null) => {
  applyTextStyle({
    fontSize: size === defaultFontSize ? null : size,
  })
}

const setColor = (color: string) => {
  applyTextStyle({ color })
}

const currentDir = computed(() => toolbarState.value.direction)

const setParagraphDirection = (dir: 'rtl' | 'ltr' | null) => {
  editor.value
    ?.chain()
    .focus()
    .updateAttributes('paragraph', { dir })
    .run()
}

const insertPlaceholder = ({ key, label }: InsertContractPlaceholderOptions) => {
  editor.value
    ?.chain()
    .focus()
    .insertContent({
      type: 'text',
      text: `{{ ${label} }}`,
      marks: [{ type: 'contractPlaceholder', attrs: { key, label } }],
    })
    .run()
}

const setLink = () => {
  const editorRef = editor.value
  if (!editorRef)
    return

  const previous = editorRef.getAttributes('link').href as string | undefined
  // eslint-disable-next-line no-alert
  const url = window.prompt(tr('editor.linkUrl'), previous || 'https://')
  if (url === null)
    return
  if (url === '') {
    editorRef.chain().focus().extendMarkRange('link').unsetLink().run()

    return
  }
  editorRef.chain().focus().extendMarkRange('link').setLink({ href: url, target: '_blank' }).run()
}

const handleToolbarWheel = (event: WheelEvent) => {
  if (event.ctrlKey || Math.abs(event.deltaY) <= Math.abs(event.deltaX))
    return

  const toolbar = event.currentTarget as HTMLElement
  if (toolbar.scrollWidth <= toolbar.clientWidth)
    return

  toolbar.scrollLeft += event.deltaY
  event.preventDefault()
}

defineExpose({
  getEditor: (): Editor | undefined => editor.value ?? undefined,
})
</script>

<template>
  <div
    class="contract-editor"
    :class="[`contract-editor--${variant}`, { 'contract-editor--disabled': disabled }]"
  >
    <div
      v-if="editor"
      class="contract-editor__toolbar d-flex flex-nowrap align-center gap-1 pa-2"
      @wheel="handleToolbarWheel"
    >
      <IconBtn
        size="small"
        rounded
        variant="text"
        :disabled="disabled"
        @click="editor.chain().focus().undo().run()"
      >
        <VIcon icon="tabler-arrow-back-up" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.undo') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        variant="text"
        :disabled="disabled"
        @click="editor.chain().focus().redo().run()"
      >
        <VIcon icon="tabler-arrow-forward" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.redo') }}
        </VTooltip>
      </IconBtn>

      <VDivider
        vertical
        inset
        class="mx-1"
      />

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('bold') ? 'tonal' : 'text'"
        :color="editor.isActive('bold') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().toggleBold().run()"
      >
        <VIcon icon="tabler-bold" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.bold') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('italic') ? 'tonal' : 'text'"
        :color="editor.isActive('italic') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().toggleItalic().run()"
      >
        <VIcon icon="tabler-italic" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.italic') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('underline') ? 'tonal' : 'text'"
        :color="editor.isActive('underline') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().toggleUnderline().run()"
      >
        <VIcon icon="tabler-underline" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.underline') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('strike') ? 'tonal' : 'text'"
        :color="editor.isActive('strike') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().toggleStrike().run()"
      >
        <VIcon icon="tabler-strikethrough" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.strikethrough') }}
        </VTooltip>
      </IconBtn>

      <VDivider
        vertical
        inset
        class="mx-1"
      />

      <template
        v-for="level in [1, 2, 3]"
        :key="level"
      >
        <IconBtn
          size="small"
          rounded
          :variant="editor.isActive('heading', { level }) ? 'tonal' : 'text'"
          :color="editor.isActive('heading', { level }) ? 'primary' : 'default'"
          :disabled="disabled"
          @click="editor.chain().focus().toggleHeading({ level: level as 1 | 2 | 3 }).run()"
        >
          <span class="text-sm font-weight-bold">H{{ level }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ tr('editor.heading', { level }) }}
          </VTooltip>
        </IconBtn>
      </template>

      <VDivider
        vertical
        inset
        class="mx-1"
      />

      <IconBtn
        v-for="align in ['left', 'center', 'right', 'justify']"
        :key="align"
        size="small"
        rounded
        :variant="editor.isActive({ textAlign: align }) ? 'tonal' : 'text'"
        :color="editor.isActive({ textAlign: align }) ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().setTextAlign(align).run()"
      >
        <VIcon :icon="`tabler-align-${align === 'justify' ? 'justified' : align}`" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr(`editor.align.${align}`) }}
        </VTooltip>
      </IconBtn>

      <VDivider
        vertical
        inset
        class="mx-1"
      />

      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('bulletList') ? 'tonal' : 'text'"
        :color="editor.isActive('bulletList') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().toggleBulletList().run()"
      >
        <VIcon icon="tabler-list" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.bulletList') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('orderedList') ? 'tonal' : 'text'"
        :color="editor.isActive('orderedList') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().toggleOrderedList().run()"
      >
        <VIcon icon="tabler-list-numbers" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.numberedList') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('blockquote') ? 'tonal' : 'text'"
        :color="editor.isActive('blockquote') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="editor.chain().focus().toggleBlockquote().run()"
      >
        <VIcon icon="tabler-quote" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.quote') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        :variant="editor.isActive('link') ? 'tonal' : 'text'"
        :color="editor.isActive('link') ? 'primary' : 'default'"
        :disabled="disabled"
        @click="setLink"
      >
        <VIcon icon="tabler-link" />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.insertLink') }}
        </VTooltip>
      </IconBtn>

      <VDivider
        vertical
        inset
        class="mx-1"
      />

      <!-- Per-paragraph direction: contracts mix RTL and LTR paragraphs -->
      <IconBtn
        size="small"
        rounded
        :variant="currentDir === 'rtl' ? 'tonal' : 'text'"
        :color="currentDir === 'rtl' ? 'primary' : 'default'"
        :disabled="disabled"
        @click="setParagraphDirection(currentDir === 'rtl' ? null : 'rtl')"
      >
        <VIcon
          icon="tabler-language-katakana"
          class="flip-in-rtl"
        />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.rtlParagraph') }}
        </VTooltip>
      </IconBtn>
      <IconBtn
        size="small"
        rounded
        :variant="currentDir === 'ltr' ? 'tonal' : 'text'"
        :color="currentDir === 'ltr' ? 'primary' : 'default'"
        :disabled="disabled"
        @click="setParagraphDirection(currentDir === 'ltr' ? null : 'ltr')"
      >
        <VIcon
          icon="tabler-language"
          class="flip-in-rtl"
        />
        <VTooltip
          activator="parent"
          location="top"
        >
          {{ tr('editor.ltrParagraph') }}
        </VTooltip>
      </IconBtn>

      <VDivider
        vertical
        inset
        class="mx-1"
      />

      <VTooltip
        location="top"
        :text="tr('editor.fontFamily')"
      >
        <template #activator="{ props }">
          <VSelect
            v-bind="props"
            class="contract-editor__toolbar-select"
            :model-value="activeFontFamily"
            :items="fontFamilies"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 130px"
            :disabled="disabled"
            @update:model-value="setFontFamily"
          />
        </template>
      </VTooltip>
      <VTooltip
        location="top"
        :text="tr('editor.fontSize')"
      >
        <template #activator="{ props }">
          <VSelect
            v-bind="props"
            class="contract-editor__toolbar-select"
            :model-value="activeFontSize"
            :items="fontSizes"
            item-title="title"
            item-value="value"
            density="compact"
            variant="outlined"
            hide-details
            style="max-width: 86px"
            :disabled="disabled"
            @update:model-value="setFontSize"
          />
        </template>
      </VTooltip>
      <VMenu
        :close-on-content-click="false"
        :disabled="disabled"
      >
        <template #activator="{ props: menuProps }">
          <VBtn
            v-bind="menuProps"
            icon
            variant="text"
            size="small"
          >
            <VIcon icon="tabler-palette" />
            <span
              class="contract-editor__color-dot"
              :style="{ backgroundColor: activeColor }"
            />
            <VTooltip
              activator="parent"
              location="top"
            >
              {{ tr('editor.textColor') }}
            </VTooltip>
          </VBtn>
        </template>
        <VCard elevation="8">
          <VCardText class="pa-2">
            <input
              type="color"
              :value="activeColor"
              class="contract-editor__color-input"
              @input="setColor(($event.target as HTMLInputElement).value)"
            >
          </VCardText>
        </VCard>
      </VMenu>

      <VDivider
        vertical
        inset
        class="mx-1"
      />

      <!-- Context-aware placeholder picker -->
      <VMenu
        :close-on-content-click="false"
        :disabled="disabled || placeholderGroups.length === 0"
        max-width="480"
        location="bottom"
      >
        <template #activator="{ props: menuProps }">
          <VBtn
            v-bind="menuProps"
            variant="tonal"
            :color="variant === 'policy' ? 'secondary' : 'primary'"
            size="small"
            prepend-icon="tabler-braces"
            :disabled="disabled || placeholderGroups.length === 0"
          >
            {{ tr('editor.insertPlaceholder') }}
          </VBtn>
        </template>
        <VCard
          class="contract-editor__placeholder-menu"
          elevation="12"
        >
          <div class="contract-editor__placeholder-heading">
            <VAvatar
              :color="variant === 'policy' ? 'secondary' : 'primary'"
              variant="tonal"
              size="36"
            >
              <VIcon
                icon="tabler-sparkles"
                size="19"
              />
            </VAvatar>
            <div>
              <div class="text-body-2 font-weight-bold">
                {{ tr('editor.insertPlaceholder') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Add a field at the cursor; its value is filled in when the contract is created.
              </div>
            </div>
          </div>
          <VCardText class="pt-0 px-3 pb-3">
            <VTextField
              v-model="placeholderSearch"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              prepend-inner-icon="tabler-search"
              placeholder="Search fields"
              class="mb-3"
            />
            <div class="contract-editor__placeholder-help">
              <VIcon
                icon="tabler-info-circle"
                size="17"
              />
              <span>Choose the field you need. The highlighted token stays visible while editing and is replaced with the live value in the final contract.</span>
            </div>
            <div class="contract-editor__placeholder-results">
              <section
                v-for="group in filteredPlaceholderGroups"
                :key="group.key"
                class="contract-editor__placeholder-group"
              >
                <div class="contract-editor__group-label">
                  {{ group.label }}
                </div>
                <div class="contract-editor__placeholder-grid">
                  <VBtn
                    v-for="field in group.fields"
                    :key="field.key"
                    variant="text"
                    color="default"
                    class="contract-editor__placeholder-option"
                    @click="insertPlaceholder({ key: field.key, label: field.label }); emit('placeholder-inserted', field.key)"
                  >
                    <span class="contract-editor__placeholder-copy">
                      <span class="contract-editor__placeholder-name">{{ field.label }}</span>
                      <code>{{ field.key }}</code>
                    </span>
                    <span class="contract-editor__placeholder-add"><VIcon
                      icon="tabler-plus"
                      size="16"
                    /></span>
                  </VBtn>
                </div>
              </section>
              <div
                v-if="!filteredPlaceholderGroups.length"
                class="text-center text-caption text-medium-emphasis py-6"
              >
                No matching fields
              </div>
            </div>
          </VCardText>
        </VCard>
      </VMenu>
    </div>

    <VDivider />

    <EditorContent
      :editor="editor"
      class="contract-editor__content"
      :style="{ '--editor-min-height': minHeight }"
    />
  </div>
</template>

<style lang="scss">
.contract-editor {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  overflow: hidden;

  &--disabled {
    opacity: 0.65;
  }

  &__toolbar {
    background: rgba(var(--v-theme-surface), 1);
    flex-wrap: nowrap;
    max-inline-size: 100%;
    overflow-x: auto;
    overscroll-behavior-inline: contain;
    scrollbar-width: none;
    white-space: nowrap;

    > * { flex: 0 0 auto; }

    &::-webkit-scrollbar { display: none; }
  }

  @media (max-width: 760px) {
    &__toolbar {
      overflow-x: auto;
    }
  }

  &--policy {
    border-color: rgba(var(--v-theme-secondary), 0.35);

    .contract-editor__toolbar {
      background: rgba(var(--v-theme-secondary), 0.055);
      border-block-start: 3px solid rgb(var(--v-theme-secondary));
    }
  }

  &__toolbar-select {
    flex: 0 0 auto;

    .v-field {
      min-block-size: 36px;
    }

    .v-field__input {
      --v-field-input-padding-top: 0;
      --v-field-input-padding-bottom: 0;
      min-block-size: 34px;
      padding-block: 0;
      align-items: center;
    }

    .v-select__selection {
      align-self: center;
      margin-block: 0;
      transform: none;
    }
  }

  &__group-label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgb(var(--v-theme-primary));
  }

  &--policy .contract-editor__group-label {
    color: rgb(var(--v-theme-secondary));
  }

  &__placeholder-menu {
    inline-size: min(480px, calc(100vw - 24px));
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 0.9rem;
    background: rgb(var(--v-theme-surface));
  }

  &__placeholder-heading {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    padding: 1rem;
  }

  &__placeholder-results {
    max-block-size: 310px;
    overflow: auto;
    padding: 0.1rem 0.15rem 0.25rem;
  }

  &__placeholder-help {
    display: flex;
    gap: 0.45rem;
    align-items: flex-start;
    margin-block-end: 0.85rem;
    padding: 0.6rem 0.7rem;
    border-radius: 0.5rem;
    background: rgba(var(--v-theme-primary), 0.08);
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.72rem;
    line-height: 1.45;

    .v-icon { color: rgb(var(--v-theme-primary)); }
  }

  &--policy .contract-editor__placeholder-help {
    background: rgba(var(--v-theme-secondary), 0.08);

    .v-icon { color: rgb(var(--v-theme-secondary)); }
  }

  &__placeholder-group + &__placeholder-group {
    margin-block-start: 1.15rem;
  }

  &__placeholder-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.55rem;
    margin-block-start: 0.55rem;
  }

  &__placeholder-option {
    display: flex;
    align-items: stretch;
    justify-content: flex-start;
    min-block-size: 66px;
    padding: 0.6rem 0.7rem !important;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 0.65rem;
    background: rgba(var(--v-theme-on-surface), 0.025);
    font-size: 0.78rem;
    letter-spacing: normal;
    text-align: start;
    text-transform: none;

    &:hover {
      border-color: rgba(var(--v-theme-primary), 0.5);
      background: rgba(var(--v-theme-primary), 0.075);
    }

    .v-btn__content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      inline-size: 100%;
      min-inline-size: 0;
      gap: 0.75rem;
    }
  }

  &__placeholder-copy {
    display: flex;
    flex-direction: column;
    min-inline-size: 0;
    align-items: flex-start;
    gap: 0.28rem;
    flex: 1 1 auto;
    text-align: start;
  }

  &__placeholder-name {
    overflow: hidden;
    color: rgb(var(--v-theme-on-surface));
    font-size: 0.82rem;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__placeholder-copy code {
    display: block;
    max-inline-size: 100%;
    overflow: hidden;
    inline-size: fit-content;
    max-inline-size: 100%;
    padding: 0.18rem 0.38rem;
    border-radius: 0.3rem;
    background: rgba(var(--v-theme-on-surface), 0.07);
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
    font-size: 0.66rem;
    text-overflow: ellipsis;
    white-space: nowrap;
    direction: ltr;
    unicode-bidi: plaintext;
  }

  &__placeholder-copy code::before { content: '{{ '; }
  &__placeholder-copy code::after { content: ' }}'; }

  &__placeholder-add {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    inline-size: 30px;
    block-size: 30px;
    border-radius: 0.5rem;
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
  }

  &--policy .contract-editor__placeholder-add {
    background: rgba(var(--v-theme-secondary), 0.12);
    color: rgb(var(--v-theme-secondary));
  }

  &--policy .contract-editor__placeholder-option:hover {
    border-color: rgba(var(--v-theme-secondary), 0.5);
    background: rgba(var(--v-theme-secondary), 0.075);
  }

  &__color-wrap {
    position: relative;
    display: inline-flex;
  }

  &__color-dot {
    position: absolute;
    inset-block-end: -2px;
    inset-inline-end: -2px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  &__color-input {
    inline-size: 180px;
    block-size: 36px;
    cursor: pointer;
  }

  &__content {
    /* The editor and printed contract share this font stack so documents
       render identically on screen and paper. */
    --contract-font-stack: 'Vazirmatn', 'Noto Sans Arabic', Tahoma, sans-serif;

    font-family: var(--contract-font-stack);

    .ProseMirror {
      padding: 1rem;
      min-block-size: var(--editor-min-height);
      outline: none;
      line-height: 1.7;

      p,
      h1,
      h2,
      h3 {
        &[dir='rtl'] {
          text-align: start;
          unicode-bidi: plaintext;
        }

        &[dir='ltr'] {
          text-align: start;
          unicode-bidi: plaintext;
        }
      }

      p.is-editor-empty:first-child::before {
        block-size: 0;
        color: #adb5bd;
        content: attr(data-placeholder);
        float: inline-start;
        pointer-events: none;
      }
    }

    .contract-placeholder {
      display: inline-block;
      padding: 0 0.35em;
      margin: 0 0.1em;
      border-radius: 4px;
      background: rgba(var(--v-theme-primary), 0.14);
      color: rgb(var(--v-theme-primary));
      border: 1px dashed rgba(var(--v-theme-primary), 0.55);
      font-size: 0.9em;
      white-space: nowrap;
    }
  }
}
</style>
