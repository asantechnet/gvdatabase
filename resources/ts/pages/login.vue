<script setup lang="ts">
import AuthApi from '@/api/auth'
import { useCookie } from '@core/composable/useCookie'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const form = ref({
  email: '',
  password: '',
  remember: false
})

const loading = ref(false)
const error = ref('')

const handleLogin = async () => {
  try {
    loading.value = true
    error.value = ''

    const response = await AuthApi.login({
      email: form.value.email,
      password: form.value.password
    })

    if (response.data && response.data.success) {
      // Set cookies for authentication
      const accessToken = response.data.data.access_token
      const userData = response.data.data.user

      const accessTokenCookie = useCookie<string | null>('accessToken')
      const userDataCookie = useCookie<Record<string, unknown> | null>('userData')

      accessTokenCookie.value = accessToken
      userDataCookie.value = userData

      // Also set in localStorage for axios interceptor
      localStorage.setItem('access_token', accessToken)
      if (response.data.data.refresh_token) {
        localStorage.setItem('refresh_token', response.data.data.refresh_token)
      }

      // Redirect to home
      await router.replace('/home')
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Login failed. Please check your credentials.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard class="auth-card pa-4 pt-7" max-width="448">
      <VCardItem class="justify-center">
        <template #prepend>
          <div class="d-flex">
            <VImg src="/logo.svg" width="32" height="32" />
          </div>
        </template>

        <VCardTitle class="text-h5 font-weight-bold">
          Asantech Mini Management
        </VCardTitle>
      </VCardItem>

      <VCardText class="pt-2">
        <h5 class="text-h5 mb-1">
          Welcome! 👋
        </h5>
        <p class="mb-0">
          Please sign-in to your account
        </p>
      </VCardText>

      <VCardText>
        <VForm @submit.prevent="handleLogin">
          <VRow>
            <!-- Email -->
            <VCol cols="12">
              <VTextField v-model="form.email" label="Email" type="email" placeholder="Enter your email"
                :error-messages="error && !form.email ? error : ''" />
            </VCol>

            <!-- Password -->
            <VCol cols="12">
              <VTextField v-model="form.password" label="Password" placeholder="••••••••"
                :type="form.remember ? 'text' : 'password'" :error-messages="error && form.email ? error : ''" />
            </VCol>

            <!-- Remember Me -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between flex-wrap gap-3">
                <VCheckbox v-model="form.remember" label="Remember me" />
                <a class="text-primary ms-2 mb-1" href="#">
                  Forgot Password?
                </a>
              </div>
            </VCol>

            <!-- Login Button -->
            <VCol cols="12">
              <VBtn block type="submit" :loading="loading">
                Login
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.auth-wrapper {
  min-block-size: 100vh;

  .auth-card {
    width: 100%;
    max-inline-size: 448px;
  }
}
</style>
