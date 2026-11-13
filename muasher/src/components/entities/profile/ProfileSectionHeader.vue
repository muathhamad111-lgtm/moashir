<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProfileSectionChip from './ProfileSectionChip.vue'
import { PROFILE_SECTION_ICONS } from '@/constants/entityProfile'

defineProps({
  section: { type: Object, required: true },
  titleId: { type: String, required: true },
  editing: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['edit'])
const { t } = useI18n()
</script>

<template>
  <header class="profile-section__header">
    <div class="profile-section__heading">
      <span class="profile-section__icon" aria-hidden="true">
        <AppIcon :name="PROFILE_SECTION_ICONS[section.key]" />
      </span>
      <h2 :id="titleId" class="profile-section__title">
        {{ t(`portal.profile.sections.${section.key}`) }}
      </h2>
      <ProfileSectionChip :state="section.state" :missing-count="section.missingCount" />
    </div>
    <span v-if="editing" class="profile-chip profile-chip--editing">
      <span class="profile-chip__dot" aria-hidden="true"></span>
      {{ t('portal.profile.state.editing') }}
    </span>
    <BaseButton
      v-else-if="canEdit"
      class="profile-section__edit"
      variant="secondary"
      :aria-label="
        t('portal.profile.actions.editLabel', {
          section: t(`portal.profile.sections.${section.key}`),
        })
      "
      @click="emit('edit')"
    >
      {{ t('portal.profile.actions.edit') }}
    </BaseButton>
    <span v-else class="profile-section__locked">
      <AppIcon name="lock" />
      {{ t('portal.profile.state.locked') }}
    </span>
  </header>
</template>
