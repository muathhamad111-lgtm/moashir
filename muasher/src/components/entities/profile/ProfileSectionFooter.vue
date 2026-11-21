<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { PROFILE_SAVE_STATES as S } from '@/constants/entityProfile'

defineProps({
  status: { type: String, default: S.IDLE },
  dirty: { type: Boolean, default: false },
  editing: { type: Boolean, default: false },
  saved: { type: Boolean, default: false },
})

const emit = defineEmits(['history'])
const { t } = useI18n()
</script>

<template>
  <footer class="profile-section__footer" :class="{ 'profile-section__footer--edit': editing }">
    <p class="profile-section__status" aria-live="polite">
      <template v-if="editing && status === S.ERROR">
        <span class="profile-section__status-text profile-section__status-text--danger">
          <AppIcon name="warning-triangle" />{{ t('portal.profile.footer.error') }}
        </span>
      </template>
      <template v-else-if="editing && dirty">
        <span class="profile-section__status-text profile-section__status-text--warning">
          <AppIcon name="info-circle" />{{ t('portal.profile.footer.unsaved') }}
        </span>
      </template>
      <template v-else-if="saved">
        <span class="profile-section__status-text profile-section__status-text--success">
          <AppIcon name="check-circle" />{{ t('portal.profile.footer.saved') }}
        </span>
      </template>
    </p>
    <div v-if="editing" class="profile-section__actions">
      <slot />
    </div>
    <button v-else type="button" class="profile-section__history" @click="emit('history')">
      <AppIcon name="clock" />
      <span>{{ t('portal.profile.actions.sectionHistory') }}</span>
    </button>
  </footer>
</template>
