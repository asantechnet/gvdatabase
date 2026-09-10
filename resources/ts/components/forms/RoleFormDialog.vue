<script setup lang="ts">
import { computed, ref, watch } from 'vue'

interface PermissionItem { name: string; category: string }
const props = defineProps<{ modelValue: boolean; roleName: string; selected: string[]; permissions: PermissionItem[]; editing: boolean; protectedRole: boolean; loading: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [value: { name: string; permissions: string[] }] }>()
const name = ref('')
const selectedPermissions = ref<string[]>([])
const search = ref('')
const expanded = ref<Record<string, boolean>>({})
const categoryLabel = (category: string) => category.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())

const grouped = computed(() => props.permissions.filter(permission => {
  const q = search.value.trim().toLowerCase().replace(/[_\s]+/g, '')

  return !q || permission.name.toLowerCase().replace(/[_\s]+/g, '').includes(q) || categoryLabel(permission.category).toLowerCase().replace(/[_\s]+/g, '').includes(q)
}).reduce<Record<string, PermissionItem[]>>((groups, permission) => {
  (groups[permission.category] ??= []).push(permission)

  return groups
}, {}))

const allSelected = computed({ get: () => props.permissions.length > 0 && props.permissions.every(permission => selectedPermissions.value.includes(permission.name)), set: value => { selectedPermissions.value = value ? props.permissions.map(permission => permission.name) : [] } })
function isExpanded(category: string) { return search.value.trim() ? true : expanded.value[category] ?? true }
function toggle(category: string) { expanded.value[category] = !isExpanded(category) }
watch(() => props.modelValue, open => { if (open) { name.value = props.roleName; selectedPermissions.value = [...props.selected]; search.value = '' } })
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="800"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="editing ? 'Edit Role' : 'Create Role'">
      <VForm @submit.prevent="emit('submit', { name, permissions: selectedPermissions })">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              class="mb-2"
            >
              <VTextField
                v-model="name"
                label="Role Name"
                placeholder="e.g. editor"
                required
                :disabled="protectedRole"
                hide-details
              />
            </VCol><VCol cols="12">
              <h6 class="text-h6 mb-3 mt-2">
                Role Permissions
              </h6><div class="border rounded bg-surface">
                <div class="d-flex justify-space-between align-center px-4 py-3 border-b bg-var-theme-background">
                  <span class="text-subtitle-1 font-weight-medium d-flex align-center gap-2"><VIcon
                    icon="tabler-list-check"
                    size="20"
                    color="primary"
                  />Access Privileges Checklist</span><VCheckbox
                    v-model="allSelected"
                    label="Select All"
                    density="compact"
                    hide-details
                    color="primary"
                    :disabled="protectedRole"
                  />
                </div><div class="px-4 py-3 border-b">
                  <VTextField
                    v-model="search"
                    label="Search permissions"
                    placeholder="Search by name or category"
                    prepend-inner-icon="tabler-search"
                    density="compact"
                    clearable
                    hide-details
                    :disabled="protectedRole"
                  />
                </div><VList
                  lines="one"
                  select-strategy="classic"
                  class="py-0"
                >
                  <template
                    v-for="(items, category) in grouped"
                    :key="category"
                  >
                    <VListSubheader
                      class="bg-var-theme-background text-uppercase font-weight-bold text-primary py-2 mt-0 d-flex justify-space-between align-center cursor-pointer"
                      @click="toggle(category)"
                    >
                      <span>{{ categoryLabel(category) }}</span><VIcon
                        size="18"
                        class="me-1"
                        :icon="isExpanded(category) ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                      />
                    </VListSubheader><template v-if="isExpanded(category)">
                      <VListItem
                        v-for="permission in items"
                        :key="permission.name"
                        class="border-b"
                        :disabled="protectedRole"
                      >
                        <template #prepend>
                          <VCheckboxBtn
                            v-model="selectedPermissions"
                            :value="permission.name"
                            color="primary"
                            :disabled="protectedRole"
                          />
                        </template><VListItemTitle
                          class="font-weight-medium transition-all"
                          :class="selectedPermissions.includes(permission.name) ? 'text-high-emphasis' : 'text-medium-emphasis'"
                        >
                          {{ permission.name.replace(`${category}_`, '').replaceAll('_', ' ') }}
                        </VListItemTitle>
                      </VListItem>
                    </template>
                  </template>
                </VList>
              </div>
            </VCol>
          </VRow>
        </VCardText><VDivider /><VCardActions>
          <VSpacer /><VBtn
            variant="tonal"
            @click="emit('update:modelValue', false)"
          >
            Cancel
          </VBtn><VBtn
            :loading="loading"
            type="submit"
            :disabled="protectedRole"
          >
            {{ editing ? 'Save' : 'Create' }}
          </VBtn>
        </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>
