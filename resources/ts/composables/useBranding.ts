import { ref } from 'vue'
import { useTheme } from 'vuetify'
import fallbackLogo from '@images/logo.svg'
import SettingApi from '@/api/setting'
import { contrastingTextColor } from '@/utils/themeContrast'
import { type ThemeColorPreferences, persistThemeColors, readStoredThemeColors, themeColorFields } from '@/utils/themePersistence'

const logoUrl = ref(fallbackLogo)
const shortCompanyName = ref('Asantech')
const originalCompanyName = ref('Asantech')
let brandingRequest: Promise<void> | null = null

interface BrandingSettings {
  website_logo_url?: string | null
  website_title?: string
  short_company_name?: string | null
  original_company_name?: string | null
  company_email?: string | null
  timezone?: string
  contract_letterhead_url?: string | null
  contract_cover_design_url?: string | null
  primary_color?: string | null
  secondary_color?: string | null
  tertiary_color?: string | null
}

export const useBranding = () => {
  const vuetifyTheme = useTheme()

  const applyBranding = (branding: BrandingSettings, persistApiFallbacks = false) => {
    logoUrl.value = branding.website_logo_url || fallbackLogo
    shortCompanyName.value = branding.short_company_name || branding.website_title || shortCompanyName.value
    originalCompanyName.value = branding.original_company_name || branding.website_title || originalCompanyName.value

    const storedThemeColors = readStoredThemeColors()

    const apiThemeColors = themeColorFields.reduce<ThemeColorPreferences>((colors, field) => {
      const value = branding[field]
      if (value)
        colors[field] = value

      return colors
    }, {})

    const resolvedThemeColors = { ...apiThemeColors, ...storedThemeColors }

    if (persistApiFallbacks) {
      const missingStoredColors = themeColorFields.reduce<ThemeColorPreferences>((colors, field) => {
        if (!storedThemeColors[field] && apiThemeColors[field])
          colors[field] = apiThemeColors[field]

        return colors
      }, {})

      persistThemeColors(missingStoredColors)
    }

    Object.values(vuetifyTheme.themes.value).forEach(theme => {
      if (resolvedThemeColors.primary_color) {
        theme.colors.primary = resolvedThemeColors.primary_color
        theme.colors['on-primary'] = contrastingTextColor(resolvedThemeColors.primary_color)
      }
      if (resolvedThemeColors.secondary_color) {
        theme.colors.secondary = resolvedThemeColors.secondary_color
        theme.colors['on-secondary'] = contrastingTextColor(resolvedThemeColors.secondary_color)
      }
      if (resolvedThemeColors.tertiary_color) {
        theme.colors.tertiary = resolvedThemeColors.tertiary_color
        theme.colors['on-tertiary'] = contrastingTextColor(resolvedThemeColors.tertiary_color)
      }
    })

  }

  const loadBranding = async () => {
    if (!brandingRequest) {
      brandingRequest = SettingApi.getCompanyProfile()
        .then(({ data }) => {
          if (data)
            applyBranding(data, true)
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
