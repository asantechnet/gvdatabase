<script setup lang="ts">
interface Props {
  isDialogVisible: boolean
  permissionName?: string
}
interface Emit {
  (e: 'update:isDialogVisible', value: boolean): void
  (e: 'update:permissionName', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  permissionName: '',
})

const emit = defineEmits<Emit>()

const currentPermissionName = ref('')

const onReset = () => {
  emit('update:isDialogVisible', false)
  currentPermissionName.value = ''
}

const onSubmit = () => {
  emit('update:isDialogVisible', false)
  emit('update:permissionName', currentPermissionName.value)
}

watch(() => props, () => {
  currentPermissionName.value = props.permissionName
})
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 600"
    :model-value="props.isDialogVisible"
    @update:model-value="onReset"
  >
    <!-- 👉 dialog close btn -->
    <DialogCloseBtn @click="onReset" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ $t(props.permissionName
            ? 'components.dialogs.addeditpermissiondialog.editPermission'
            : 'components.dialogs.addeditpermissiondialog.addPermission') }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ $t(props.permissionName
            ? 'components.dialogs.addeditpermissiondialog.editDescription'
            : 'components.dialogs.addeditpermissiondialog.addDescription') }}
        </p>

        <!-- 👉 Form -->
        <VForm>
          <VAlert
            type="warning"
            :title="$t('components.dialogs.addeditpermissiondialog.warning')"
            variant="tonal"
            class="mb-6"
          >
            <template #text>
              {{ $t('components.dialogs.addeditpermissiondialog.warningText', {
                action: $t(props.permissionName ? 'common.actions.editing' : 'common.actions.adding'),
              }) }}
            </template>
          </VAlert>

          <!-- 👉 Role name -->
          <div class="d-flex gap-4 mb-6 flex-wrap flex-column flex-sm-row">
            <AppTextField
              v-model="currentPermissionName"
              :placeholder="$t('components.dialogs.addeditpermissiondialog.enterPermissionName')"
            />

            <VBtn @click="onSubmit">
              {{ props.permissionName ? 'Update' : 'Add' }}
            </VBtn>
          </div>

          <VCheckbox :label="$t('components.dialogs.addeditpermissiondialog.setAsCorePermission')" />
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.permission-table {
  td {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding-block: 0.5rem;
    padding-inline: 0;
  }
}
</style>
