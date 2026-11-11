<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ProfileFieldCell from './ProfileFieldCell.vue'
import EmailChangeDialog from './EmailChangeDialog.vue'
import { useEmailChangeCancel } from '@/composables/useEmailChangeCancel'
import { fieldLabelKey } from '@/utils/entityProfile/sections'

const props = defineProps({
  field: { type: Object, required: true },
  profile: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['changed'])
const { t } = useI18n()
const dialogOpen = ref(false)
const { busy, cancelRequest } = useEmailChangeCancel()
const pending = computed(() => props.profile.pendingEmailChange)
</script>

<template>
  <ProfileFieldCell
    :label="t(fieldLabelKey(field.name))"
    :value="profile.officialEmail ?? ''"
    :missing="field.missing"
    ltr
    data-testid="profile-official-email"
  >
    <span v-if="pending" class="profile-field__pending" role="status">
      <StatusBadge :status="pending.status" />
      <span class="profile-field__note">
        {{ t('portal.profile.email.to') }}
        <bdi dir="ltr">{{ pending.newEmail }}</bdi>
      </span>
      <button
        v-if="canEdit"
        type="button"
        class="profile-field__action"
        :disabled="busy"
        data-testid="email-change-cancel"
        @click="cancelRequest"
      >
        {{ t('portal.profile.email.cancel') }}
      </button>
    </span>
    <button
      v-else-if="canEdit"
      type="button"
      class="profile-field__action"
      data-testid="email-change-open"
      @click="dialogOpen = true"
    >
      {{ t('portal.profile.email.change') }}
    </button>
    <EmailChangeDialog v-model="dialogOpen" @completed="emit('changed')" />
  </ProfileFieldCell>
</template>
