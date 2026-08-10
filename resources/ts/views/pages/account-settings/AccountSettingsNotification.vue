<script lang="ts" setup>
import AuthApi, { type Account, type NotificationPreferences } from '@/api/auth'
const recentDevices = ref(
  [
    {
      type: 'New for you',
      email: true,
      browser: true,
      app: true,
    },
    {
      type: 'Account activity',
      email: true,
      browser: true,
      app: true,
    },
    {
      type: 'A new browser used to sign in',
      email: true,
      browser: true,
      app: false,
    },
    {
      type: 'A new device is linked',
      email: true,
      browser: false,
      app: false,
    },
  ],
)

const selectedNotification = ref('Only when I\'m online')
const initialDevices = ref<NotificationPreferences>()
const saving = ref(false)
const message = ref('')
const error = ref('')

async function load() {
  try {
    const account = (await AuthApi.me()).data.data as Account
    if (account.notifications) recentDevices.value = Object.entries(account.notifications).map(([type, value]) => ({ type, ...value }))
    initialDevices.value = structuredClone(Object.fromEntries(recentDevices.value.map(({ type, ...value }) => [type, value])))
    selectedNotification.value = account.notification_frequency === 'anytime' ? 'Anytime' : 'Only when I\'m online'
  } catch (e: any) { error.value = e.message || 'Unable to load notification preferences.' }
}

async function save() {
  saving.value = true
  try {
    const notifications = Object.fromEntries(recentDevices.value.map(({ type, ...value }) => [type, value])) as NotificationPreferences
    await AuthApi.updateNotifications({ notifications, notification_frequency: selectedNotification.value === 'Anytime' ? 'anytime' : 'online' })
    initialDevices.value = structuredClone(notifications); message.value = 'Notification preferences saved.'; error.value = ''
  } catch (e: any) { error.value = e.message || 'Unable to save notification preferences.' }
  finally { saving.value = false }
}

function discard() { if (initialDevices.value) recentDevices.value = Object.entries(initialDevices.value).map(([type, value]) => ({ type, ...value })) }
onMounted(load)
</script>

<template>
  <VCard>
    <VAlert v-if="message" type="success" class="ma-4">{{ message }}</VAlert>
    <VAlert v-if="error" type="error" class="ma-4">{{ error }}</VAlert>
    <VCardItem>
      <VCardTitle>Recent Devices</VCardTitle>
      <p class="text-body-1 mb-0">
        We need permission from your browser to show notifications. <span class="text-primary cursor-pointer">Request Permission</span>
      </p>
    </VCardItem>

    <VCardText class="px-0">
      <VDivider />
      <VTable class="text-no-wrap rounded">
        <thead>
          <tr>
            <th scope="col">
              Type
            </th>
            <th scope="col">
              EMAIL
            </th>
            <th scope="col">
              BROWSER
            </th>
            <th scope="col">
              App
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="device in recentDevices"
            :key="device.type"
          >
            <td class="text-body-1 text-high-emphasis">
              {{ device.type }}
            </td>
            <td>
              <VCheckbox v-model="device.email" />
            </td>
            <td>
              <VCheckbox v-model="device.browser" />
            </td>
            <td>
              <VCheckbox v-model="device.app" />
            </td>
          </tr>
        </tbody>
      </VTable>
      <VDivider />
    </VCardText>

    <VCardText>
      <VForm @submit.prevent="save">
        <h6 class="text-body-1 font-weight-medium mb-6">
          When should we send you notifications?
        </h6>

        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedNotification"
              mandatory
              placeholder="Select an option"
              :items="['Only when I\'m online', 'Anytime']"
            />
          </VCol>
        </VRow>

        <div class="d-flex flex-wrap gap-4 mt-6">
          <VBtn type="submit" :loading="saving">
            Save Changes
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            type="reset"
            @click.prevent="discard"
          >
            Discard
          </VBtn>
        </div>
      </VForm>
    </VCardText>
  </VCard>
</template>
