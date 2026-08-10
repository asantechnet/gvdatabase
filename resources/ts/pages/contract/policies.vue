<script setup lang="ts">
import ContractTemplateApi from '@/api/contractTemplate'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useRtl } from 'vuetify'

// Routing & Theme Utilities
const router = useRouter()
const route = useRoute()
const { isRtl } = useRtl()

// State
const formRef = ref()
const contractName = ref('')
const contractType = ref<'Employment' | 'Service' | 'Partnership' | 'Lease' | 'NDA'>('Employment')
const coverText = ref('')
const templateId = ref<number | null>(null)
const templates = ref<any[]>([])
const isLoading = ref(false)
const deleteDialog = ref(false)
const templateToDelete = ref<number | null>(null)

// Vuexy-style Native Snackbar
const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
  icon: 'tabler-check'
})

const showToast = (message: string, color: 'success' | 'error' | 'warning' = 'success') => {
  snackbar.value = {
    show: true,
    message,
    color,
    icon: color === 'success' ? 'tabler-circle-check' : color === 'error' ? 'tabler-alert-circle' : 'tabler-alert-triangle'
  }
}

let quillEditor: Quill | null = null

// Register Inline Style Attributor for Fonts
const registerQuillFonts = () => {
  const FontStyle = Quill.import('attributors/style/font')

  // Whitelist 5 English and 5 Persian font family names
  FontStyle.whitelist = [
    'arial', 'times-new-roman', 'georgia', 'verdana', 'roboto', // English
    'vazir', 'shabnam', 'samim', 'sahel', 'tanha' // Persian
  ]

  Quill.register(FontStyle, true)
}

onMounted(async () => {
  await nextTick()
  registerQuillFonts()
  initializeEditor()
  loadTemplates()

  if (route.params.id) {
    loadTemplate(Number(route.params.id))
  }
})

onBeforeUnmount(() => {
  if (quillEditor) {
    quillEditor = null
  }
})

const initializeEditor = () => {
  const editorElement = document.getElementById('contract-policies-editor')

  if (editorElement && !quillEditor) {
    const FontStyle = Quill.import('attributors/style/font')

    quillEditor = new Quill(editorElement, {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ 'font': FontStyle.whitelist }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'color': [] }, { 'background': [] }],
          [{ 'script': 'super' }, { 'script': 'sub' }],
          [{ 'header': [1, 2, 3, false] }],
          [{ 'align': [] }],
          ['blockquote', 'code-block'],
          [{ 'list': 'ordered' }, { 'list': 'bullet' }],
          ['link', 'image'],
          ['clean']
        ],
        history: {
          delay: 2000,
          maxStack: 500,
          userOnly: true
        }
      },
      placeholder: 'Write your contract policies here...'
    })

    // Dynamically respect RTL/LTR and set default font
    quillEditor.format('font', 'vazir')
    quillEditor.format('direction', isRtl.value ? 'rtl' : 'ltr')
    if (isRtl.value) {
      quillEditor.format('align', 'right')
    }
  }
}

// Populate Quill using HTML input (with legacy Delta fallback support)
const setEditorContent = (content: any) => {
  if (!quillEditor) return

  // 1. Clear editor and reset state
  quillEditor.setText('')

  if (!content) return

  try {
    // 2. Handle HTML string input
    if (typeof content === 'string') {
      const trimmed = content.trim()

      // Backward compatibility check for legacy JSON Deltas stored in DB
      if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
        try {
          const parsed = JSON.parse(trimmed)
          const ops = Array.isArray(parsed) ? parsed : parsed?.ops
          if (Array.isArray(ops)) {
            const cleanOps = ops.filter(op => op && op.insert !== null && op.insert !== undefined)
            quillEditor.setContents({ ops: cleanOps })
            return
          }
        } catch {
          // Not JSON, continue to paste HTML below
        }
      }

      // Paste HTML directly into editor
      quillEditor.clipboard.dangerouslyPasteHTML(trimmed)
      return
    }

    // 3. Backward compatibility for legacy Delta object records
    if (typeof content === 'object') {
      const ops = Array.isArray(content) ? content : content?.ops
      if (Array.isArray(ops)) {
        const cleanOps = ops.filter((op: any) => op && op.insert !== null && op.insert !== undefined)
        quillEditor.setContents({ ops: cleanOps })
        return
      }
    }

    // Fallback paste
    quillEditor.clipboard.dangerouslyPasteHTML(String(content))
  } catch (err) {
    console.error('Error pasting content to Quill:', err)
  }
}

