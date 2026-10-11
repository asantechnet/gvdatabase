import SettingApi from '@/api/setting'

// Kept at module scope so the layout, profile menu, and setup screens share
// one profile request and react immediately after the setting is saved.
const enabled = ref<boolean | null>(null)
let pending: Promise<boolean> | null = null

async function load() {
  if (enabled.value !== null)
    return enabled.value
  if (!pending) {
    pending = SettingApi.getCompanyProfile()
      .then(response => enabled.value = response.data?.automatic_attendance_enabled ?? false)
      .catch(() => false)
      .finally(() => pending = null)
  }

  return pending
}

export function useAutomaticAttendance() {
  return {
    enabled,
    load,
    setEnabled: (value: boolean) => enabled.value = value,
  }
}
