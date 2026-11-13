<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import { PROFILE_SECTION_STATES } from '@/constants/entityProfile'

const props = defineProps({
  state: { type: String, required: true },
  missingCount: { type: Number, default: 0 },
})

const { t, n } = useI18n()
const complete = computed(() => props.state === PROFILE_SECTION_STATES.COMPLETE)
const count = computed(() => formatNumber(n, props.missingCount))
</script>

<template>
  <span
    class="profile-chip"
    :class="complete ? 'profile-chip--complete' : 'profile-chip--missing'"
    data-testid="profile-section-state"
  >
    <span class="profile-chip__dot" aria-hidden="true"></span>
    <span aria-hidden="true">{{
      complete ? t('portal.profile.state.complete') : t('portal.profile.state.missing', { count })
    }}</span>
    <span class="profile-chip__sr">{{
      complete
        ? t('portal.profile.state.complete')
        : t('portal.profile.state.missingLabel', { count })
    }}</span>
  </span>
</template>