const loadTemplates = async () => {
  try {
    isLoading.value = true
    const response = await ContractTemplateApi.getAll()
    templates.value = response.data.data || []
  } catch (error) {
    showToast('Failed to load templates', 'error')
    console.error('Load templates failed:', error)
  } finally {
    isLoading.value = false
  }
}

const saveContract = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid || !quillEditor) return

  // Grabs the raw HTML output from Quill instead of Delta JSON
  const htmlContent = quillEditor.root.innerHTML

  const payload = {
    name: contractName.value,
    type: contractType.value,
    cover_text: coverText.value,
    content: htmlContent
  }

  try {
    isLoading.value = true
    if (templateId.value) {
      await ContractTemplateApi.update(templateId.value, payload)
      showToast('Template updated successfully', 'success')
    } else {
      await ContractTemplateApi.create(payload)
      showToast('Template created successfully', 'success')
    }

    await loadTemplates()
    resetForm()
  } catch (error) {
    showToast('Failed to save template', 'error')
    console.error('Save failed:', error)
  } finally {
    isLoading.value = false
  }
}

const loadTemplate = async (id: number) => {
  try {
    isLoading.value = true
    const response = await ContractTemplateApi.getById(id)
    const template = response.data.data

    contractName.value = template.name
    contractType.value = template.type
    coverText.value = template.cover_text
    templateId.value = template.id

    // Load stored HTML into editor
    setEditorContent(template.content)
  } catch (error) {
    showToast('Failed to load template details', 'error')
    console.error('Load failed:', error)
  } finally {
    isLoading.value = false
  }
}

const confirmDelete = (id: number) => {
  templateToDelete.value = id
  deleteDialog.value = true
}

const deleteTemplate = async () => {
  if (!templateToDelete.value) return

  try {
    isLoading.value = true
    await ContractTemplateApi.delete(templateToDelete.value)
    showToast('Template deleted successfully', 'success')
    await loadTemplates()
    if (templateId.value === templateToDelete.value) {
      resetForm()
    }
  } catch (error) {
    showToast('Failed to delete template', 'error')
    console.error('Delete failed:', error)
  } finally {
    isLoading.value = false
    deleteDialog.value = false
    templateToDelete.value = null
  }
}

const resetForm = () => {
  formRef.value?.resetValidation()
  contractName.value = ''
  contractType.value = 'Employment'
  coverText.value = ''
  templateId.value = null

  if (quillEditor) {
    quillEditor.setText('')
    quillEditor.format('font', 'vazir')
    quillEditor.format('direction', isRtl.value ? 'rtl' : 'ltr')
  }
}

const editTemplate = (id: number) => {
  loadTemplate(id)
}
</script>

