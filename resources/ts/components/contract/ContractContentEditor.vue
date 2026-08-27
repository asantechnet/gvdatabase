<script setup lang="ts">
import { Placeholder } from '@tiptap/extension-placeholder'
import { TextAlign } from '@tiptap/extension-text-align'
import { TextStyle } from '@tiptap/extension-text-style'
import { Underline } from '@tiptap/extension-underline'
import { StarterKit } from '@tiptap/starter-kit'
import { EditorContent, useEditor, type Editor } from '@tiptap/vue-3'
import type { PlaceholderGroup } from '@/api/contractPlaceholders'
import { ContractPlaceholderMark, type InsertContractPlaceholderOptions } from '@/extensions/contract/ContractPlaceholderMark'
import { ParagraphDirection } from '@/extensions/contract/ParagraphDirection'

const props = withDefaults(defineProps<{
  modelValue: string
  placeholderGroups?: PlaceholderGroup[]
  disabled?: boolean
  minHeight?: string
}>(), {
  placeholderGroups: () => [],
  disabled: false,
  minHeight: '420px',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'placeholder-inserted', key: string): void
}>()

const defaultFontFamily = '__default_font_family__'
const defaultFontSize = '__default_font_size__'
const fontFamilies = [
  { title: 'Font', value: defaultFontFamily },
  { title: 'Vazirmatn', value: 'Vazirmatn' },
  { title: 'Estedad', value: 'Estedad' },
  { title: 'Sahel', value: 'Sahel' },
  { title: 'Samim', value: 'Samim' },
  { title: 'Shabnam', value: 'Shabnam' },
  { title: 'Arial', value: 'Arial' },
  { title: 'Tahoma', value: 'Tahoma' },
  { title: 'Times New Roman', value: 'Times New Roman' },
  { title: 'Courier New', value: 'Courier New' },
]
const fontSizes = [
  { title: 'Size', value: defaultFontSize },
  ...['11px', '12px', '14px', '16px', '18px', '20px', '24px', '28px'].map(value => ({ title: value, value })),
]

const toolbarState = ref({
  fontFamily: defaultFontFamily,
  fontSize: defaultFontSize,
  color: '#000000',
  direction: null as 'rtl' | 'ltr' | null,
})

const normalizeColor = (color?: string) => {
  if (!color)
    return '#000000'

  const rgbMatch = color.match(/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/)
  if (!rgbMatch)
    return color.startsWith('#') ? color : '#000000'

  return `#${rgbMatch.slice(1).map(value => Number(value).toString(16).padStart(2, '0')).join('')}`
}

