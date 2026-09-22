import { ref } from 'vue'
import type { HorizontalNavItems, NavGroup, NavLink } from '@layouts/types'
import ServiceApi from '@/api/services'

const isGroup = (item: NavGroup | NavLink): item is NavGroup => 'children' in item

/** Hydrates the Contracts menu with active catalogue services after sign-in. */
export function useServiceContractNavigation(baseItems: HorizontalNavItems) {
  const navItems = ref<HorizontalNavItems>(baseItems)

  async function loadServiceContracts() {
    try {
      const { data: services } = await ServiceApi.getAll()
      const serviceLinks: NavLink[] = services
        .filter(service => service.status === 'active')
        .map(service => ({
          title: service.name || service.type,
          isTitleTranslationKey: false,
          icon: { icon: 'tabler-briefcase' },
          to: { name: 'contracts', query: { serviceId: String(service.id), serviceName: service.name || service.type } },
          action: 'read',
          subject: 'view_contracts',
        }))

      const fallbackLinks: NavLink[] = [
        { title: 'common.navigation.unassignedContracts', icon: { icon: 'tabler-file-off' }, to: { name: 'contracts', query: { withoutService: '1' } }, action: 'read', subject: 'view_contracts' },
        { title: 'common.navigation.otherContracts', icon: { icon: 'tabler-files' }, to: 'other-contracts', action: 'read', subject: 'view_contracts' },
      ]

      navItems.value = baseItems.map(item => {
        if (!isGroup(item) || item.title !== 'common.navigation.contract') return item

        return { ...item, children: [...serviceLinks, ...fallbackLinks] }
      })
    }
    catch {
      // Keep the static Contracts fallback when the service catalogue is not available.
    }
  }

  return { navItems, loadServiceContracts }
}
