<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProfileSectionChip from './ProfileSectionChip.vue'
import { percentText } from '@/utils/percentText'
import { PROFILE_SECTION_ICONS } from '@/constants/entityProfile'

const props = defineProps({
  completion: { type: Object, required: true },
  sections: { type: Array, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['fix', 'complete'])
const { t, n } = useI18n()
const titleId = useId()
const percent = computed(() => props.completion.percent ?? 0)
const percentLabel = computed(() => percentText(n, t, percent.value))
const incomplete = computed(() => props.sections.some((section) => section.missingCount > 0))
</script>

<template>
  <section
    class="profile-completeness"
    :aria-labelledby="titleId"
    data-testid="profile-completeness"
  >
    <h2 :id="titleId" class="profile-completeness__title">
      {{ t('portal.profile.completion.title') }}
    </h2>
    <p class="profile-completeness__percent">
      <bdi dir="ltr">{{ percentLabel }}</bdi>
    </p>
    <div
      class="profile-completeness__track"
      role="progressbar"
      :aria-labelledby="titleId"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="percent"
      :aria-valuetext="percentLabel"
    >
      <svg class="profile-completeness__svg" focusable="false" aria-hidden="true">
        <rect class="profile-completeness__fill" :width="`${percent}%`" height="100%" />
      </svg>
    </div>
    <ul
      class="profile-completeness__rows"
      :aria-label="t('portal.profile.completion.sectionsLabel')"
    >
      <li v-for="section in sections" :key="section.key" class="profile-completeness__row">
        <span class="profile-completeness__icon" aria-hidden="true">
          <AppIcon :name="PROFILE_SECTION_ICONS[section.key]" />
        </span>
        <span class="profile-completeness__name">
          {{ t(`portal.profile.sections.${section.key}`) }}
        </span>
        <ProfileSectionChip :state="section.state" :missing-count="section.missingCount" />
        <button
          v-if="canEdit && section.missingCount > 0"
          type="button"
          class="profile-completeness__fix"
          :aria-label="
            t('portal.profile.completion.fixLabel', {
              section: t(`portal.profile.sections.${section.key}`),
            })
          "
          @click="emit('fix', section)"
        >
          {{ t('portal.profile.completion.fix') }}
          <AppIcon name="chevron-sm-right" class="profile-completeness__chevron" />
        </button>
      </li>
    </ul>
    <BaseButton
      v-if="canEdit && incomplete"
      variant="primary"
      icon="arrow-right"
      class="profile-completeness__action"
      data-testid="profile-complete-action"
      @click="emit('complete')"
    >
      {{ t('portal.profile.completion.action') }}
    </BaseButton>
  </section>
</template>
