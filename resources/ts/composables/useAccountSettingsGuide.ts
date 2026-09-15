import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'
import { currentLocale, translate } from '@/utils/localization'

type AccountSettingsTab = 'account' | 'security' | 'notifications' | 'work-history' | 'deactivation-requests'

const driverProgressText = () => ({
  en: '{{current}} of {{total}}',
  'fa-AF': '{{current}} از {{total}}',
  'ps-AF': '{{current}} له {{total}}',
}[currentLocale()] ?? '{{current}} of {{total}}')

interface GuideStep {
  element?: string
  title: string
  description: string
  side?: 'top' | 'right' | 'bottom' | 'left'
}

const guideSteps: Record<AccountSettingsTab, GuideStep[]> = {
  account: [
    {
      element: '[data-account-guide="navigation"]',
      title: 'Account settings',
      description: 'Use these sections to manage your profile, security, notifications, and personal work history. This guide stays on the section you have open.',
      side: 'right',
    },
    {
      element: '[data-account-guide="profile-photo"]',
      title: 'Profile photo',
      description: 'Upload a clear work photo so teammates can recognize you. The file picker only accepts common image formats.',
      side: 'bottom',
    },
    {
      element: '[data-account-guide="profile-details"]',
      title: 'Profile details',
      description: 'Keep your name and work contact details current. Save changes only after reviewing the fields you changed.',
      side: 'top',
    },
    {
      element: '[data-account-guide="account-status"]',
      title: 'Account status',
      description: 'This area contains account-level actions, including the controlled deactivation workflow when your role allows it.',
      side: 'top',
    },
  ],
  security: [
    {
      element: '[data-account-guide="navigation"]',
      title: 'Security settings',
      description: 'This guide covers only the security section currently on screen. Your profile and notification settings are not included.',
      side: 'right',
    },
    {
      element: '[data-account-guide="change-password"]',
      title: 'Change your password',
      description: 'Confirm your current password, then choose and confirm a new one. Saving a new password signs out other active sessions automatically.',
      side: 'bottom',
    },
    {
      element: '[data-account-guide="two-factor"]',
      title: 'Two-step verification',
      description: 'Add an authenticator app for a second sign-in check. Keep its recovery access safe before enabling it.',
      side: 'top',
    },
    {
      element: '[data-account-guide="sessions"]',
      title: 'Other signed-in devices',
      description: 'If a device is lost or shared, enter your current password and sign out every other session. This browser stays signed in.',
      side: 'top',
    },
  ],
  notifications: [
    {
      element: '[data-account-guide="navigation"]',
      title: 'Notification preferences',
      description: 'This guide is limited to the notification preferences currently open.',
      side: 'right',
    },
    {
      element: '[data-account-guide="notification-events"]',
      title: 'Choose meaningful alerts',
      description: 'Turn on the event categories you want to see in your in-app inbox. These controls do not affect other users.',
      side: 'top',
    },
    {
      element: '[data-account-guide="notification-actions"]',
      title: 'Save or discard',
      description: 'Save applies your choices. Discard restores the preferences that were loaded when you opened this page.',
      side: 'top',
    },
  ],
  'work-history': [
    {
      element: '[data-account-guide="navigation"]',
      title: 'My work history',
      description: 'This guide explains the work-history section currently open.',
      side: 'right',
    },
    {
      element: '[data-account-guide="work-history-filter"]',
      title: 'Pick a month',
      description: 'Use this month picker to review your attendance and reports for a specific period.',
      side: 'bottom',
    },
    {
      element: '[data-account-guide="work-history-table"]',
      title: 'Review each workday',
      description: 'Each row shows your attendance status, work duration, and a report link when a daily report is available.',
      side: 'top',
    },
  ],
  'deactivation-requests': [
    {
      element: '[data-account-guide="navigation"]',
      title: 'Deactivation requests',
      description: 'This guide stays on the review queue currently open.',
      side: 'right',
    },
  ],
}

/** Opens a short tour for the account-settings section that is currently rendered. */
export function startAccountSettingsGuide(tab: string) {
  const steps = guideSteps[tab as AccountSettingsTab] ?? guideSteps.account
  const visibleSteps = steps.filter(step => !step.element || document.querySelector(step.element))

  if (!visibleSteps.length)
    return

  const pageGuide = driver({
    animate: true,
    allowClose: true,
    overlayOpacity: 0.55,
    showProgress: true,
    progressText: driverProgressText(),
    nextBtnText: translate('common.pageGuide.next'),
    prevBtnText: translate('common.pageGuide.back'),
    doneBtnText: translate('common.pageGuide.done'),
    steps: visibleSteps.map((step, index) => ({
      element: step.element,
      popover: {
        title: currentLocale() === 'en' ? step.title : translate('common.pageGuide.accountStepTitle', { step: index + 1 }),
        description: currentLocale() === 'en' ? step.description : translate('common.pageGuide.accountStepDescription'),
        side: step.side,
        align: 'center',
      },
    })),
  })

  pageGuide.drive()
}
