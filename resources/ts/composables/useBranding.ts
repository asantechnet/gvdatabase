import { ref } from 'vue'
import { useTheme } from 'vuetify'
import fallbackLogo from '@images/logo.svg'
import SettingApi from '@/api/setting'
import { type ThemeColorPreferences, persistThemeColors, readStoredThemeColors, themeColorFields, themeWithPalette } from '@/utils/themePersistence'

const logoUrl = ref(fallbackLogo)
const isLogoSquare = ref<boolean | null>(null)
const shortCompanyName = ref('Educational Visa')
const originalCompanyName = ref('Educational Visa Services')
let brandingRequest: Promise<void> | null = null
let logoProbeId = 0

const setLogoShape = (width: number, height: number) => {
  if (width <= 0 || height <= 0)
    return

  // A tiny tolerance avoids treating images with a one-pixel export rounding
  // difference as rectangular while still detecting wide 1:2 brand marks.
  isLogoSquare.value = Math.abs(width - height) / Math.max(width, height) <= 0.02
}

const updateFavicon = (source: string | null) => {
  if (typeof document === 'undefined')
    return

  const href = source || '/favicon.svg'
  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')

  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }

  if (link.getAttribute('href') !== href)
    link.setAttribute('href', href)
}

const detectLogoShape = (source: string) => {
  if (typeof Image === 'undefined')
    return

  const probeId = ++logoProbeId
  const image = new Image()

  isLogoSquare.value = null
  image.onload = () => {
    if (probeId === logoProbeId)
      setLogoShape(image.naturalWidth, image.naturalHeight)
  }
  image.onerror = () => {
    if (probeId === logoProbeId)
      isLogoSquare.value = false
  }
  image.src = source
}

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
  light_background_color?: string | null
  light_surface_color?: string | null
  dark_background_color?: string | null
  dark_surface_color?: string | null
}

export const useBranding = () => {
  const vuetifyTheme = useTheme()

  const applyBranding = (branding: BrandingSettings, persistApiFallbacks = false) => {
    logoUrl.value = branding.website_logo_url || fallbackLogo
    detectLogoShape(logoUrl.value)
    updateFavicon(branding.website_logo_url || null)
    shortCompanyName.value = branding.short_company_name || branding.website_title || shortCompanyName.value
    originalCompanyName.value = branding.original_company_name || branding.website_title || originalCompanyName.value

    const storedThemeColors = readStoredThemeColors()

    const apiThemeColors = themeColorFields.reduce<ThemeColorPreferences>((colors, field) => {
      const value = branding[field]
      if (value)
        colors[field] = value

      return colors
    }, {})

    // Local storage prevents a flash during app boot, but the company record
    // remains authoritative once it is available (including for other users).
    const resolvedThemeColors = { ...storedThemeColors, ...apiThemeColors }

    if (persistApiFallbacks)
      persistThemeColors(apiThemeColors)

    Object.entries(vuetifyTheme.themes.value).forEach(([themeName, theme]) => {
      // Replace the whole definition so Vuetify updates every generated CSS
      // variable in one reactive change instead of exposing a half-updated
      // palette while the company profile request is resolving.
      vuetifyTheme.themes.value[themeName] = themeWithPalette(theme, resolvedThemeColors)
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
    isLogoSquare,
    shortCompanyName,
    originalCompanyName,
    handleLogoLoad: (event: Event) => {
      const image = event.currentTarget as HTMLImageElement | null

      if (image)
        setLogoShape(image.naturalWidth, image.naturalHeight)
    },
    loadBranding,
    updateBranding: applyBranding,
  }
}
