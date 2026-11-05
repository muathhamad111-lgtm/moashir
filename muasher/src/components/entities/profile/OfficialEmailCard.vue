<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmailChangeDialog from './EmailChangeDialog.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ENTITY_STATUSES } from '@/constants/entityEnums'

const props = defineProps({
  profile: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { can } = usePermissions()
const { dateTime } = useEntityDisplay()
const dialogOpen = ref(false)

const pending = computed(() => props.profile.pendingEmailChange)
const canChange = computed(
  () =>
    can(PERMISSIONS.ENTITY_PROFILE_EDIT) &&
    props.profile.status === ENTITY_STATUSES.ACTIVE &&
    !pending.value,
)
</script>

<template>
  <Card :title="t('entities.email.title')">
    <div class="official-email">
      <p class="official-email__current">
        <span class="official-email__label">{{ t('entities.fields.officialEmail') }}</span>
        <span class="official-email__value u-ltr">{{ profile.officialEmail }}</span>
      </p>
      <div v-if="pending" class="official-email__pending" role="status">
        <StatusBadge :status="pending.status" />
        <p class="official-email__pending-text">
          {{
            t('entities.email.pendingMessage', {
              email: pending.newEmail,
              date: dateTime(pending.requestedAt),
            })
          }}
        </p>
      </div>
      <p class="official-email__hint">{{ t('entities.email.hint') }}</p>
      <BaseButton
        v-if="canChange"
        variant="secondary"
        size="sm"
        icon="fa-solid fa-envelope-circle-check"
        @click="dialogOpen = true"
      >
        {{ t('entities.email.change') }}
      </BaseButton>
    </div>
    <EmailChangeDialog v-model="dialogOpen" @completed="emit('changed')" />
  </Card>
</template>
