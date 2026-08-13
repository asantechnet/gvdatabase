<script setup lang="ts">
import ContractTemplateApi from '@/api/contractTemplate'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useRtl } from 'vuetify'

// Routing & Theme Utilities
const route = useRoute()
const toast = useToast()
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

const showToast = (message: string, color: 'success' | 'error' | 'warning' = 'success') => {
  toast[color](message)
}

let quillEditor: any = null

// Register Inline Style Attributor for Fonts
const registerQuillFonts = () => {
  const FontStyle = Quill.import('attributors/style/font')

  // Use dependable system fonts. Remote font CDNs made saved templates render
  // differently depending on network and provider availability.
  FontStyle.whitelist = [
    'arial', 'times-new-roman', 'georgia', 'verdana', 'tahoma', 'segoe-ui'
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
      placeholder: translate('pages.settings.policies.policies.writeYourContractPoliciesHere')
    })

    // Respect direction without writing an unexpected font into the document.
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
    templates.value = await ContractTemplateApi.getAll()
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
    name: contractName.value.trim(),
    type: contractType.value,
    cover_text: coverText.value,
    content: htmlContent
  }

  try {
    isLoading.value = true
    let savedTemplate
    if (templateId.value) {
      savedTemplate = await ContractTemplateApi.update(templateId.value, payload)
      showToast('Template updated successfully', 'success')
    } else {
      savedTemplate = await ContractTemplateApi.create(payload)
      showToast('Template created successfully', 'success')
    }

    const index = templates.value.findIndex(template => template.id === savedTemplate.id)
    if (index === -1)
      templates.value.unshift(savedTemplate)
    else
      templates.value[index] = savedTemplate

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
    const template = await ContractTemplateApi.getById(id)

    contractName.value = template.name
    contractType.value = template.type
    coverText.value = template.cover_text ?? ''
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
    templates.value = templates.value.filter(template => template.id !== templateToDelete.value)
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
  contractName.value = ''
  contractType.value = 'Employment'
  coverText.value = ''
  templateId.value = null

  if (quillEditor) {
    quillEditor.setText('')
    quillEditor.format('direction', isRtl.value ? 'rtl' : 'ltr')
  }

  // Reset after the model values settle so the empty new form does not
  // immediately inherit the previous submit's red required state.
  nextTick(() => formRef.value?.resetValidation())
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
          <VCardTitle>{{ $t('pages.settings.policies.policies.contractTemplates') }}</VCardTitle>
          <VCardSubtitle>{{ $t('pages.settings.policies.policies.manageSavedLegalStructures') }}</VCardSubtitle>
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
            <div class="text-body-1 font-weight-medium">{{ $t('pages.settings.policies.policies.noTemplatesFound') }}</div>
            <div class="text-caption">{{ $t('pages.settings.policies.policies.useTheFormToCreateYourFirstTemplate') }}</div>
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
                  {{ translateBackendValue(template.type) }}
                </VChip>
              </VListItemSubtitle>

              <template #append>
                <div class="d-flex align-center gap-1">
                  <!-- Edit Action Button -->
                  <VBtn icon variant="text" size="small" color="primary" class="opacity-75 hover-opacity-100"
                    @click.stop="editTemplate(template.id)">
                    <VIcon icon="tabler-edit" size="18" />
                    <VTooltip activator="parent" location="top">{{ $t('pages.settings.policies.policies.editTemplate') }}</VTooltip>
                  </VBtn>

                  <!-- Delete Action Button -->
                  <VBtn icon variant="text" size="small" color="error" class="opacity-75 hover-opacity-100"
                    @click.stop="confirmDelete(template.id)">
                    <VIcon icon="tabler-trash" size="18" />
                    <VTooltip activator="parent" location="top">{{ $t('pages.settings.policies.policies.deleteTemplate') }}</VTooltip>
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
          <VCardSubtitle>{{ $t('pages.settings.policies.policies.defineTemplatePropertiesAndContractPolicies') }}</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText class="pt-6 flex-grow-1">
          <VForm ref="formRef" @submit.prevent="saveContract">
            <VRow>
              <VCol cols="12" sm="6">
                <VTextField v-model="contractName" :label="$t('pages.settings.policies.policies.contractName')" :placeholder="$t('pages.settings.policies.policies.eGStandardNDA2026')"
                  prepend-inner-icon="tabler-file-text" variant="outlined" :rules="[v => !!v || 'Name is required']" />
              </VCol>

              <VCol cols="12" sm="6">
                <VSelect v-model="contractType" :label="$t('pages.settings.policies.policies.contractType')"
                :items="['Employment', 'Service', 'Partnership', 'Lease', 'NDA']" :item-title="translateBackendValue" prepend-inner-icon="tabler-settings"
                  variant="outlined" :rules="[v => !!v || 'Type is required']" />
              </VCol>

              <VCol cols="12">
                <VTextarea v-model="coverText" :label="$t('pages.settings.policies.policies.coverIntroductionText')"
                  :placeholder="$t('pages.settings.policies.policies.briefOverviewOrIntroductoryStatementOfTheContract')"
                  prepend-inner-icon="tabler-align-left" variant="outlined" rows="2" auto-grow />
              </VCol>

              <!-- Quill Editor Section -->
              <VCol cols="12">
                <div class="text-caption text-medium-emphasis mb-1 font-weight-medium">
                  {{ $t('pages.settings.policies.policies.contractPOLICIESTERMS') }}
                </div>
                <div class="editor-container">
                  <div id="contract-policies-editor" class="quill-editor" />
                </div>
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-6">
              <VBtn color="secondary" variant="tonal" prepend-icon="tabler-refresh" :disabled="isLoading"
                @click="resetForm">
                {{ $t('pages.settings.policies.policies.reset') }}
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
        <VCardTitle class="text-h5">{{ $t('pages.settings.policies.policies.deleteTemplate2') }}</VCardTitle>
      </VCardItem>

      <VCardText class="text-body-2 text-medium-emphasis">
        {{ $t('common.confirmations.areYouSureYouWantToDeleteThisContractTemplateThisActionCannotBeUndone') }}
      </VCardText>

      <VCardActions class="justify-center gap-3 mt-2">
        <VBtn color="secondary" variant="tonal" @click="deleteDialog = false">
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn color="error" variant="elevated" :loading="isLoading" @click="deleteTemplate">
          {{ $t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

</template>

<style lang="scss">
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
          padding-inline-end: 24px;
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
        font-family: inherit;
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

}

// System font choices render reliably without remote requests and include
// dependable Arabic/Persian fallbacks on Windows, macOS, and Linux.
$quill-fonts: (
  'arial': ('Arial', Arial, sans-serif),
  'times-new-roman': ('Times New Roman', 'Times New Roman', serif),
  'georgia': ('Georgia', Georgia, serif),
  'verdana': ('Verdana', Verdana, sans-serif),
  'tahoma': ('Tahoma (Persian)', Tahoma, sans-serif),
  'segoe-ui': ('Segoe UI (Persian)', 'Segoe UI', sans-serif)
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
    content: "Default" !important;
    font-family: inherit !important;
  }
}
</style>
