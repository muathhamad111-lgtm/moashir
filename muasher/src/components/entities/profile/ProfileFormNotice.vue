<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { fieldLabelKey } from '@/utils/entityProfile/sections'
import { PROFILE_SAVE_STATES as S } from '@/constants/entityProfile'

const props = defineProps({
  status: { type: String, required: true },
  errorFields: { type: Array, default: () => [] },
})

const { t } = useI18n()
const labels = computed(() =>
  props.errorFields.map((name) => t(fieldLabelKey(name))).join(t('portal.profile.listSeparator')),
)
</script>

<template>
  <div
    v-if="status === S.ERROR && errorFields.length"
    class="profile-notice profile-notice--danger"
    role="alert"
    data-testid="profile-error-summary"
  >
    <AppIcon name="warning-triangle" class="profile-notice__icon" />
    <div>
      <p class="profile-notice__title">{{ t('portal.profile.errors.summary') }}</p>
      <p class="profile-notice__text">{{ labels }}</p>
    </div>
  </div>
  <div
    v-else-if="status === S.STALE"
    class="profile-notice profile-notice--warning"
    role="alert"
    data-testid="profile-stale"
  >
    <AppIcon name="refresh" class="profile-notice__icon" />
    <p class="profile-notice__title">{{ t('portal.profile.errors.stale') }}</p>
  </div>
</template>
