<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Card from '@/components/ui/Card.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { ndaService } from '@/services/ndaService'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  overview: { type: Object, required: true },
})

const emit = defineEmits(['saved'])

const { t } = useI18n()
const toast = useToast()
const roles = ref([])
const reminderDays = ref(3)
const reasonOpen = ref(false)

watch(
  () => props.overview,
  (overview) => {
    roles.value = [...overview.roles]
    reminderDays.value = overview.reminderDays
  },
  { immediate: true },
)

const dirty = computed(
  () =>
    reminderDays.value !== props.overview.reminderDays ||
    roles.value.length !== props.overview.roles.length ||
    roles.value.some((role) => !props.overview.roles.includes(role)),
)

const validDelay = computed(
  () =>
    Number.isInteger(reminderDays.value) &&
    reminderDays.value >= 1 &&
    reminderDays.value <= props.overview.maxReminderDays,
)

function toggle(role) {
  roles.value = roles.value.includes(role)
    ? roles.value.filter((item) => item !== role)
    : [...roles.value, role]
}

async function save(reason) {
  const { data } = await ndaService.updateSettings({
    roles: roles.value,
    reminderDays: reminderDays.value,
    reason,
  })
  toast.success(t('nda.admin.scope.saved'))
  emit('saved', data)
}
</script>

<template>
  <Card class="nda-scope" data-testid="nda-scope">
    <h2 class="legal-admin__title">{{ t('nda.admin.scope.title') }}</h2>
    <p class="nda-admin__note">{{ t('nda.admin.scope.hint') }}</p>
    <div class="nda-scope__chips" role="group" :aria-label="t('nda.admin.scope.title')">
      <button
        v-for="role in overview.availableRoles"
        :key="role"
        type="button"
        class="nda-scope__chip"
        :aria-pressed="roles.includes(role) ? 'true' : 'false'"
        @click="toggle(role)"
      >
        {{ t(`users.roleLabels.${role}`) }}
      </button>
    </div>
    <p class="nda-admin__note">{{ t('nda.admin.scope.superAdmin') }}</p>
    <BaseInput
      :model-value="String(reminderDays)"
      name="reminderDays"
      type="number"
      inputmode="numeric"
      input-dir="ltr"
      :label="t('nda.admin.scope.reminderDays')"
      :hint="t('nda.admin.scope.reminderHint', { max: overview.maxReminderDays })"
      data-testid="nda-reminder-days"
      @update:model-value="reminderDays = Number($event)"
    />
    <p v-if="dirty" class="nda-admin__note" aria-live="polite">
      {{ t('nda.admin.scope.unsaved') }}
    </p>
    <BaseButton
      variant="secondary"
      icon="save"
      :disabled="!dirty || !validDelay"
      @click="reasonOpen = true"
    >
      {{ t('nda.admin.scope.save') }}
    </BaseButton>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('nda.admin.scope.reasonTitle')"
      :submit-label="t('actions.save')"
      :action="save"
      :min-length="10"
      action-name="nda.admin.scope.save"
    />
  </Card>
</template>
