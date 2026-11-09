import SettingApi from '@/api/setting'

// Module-scope state so navigation, route guards, service pickers and the
// settings screen share one company-profile request and react immediately
// when the Academy switch is saved.
const enabled = ref<boolean | null>(null)
let pending: Promise<boolean> | null = null

async function load() {
  if (enabled.value !== null)
    return enabled.value
  if (!pending) {
    pending = SettingApi.getCompanyProfile()
      .then(response => enabled.value = response.data?.academy_enabled ?? false)
      .catch(() => false)
      .finally(() => pending = null)
  }

  return pending
}

export function useAcademyEnabled() {
  return {
    enabled,
    load,
    setEnabled: (value: boolean) => enabled.value = value,
  }
}
