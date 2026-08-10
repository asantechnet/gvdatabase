<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useTheme } from 'vuetify'
import { useToast } from 'vue-toastification'
import { useAppApi } from '@/composables/useAppApi'
import { useBranding } from '@/composables/useBranding'
import type { SettingForm } from '@/api'

const toast = useToast()
const { settings } = useAppApi()
const { updateBranding } = useBranding()
const vuetifyTheme = useTheme()
const currentThemeName = computed(() => vuetifyTheme.name.value)

const defaultForm: SettingForm = {
  navigation_clr: '#ffffff',
  cards_clr: '#ffffff',
  footer_clr: '#ffffff',
  website_title: 'Asantech',
  website_logo: null,
  website_address: '',
  phone: '',
  physical_address: '',
  short_company_name: 'Asantech',
  original_company_name: 'Asantech',
}

const form = ref<SettingForm>({ ...defaultForm })
const settingId = ref<number | null>(null)
const logoPreview = ref<string>('/images/logo.png')
const isLoading = ref(false)
const isSaving = ref(false)

const applyTheme = () => {
  const theme = vuetifyTheme.themes.value[currentThemeName.value]
  if (!theme) return

  theme.colors.header = form.value.navigation_clr
  theme.colors.footer = form.value.footer_clr
  theme.colors.surface = form.value.cards_clr

  // Layout chrome does not consume Vuetify's custom `header` and `footer` keys.
  // CSS variables let the visible navbar, navigation, and footer repaint immediately.
  document.documentElement.style.setProperty('--app-navigation-color', form.value.navigation_clr)
  document.documentElement.style.setProperty('--app-footer-color', form.value.footer_clr)

  localStorage.setItem('theme-header-color', form.value.navigation_clr)
  localStorage.setItem('theme-footer-color', form.value.footer_clr)
  localStorage.setItem('theme-card-color', form.value.cards_clr)
}

watch(
  () => [form.value.navigation_clr, form.value.footer_clr, form.value.cards_clr],
  applyTheme,
  { immediate: true },
)

const loadSettings = async () => {
  isLoading.value = true

  try {
    const response = await settings.getAll()
    const existing = response.data[0]

    if (existing) {
      settingId.value = existing.id
      form.value = {
        navigation_clr: existing.navigation_clr,
        cards_clr: existing.cards_clr,
        footer_clr: existing.footer_clr,
        website_title: existing.website_title,
        website_logo: null,
        website_address: existing.website_address || '',
        phone: existing.phone || '',
        physical_address: existing.physical_address || '',
        short_company_name: existing.short_company_name || existing.website_title,
        original_company_name: existing.original_company_name || existing.website_title,
      }
      logoPreview.value = existing.website_logo_url || existing.website_logo || '/images/logo.png'
    } else {
      settingId.value = null
      form.value = { ...defaultForm }
      logoPreview.value = '/images/logo.png'
    }

    applyTheme()
  } catch (error: any) {
    toast.error(error?.message || 'Failed to load settings')
  } finally {
    isLoading.value = false
  }
}

const handleLogoUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (!file) return

  form.value.website_logo = file
  logoPreview.value = URL.createObjectURL(file)
}

const resetSettings = () => {
  form.value = { ...defaultForm }
  form.value.website_logo = null
  logoPreview.value = '/images/logo.png'
  applyTheme()
}

const saveSettings = async () => {
  isSaving.value = true

  try {
    const payload: SettingForm = { ...form.value }
    payload.website_logo = form.value.website_logo instanceof File ? form.value.website_logo : null

    let response
    if (settingId.value) {
      response = await settings.update(settingId.value, payload)
    } else {
      response = await settings.create(payload)
    }

    const saved = response.data
    settingId.value = saved.id
    form.value.website_logo = null
    logoPreview.value = saved.website_logo_url || saved.website_logo || '/images/logo.png'
    updateBranding(saved)
    toast.success('Settings saved successfully')
    applyTheme()
  } catch (error: any) {
    if (error?.errors) {
      Object.values(error.errors).flat().forEach((message) => toast.error(String(message)))
    } else {
      toast.error(error?.message || 'Failed to save settings')
    }
  } finally {
    isSaving.value = false
  }
}

onMounted(loadSettings)
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Setup Settings">
        <VCardText>
          <VRow>
            <VCol cols="12" md="4" class="text-center d-flex flex-column align-center justify-center border-md-e">
              <VAvatar rounded size="120" class="mb-4 elevation-2 bg-light">
                <VImg :src="logoPreview" alt="Website logo preview" cover />
              </VAvatar>

              <VBtn color="primary" variant="tonal" density="comfortable" class="position-relative">
                <VIcon icon="tabler-upload" class="me-2" />
                Upload New Logo
                <input
                  type="file"
                  accept="image/*"
                  class="position-absolute opacity-0 w-100 h-100 cursor-pointer"
                  style="top: 0; left: 0;"
                  @change="handleLogoUpload"
                >
              </VBtn>
              <p class="text-caption text-medium-emphasis mt-2">
                JPG, PNG, GIF, or WEBP up to 2MB
              </p>
            </VCol>

            <VCol cols="12" md="8" class="ps-md-6">
              <VRow>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.website_title" label="Website Title" placeholder="Asantech" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.short_company_name" label="Short Company Name" placeholder="Asantech" />
                </VCol>
                <VCol cols="12">
                  <VTextField v-model="form.original_company_name" label="Original Company Name" placeholder="Asantech Limited" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.website_address" label="Website Address" placeholder="https://example.com" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.phone" label="Phone" placeholder="+1 555 000 0000" />
                </VCol>
                <VCol cols="12">
                  <VTextarea v-model="form.physical_address" label="Physical Address" rows="2" auto-grow />
                </VCol>
              </VRow>

              <h4 class="text-h6 mb-4 mt-4 font-weight-medium">Layout Colors</h4>
              <VRow>
                <VCol cols="12" sm="4" class="d-flex flex-column align-center">
                  <span class="text-subtitle-2 mb-2">Header Navbar</span>
                  <VColorPicker v-model="form.navigation_clr" mode="hex" hide-inputs canvas-height="100" width="100%" />
                  <div class="text-caption font-mono mt-2 px-2 py-1 bg-initial rounded">{{ form.navigation_clr }}</div>
                </VCol>

                <VCol cols="12" sm="4" class="d-flex flex-column align-center">
                  <span class="text-subtitle-2 mb-2">Footer Bar</span>
                  <VColorPicker v-model="form.footer_clr" mode="hex" hide-inputs canvas-height="100" width="100%" />
                  <div class="text-caption font-mono mt-2 px-2 py-1 bg-initial rounded">{{ form.footer_clr }}</div>
                </VCol>

                <VCol cols="12" sm="4" class="d-flex flex-column align-center">
                  <span class="text-subtitle-2 mb-2">Cards & Surfaces</span>
                  <VColorPicker v-model="form.cards_clr" mode="hex" hide-inputs canvas-height="100" width="100%" />
                  <div class="text-caption font-mono mt-2 px-2 py-1 bg-initial rounded">{{ form.cards_clr }}</div>
                </VCol>
              </VRow>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider class="mt-4" />
        <VCardText class="d-flex gap-4 justify-end">
          <VBtn color="secondary" variant="tonal" :disabled="isLoading || isSaving" @click="resetSettings">
            Reset Defaults
          </VBtn>
          <VBtn color="primary" :loading="isSaving" :disabled="isLoading" @click="saveSettings">
            Save Settings
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}

</style>