<template>
  <VRow class="match-height">
    <!-- Templates List Sidebar -->
    <VCol cols="12" md="5" lg="4">
      <VCard class="d-flex flex-column h-100">
        <VCardItem class="pb-4">
          <template #prepend>
            <VIcon icon="tabler-files" size="24" class="text-primary mr-2" />
          </template>
          <VCardTitle>Contract Templates</VCardTitle>
          <VCardSubtitle>Manage saved legal structures</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText class="pa-0 flex-grow-1 template-list-container">
          <!-- Loading State -->
          <div v-if="isLoading && !templates.length" class="d-flex justify-center align-center pa-6">
            <VProgressCircular indeterminate color="primary" />
          </div>

          <!-- Empty State -->
          <div v-else-if="!templates.length" class="text-center pa-8 text-medium-emphasis">
            <VIcon icon="tabler-folder-off" size="48" class="mb-2 opacity-50" />
            <div class="text-body-1 font-weight-medium">No templates found</div>
            <div class="text-caption">Use the form to create your first template</div>
          </div>

          <!-- List -->
          <VList v-else lines="two" class="pa-2">
            <VListItem v-for="template in templates" :key="template.id" :active="templateId === template.id"
              color="primary" rounded="lg" class="mb-1 transition-fast-in-fast-out" @click="editTemplate(template.id)">
              <template #prepend>
                <VAvatar color="primary" variant="tonal" size="38" class="mr-3">
                  <VIcon icon="tabler-file-description" size="20" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">
                {{ template.name }}
              </VListItemTitle>
              <VListItemSubtitle class="mt-1">
                <VChip size="x-small" color="secondary" variant="elevated" class="font-weight-medium">
                  {{ template.type }}
                </VChip>
              </VListItemSubtitle>

              <template #append>
                <div class="d-flex align-center gap-1">
                  <!-- Edit Action Button -->
                  <VBtn icon variant="text" size="small" color="primary" class="opacity-75 hover-opacity-100"
                    @click.stop="editTemplate(template.id)">
                    <VIcon icon="tabler-edit" size="18" />
                    <VTooltip activator="parent" location="top">Edit Template</VTooltip>
                  </VBtn>

                  <!-- Delete Action Button -->
                  <VBtn icon variant="text" size="small" color="error" class="opacity-75 hover-opacity-100"
                    @click.stop="confirmDelete(template.id)">
                    <VIcon icon="tabler-trash" size="18" />
                    <VTooltip activator="parent" location="top">Delete Template</VTooltip>
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Main Editor Form -->
    <VCol cols="12" md="7" lg="8">
      <VCard class="contract-policies-form d-flex flex-column h-100">
        <VCardItem class="pb-4">
          <template #prepend>
            <VIcon :icon="templateId ? 'tabler-edit' : 'tabler-file-plus'" size="24" class="text-primary mr-2" />
          </template>
          <VCardTitle>{{ templateId ? 'Edit Template' : 'Create New Template' }}</VCardTitle>
          <VCardSubtitle>Define template properties and contract policies</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText class="pt-6 flex-grow-1">
          <VForm ref="formRef" @submit.prevent="saveContract">
            <VRow>
              <VCol cols="12" sm="6">
                <VTextField v-model="contractName" label="Contract Name" placeholder="e.g. Standard NDA 2026"
                  prepend-inner-icon="tabler-file-text" variant="outlined" :rules="[v => !!v || 'Name is required']" />
              </VCol>

              <VCol cols="12" sm="6">
                <VSelect v-model="contractType" label="Contract Type"
                  :items="['Employment', 'Service', 'Partnership', 'Lease', 'NDA']" prepend-inner-icon="tabler-settings"
                  variant="outlined" :rules="[v => !!v || 'Type is required']" />
              </VCol>

              <VCol cols="12">
                <VTextarea v-model="coverText" label="Cover / Introduction Text"
                  placeholder="Brief overview or introductory statement of the contract..."
                  prepend-inner-icon="tabler-align-left" variant="outlined" rows="2" auto-grow />
              </VCol>

              <!-- Quill Editor Section -->
              <VCol cols="12">
                <div class="text-caption text-medium-emphasis mb-1 font-weight-medium">
                  CONTRACT POLICIES & TERMS
                </div>
                <div class="editor-container">
                  <div id="contract-policies-editor" class="quill-editor" />
                </div>
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-6">
              <VBtn color="secondary" variant="tonal" prepend-icon="tabler-refresh" :disabled="isLoading"
                @click="resetForm">
                Reset
              </VBtn>
              <VBtn type="submit" color="primary" variant="elevated" prepend-icon="tabler-device-floppy"
                :loading="isLoading">
                {{ templateId ? 'Update Template' : 'Save Template' }}
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <!-- Delete Confirmation Dialog -->
  <VDialog v-model="deleteDialog" max-width="400px">
    <VCard class="text-center pa-4">
      <VCardItem>
        <VAvatar color="error" variant="tonal" size="56" class="mb-2">
          <VIcon icon="tabler-alert-triangle" size="32" />
        </VAvatar>
        <VCardTitle class="text-h5">Delete Template?</VCardTitle>
      </VCardItem>

      <VCardText class="text-body-2 text-medium-emphasis">
        Are you sure you want to delete this contract template? This action cannot be undone.
      </VCardText>

      <VCardActions class="justify-center gap-3 mt-2">
        <VBtn color="secondary" variant="tonal" @click="deleteDialog = false">
          Cancel
        </VBtn>
        <VBtn color="error" variant="elevated" :loading="isLoading" @click="deleteTemplate">
          Delete
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Vuexy Native Snackbar -->
  <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="bottom end" variant="elevated" timeout="3000">
    <div class="d-flex align-center">
      <VIcon :icon="snackbar.icon" size="20" class="mr-2" />
      <span>{{ snackbar.message }}</span>
    </div>
  </VSnackbar>
</template>

<style lang="scss">
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

// Handle responsive template list height for tablet/desktop
.template-list-container {
  max-height: 400px;
  overflow-y: auto;

  @media (min-width: 960px) {
    max-height: calc(100vh - 200px);
  }
}

