import { ref } from 'vue'
import { useTheme } from 'vuetify'
import fallbackLogo from '@images/logo.svg'
import SettingApi from '@/api/setting'

const logoUrl = ref(fallbackLogo)
const shortCompanyName = ref('Asantech')
const originalCompanyName = ref('Asantech')
let brandingRequest: Promise<void> | null = null

interface BrandingSettings {
  website_logo_url?: string | null
  website_title?: string
  short_company_name?: string | null
  original_company_name?: string | null
  navigation_clr?: string
  footer_clr?: string
  cards_clr?: string
}

export const useBranding = () => {
  const vuetifyTheme = useTheme()

  const applyBranding = (branding: BrandingSettings) => {
    logoUrl.value = branding.website_logo_url || fallbackLogo
    shortCompanyName.value = branding.short_company_name || branding.website_title || shortCompanyName.value
    originalCompanyName.value = branding.original_company_name || branding.website_title || originalCompanyName.value

    const theme = vuetifyTheme.themes.value[vuetifyTheme.name.value]
    if (!theme) return

    if (branding.navigation_clr) {
      theme.colors.header = branding.navigation_clr
      document.documentElement.style.setProperty('--app-navigation-color', branding.navigation_clr)
    }
    if (branding.footer_clr) {
      theme.colors.footer = branding.footer_clr
      document.documentElement.style.setProperty('--app-footer-color', branding.footer_clr)
    }
    if (branding.cards_clr)
      theme.colors.surface = branding.cards_clr
  }

  const loadBranding = async () => {
    if (!brandingRequest) {
      brandingRequest = SettingApi.getAll()
        .then(({ data }) => {
          const setting = data[0]
          if (setting) applyBranding(setting)
        })
        .catch(() => {
          // Keep the built-in branding when settings are not available yet.
        })
    }

    await brandingRequest
  }

  return {
    logoUrl,
    shortCompanyName,
    originalCompanyName,
    loadBranding,
    updateBranding: applyBranding,
  }
}