const normalizeFontFamily = (family?: string | null) => {
  const value = family?.split(',')[0]?.replace(/['"]/g, '').trim()
  return fontFamilies.some(font => font.value === value) ? value! : defaultFontFamily
}

const normalizeFontSize = (size?: string | null) =>
  fontSizes.some(fontSize => fontSize.value === size) ? size! : defaultFontSize

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

// Extends the stock TextStyle mark with the typographic attributes legal
// documents need (family, size, color) without new dependencies.
const ContractTextStyle = TextStyle.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      fontFamily: {
        default: null,
        parseHTML: element => element.style.fontFamily.replace(/['"]/g, ''),
        renderHTML: attributes => {
          if (!(attributes as { fontFamily?: string | null }).fontFamily)
            return {}

          return { style: `font-family: '${(attributes as { fontFamily: string }).fontFamily}'` }
        },
      },
      fontSize: {
        default: null,
        parseHTML: element => element.style.fontSize,
        renderHTML: attributes => {
          if (!(attributes as { fontSize?: string | null }).fontSize)
            return {}

          return { style: `font-size:${(attributes as { fontSize: string }).fontSize}` }
        },
      },
      color: {
        default: null,
        parseHTML: element => element.style.color,
        renderHTML: attributes => {
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
    Placeholder.configure({ placeholder: 'Start writing the contract…' }),
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
  if (!isSame) editor.value?.commands.setContent(value || '', false)
})

watch(() => props.disabled, disabled => {
  editor.value?.setEditable(!disabled)
})

const activeFontFamily = computed(() => toolbarState.value.fontFamily)

const activeFontSize = computed(() => toolbarState.value.fontSize)

const activeColor = computed(() => toolbarState.value.color)

// A mark at a collapsed cursor only affects future typing. Formatting the
// current paragraph in that case gives font, size, and color controls an
// immediate, visible effect without requiring the author to select its text.
const applyTextStyle = (attributes: Record<string, string | null>) => {
  const editorInstance = editor.value
  if (!editorInstance)
    return

  const { selection } = editorInstance.state
  const from = selection.empty ? selection.$from.start() : selection.from
  const to = selection.empty ? selection.$from.end() : selection.to
  const restoreSelection = selection.empty ? selection.from : { from: selection.from, to: selection.to }

  editorInstance
    .chain()
    .focus()
    .setTextSelection({ from, to })
    .setMark('textStyle', attributes)
    .setTextSelection(restoreSelection)
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
      text: `[ ${label} ]`,
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
  const url = window.prompt('Link URL', previous || 'https://')
  if (url === null)
    return
  if (url === '') {
    editorRef.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }
  editorRef.chain().focus().extendMarkRange('link').setLink({ href: url, target: '_blank' }).run()
}

defineExpose({
  getEditor: (): Editor | undefined => editor.value ?? undefined,
})
</script>

<template>
  <div class="contract-editor" :class="{ 'contract-editor--disabled': disabled }">
    <div v-if="editor" class="contract-editor__toolbar d-flex flex-wrap align-center gap-1 pa-2">
      <IconBtn size="small" rounded variant="text" :disabled="disabled"
        @click="editor.chain().focus().undo().run()">
        <VIcon icon="tabler-arrow-back-up" />
        <VTooltip activator="parent" location="top">Undo</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded variant="text" :disabled="disabled"
        @click="editor.chain().focus().redo().run()">
        <VIcon icon="tabler-arrow-forward" />
        <VTooltip activator="parent" location="top">Redo</VTooltip>
      </IconBtn>

      <VDivider vertical inset class="mx-1" />

      <IconBtn size="small" rounded :variant="editor.isActive('bold') ? 'tonal' : 'text'"
        :color="editor.isActive('bold') ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().toggleBold().run()">
        <VIcon icon="tabler-bold" />
        <VTooltip activator="parent" location="top">Bold</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded :variant="editor.isActive('italic') ? 'tonal' : 'text'"
        :color="editor.isActive('italic') ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().toggleItalic().run()">
        <VIcon icon="tabler-italic" />
        <VTooltip activator="parent" location="top">Italic</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded :variant="editor.isActive('underline') ? 'tonal' : 'text'"
        :color="editor.isActive('underline') ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().toggleUnderline().run()">
        <VIcon icon="tabler-underline" />
        <VTooltip activator="parent" location="top">Underline</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded :variant="editor.isActive('strike') ? 'tonal' : 'text'"
        :color="editor.isActive('strike') ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().toggleStrike().run()">
        <VIcon icon="tabler-strikethrough" />
        <VTooltip activator="parent" location="top">Strikethrough</VTooltip>
      </IconBtn>

      <VDivider vertical inset class="mx-1" />

      <template v-for="level in [1, 2, 3]" :key="level">
        <IconBtn size="small" rounded
          :variant="editor.isActive('heading', { level }) ? 'tonal' : 'text'"
          :color="editor.isActive('heading', { level }) ? 'primary' : 'default'" :disabled="disabled"
          @click="editor.chain().focus().toggleHeading({ level: level as 1 | 2 | 3 }).run()">
          <span class="text-sm font-weight-bold">H{{ level }}</span>
          <VTooltip activator="parent" location="top">Heading {{ level }}</VTooltip>
        </IconBtn>
      </template>

      <VDivider vertical inset class="mx-1" />

      <IconBtn v-for="align in ['left', 'center', 'right', 'justify']" :key="align" size="small" rounded
        :variant="editor.isActive({ textAlign: align }) ? 'tonal' : 'text'"
        :color="editor.isActive({ textAlign: align }) ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().setTextAlign(align).run()">
        <VIcon :icon="`tabler-align-${align === 'justify' ? 'justified' : align}`" />
        <VTooltip activator="parent" location="top">Align {{ align }}</VTooltip>
      </IconBtn>

      <VDivider vertical inset class="mx-1" />

      <IconBtn size="small" rounded :variant="editor.isActive('bulletList') ? 'tonal' : 'text'"
        :color="editor.isActive('bulletList') ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().toggleBulletList().run()">
        <VIcon icon="tabler-list" />
        <VTooltip activator="parent" location="top">Bullet list</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded :variant="editor.isActive('orderedList') ? 'tonal' : 'text'"
        :color="editor.isActive('orderedList') ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().toggleOrderedList().run()">
        <VIcon icon="tabler-list-numbers" />
        <VTooltip activator="parent" location="top">Numbered list</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded :variant="editor.isActive('blockquote') ? 'tonal' : 'text'"
        :color="editor.isActive('blockquote') ? 'primary' : 'default'" :disabled="disabled"
        @click="editor.chain().focus().toggleBlockquote().run()">
        <VIcon icon="tabler-quote" />
        <VTooltip activator="parent" location="top">Quote</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded :variant="editor.isActive('link') ? 'tonal' : 'text'"
        :color="editor.isActive('link') ? 'primary' : 'default'" :disabled="disabled"
        @click="setLink">
        <VIcon icon="tabler-link" />
        <VTooltip activator="parent" location="top">Insert link</VTooltip>
      </IconBtn>

      <VDivider vertical inset class="mx-1" />

      <!-- Per-paragraph direction: contracts mix RTL and LTR paragraphs -->
      <IconBtn size="small" rounded :variant="currentDir === 'rtl' ? 'tonal' : 'text'"
        :color="currentDir === 'rtl' ? 'primary' : 'default'" :disabled="disabled"
        @click="setParagraphDirection(currentDir === 'rtl' ? null : 'rtl')">
        <VIcon icon="tabler-language-katakana" class="flip-in-rtl" />
        <VTooltip activator="parent" location="top">Right-to-left paragraph</VTooltip>
      </IconBtn>
      <IconBtn size="small" rounded :variant="currentDir === 'ltr' ? 'tonal' : 'text'"
        :color="currentDir === 'ltr' ? 'primary' : 'default'" :disabled="disabled"
        @click="setParagraphDirection(currentDir === 'ltr' ? null : 'ltr')">
        <VIcon icon="tabler-language" class="flip-in-rtl" />
        <VTooltip activator="parent" location="top">Left-to-right paragraph</VTooltip>
      </IconBtn>

      <VDivider vertical inset class="mx-1" />

      <VTooltip location="top" text="Font family">
        <template #activator="{ props }">
          <VSelect v-bind="props" class="contract-editor__toolbar-select" :model-value="activeFontFamily" :items="fontFamilies" item-title="title" item-value="value" density="compact" variant="outlined"
            hide-details style="max-width: 130px" :disabled="disabled" @update:model-value="setFontFamily" />
        </template>
      </VTooltip>
      <VTooltip location="top" text="Font size">
        <template #activator="{ props }">
          <VSelect v-bind="props" class="contract-editor__toolbar-select" :model-value="activeFontSize" :items="fontSizes" item-title="title" item-value="value" density="compact" variant="outlined" hide-details
            style="max-width: 86px" :disabled="disabled" @update:model-value="setFontSize" />
        </template>
      </VTooltip>
      <VMenu :close-on-content-click="false" :disabled="disabled">
        <template #activator="{ props: menuProps }">
          <VBtn v-bind="menuProps" icon variant="text" size="small">
            <VIcon icon="tabler-palette" />
            <span class="contract-editor__color-dot" :style="{ backgroundColor: activeColor }" />
            <VTooltip activator="parent" location="top">Text color</VTooltip>
          </VBtn>
        </template>
        <VCard elevation="8">
          <VCardText class="pa-2">
            <input type="color" :value="activeColor" class="contract-editor__color-input"
              @input="setColor(($event.target as HTMLInputElement).value)">
          </VCardText>
        </VCard>
      </VMenu>

      <VDivider vertical inset class="mx-1" />

      <!-- Context-aware placeholder picker -->
      <VMenu :close-on-content-click="false" :disabled="disabled || placeholderGroups.length === 0">
        <template #activator="{ props: menuProps }">
          <VBtn v-bind="menuProps" variant="tonal" color="primary" size="small" prepend-icon="tabler-braces"
            :disabled="disabled || placeholderGroups.length === 0">
            Insert Placeholder
          </VBtn>
        </template>
        <VList density="compact" max-height="360" min-width="240" class="py-0">
          <template v-for="group in placeholderGroups" :key="group.key">
            <VListSubtitle class="font-weight-medium text-primary text-uppercase contract-editor__group-label">
              {{ group.label }}
            </VListSubtitle>
            <VListItem v-for="field in group.fields" :key="field.key" :title="field.label" :subtitle="field.key"
              density="compact" @click="insertPlaceholder({ key: field.key, label: field.label }); emit('placeholder-inserted', field.key)" />
            <VDivider v-if="group !== placeholderGroups[placeholderGroups.length - 1]" />
          </template>
          <VListItem v-if="placeholderGroups.length === 0" title="No placeholders available for this contract type" />
        </VList>
      </VMenu>
    </div>

    <VDivider />

    <EditorContent :editor="editor" class="contract-editor__content" :style="{ '--editor-min-height': minHeight }" />
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
  }

  // Vuetify's fields reserve more space above the value than below it. That
  // is useful for labelled form fields, but makes compact toolbar selects look
  // vertically off-centre. Remove that form-specific padding here only.
  &__toolbar-select {
    flex: 0 0 auto;

    .v-field__input {
      --v-field-input-padding-top: 0;
      --v-field-input-padding-bottom: 0;
      align-items: center;
    }

    .v-select__selection {
      align-self: center;
      margin-block: 0;
      transform: translateY(6px);
    }
  }

  &__group-label {
    font-size: 0.7rem;
    letter-spacing: 0.04em;
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