// Modernized Theme-Agnostic Quill Editor Styling
.contract-policies-form {
  .editor-container {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 6px;
    background-color: rgb(var(--v-theme-surface));
    transition: border-color 0.2s ease-in-out;

    &:focus-within {
      border-color: rgb(var(--v-theme-primary));
    }

    // Modernize Toolbar for Light/Dark Mode
    .ql-toolbar.ql-snow {
      border: none;
      border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
      background-color: rgba(var(--v-theme-on-surface), 0.02);
      padding: 12px;

      // Font Picker Dropdown Sizing
      .ql-picker.ql-font {
        width: 150px !important;

        .ql-picker-label {
          padding-right: 24px;
        }
      }

      .ql-picker-label,
      .ql-picker-item,
      .ql-stroke {
        color: rgb(var(--v-theme-on-surface));
        stroke: rgb(var(--v-theme-on-surface));
      }

      .ql-fill {
        fill: rgb(var(--v-theme-on-surface));
      }

      .ql-active .ql-stroke,
      button:hover .ql-stroke {
        stroke: rgb(var(--v-theme-primary));
      }
    }

    .quill-editor {
      min-height: 320px;
      max-height: 500px;
      overflow-y: auto;

      .ql-container.ql-snow {
        border: none !important;
        font-family: 'vazir', sans-serif;
        color: rgb(var(--v-theme-on-surface));
      }

      .ql-editor {
        padding: 16px !important;
        line-height: 1.8;
        font-size: 0.95rem;

        &.ql-blank::before {
          color: rgba(var(--v-theme-on-surface), 0.38);
          font-style: normal;
        }
      }
    }
  }

  .v-field__input {
    font-family: 'vazir', sans-serif;
  }
}

// Map 10 Custom Fonts to Quill Dropdown Labels and Render Styles
$quill-fonts: (
  'arial': ('Arial', Arial, sans-serif),
  'times-new-roman': ('Times New Roman', 'Times New Roman', serif),
  'georgia': ('Georgia', Georgia, serif),
  'verdana': ('Verdana', Verdana, sans-serif),
  'roboto': ('Roboto', 'Roboto', sans-serif),
  'vazir': ('Vazir', 'vazir', sans-serif),
  'shabnam': ('Shabnam', 'shabnam', sans-serif),
  'samim': ('Samim', 'samim', sans-serif),
  'sahel': ('Sahel', 'sahel', sans-serif),
  'tanha': ('Tanha', 'tanha', sans-serif)
);

@each $key, $val in $quill-fonts {
  $label: nth($val, 1);
  $family: nth($val, 2);
  $fallback: nth($val, 3);

  .ql-picker.ql-font {

    .ql-picker-label[data-value="#{$key}"]::before,
    .ql-picker-item[data-value="#{$key}"]::before {
      content: "#{$label}" !important;
      font-family: $family, $fallback !important;
    }
  }

  // Ensure inline-styled elements render in the editor correctly
  [style*="font-family: #{$key}"],
  [style*="font-family: \"#{$key}\""] {
    font-family: $family, $fallback !important;
  }
}

// Set default label if no data-value is selected
.ql-picker.ql-font {

  .ql-picker-label:not([data-value])::before,
  .ql-picker-item:not([data-value])::before {
    content: "Vazir" !important;
    font-family: 'vazir', sans-serif !important;
  }
}

// Reliable, Verified CDNs for 5 Open-Source Persian Fonts
@font-face {
  font-family: 'vazir';
  src: url('https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/fonts/webfonts/Vazirmatn-Regular.woff2') format('woff2');
  font-weight: normal;
  font-style: normal;
  font-display: swap;
}

@font-face {
  font-family: 'shabnam';
  src: url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam.woff2') format('woff2');
  font-weight: normal;
  font-style: normal;
  font-display: swap;
}

@font-face {
  font-family: 'samim';
  src: url('https://cdn.jsdelivr.net/gh/rastikerdar/samim-font@v4.0.5/dist/Samim.woff2') format('woff2');
  font-weight: normal;
  font-style: normal;
  font-display: swap;
}

@font-face {
  font-family: 'sahel';
  src: url('https://cdn.jsdelivr.net/gh/rastikerdar/sahel-font@v3.4.0/dist/Sahel.woff2') format('woff2');
  font-weight: normal;
  font-style: normal;
  font-display: swap;
}

@font-face {
  font-family: 'tanha';
  src: url('https://cdn.jsdelivr.net/gh/rastikerdar/tanha-font@v0.9.0/dist/Tanha.woff2') format('woff2');
  font-weight: normal;
  font-style: normal;
  font-display: swap;
}
</style>
