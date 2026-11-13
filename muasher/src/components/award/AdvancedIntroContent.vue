<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdvancedIntroContent } from '@/composables/useIntroPageContent'
import { PUBLIC_PAGE_STATES } from '@/constants/cms'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { state, localized, highlights, groups } = useAdvancedIntroContent()

const visible = computed(
  () =>
    state.value === PUBLIC_PAGE_STATES.READY &&
    (highlights.value.length > 0 || groups.value.length > 0),
)

function groupTitle(group) {
  return (
    group.title || t(`advanced.content.groups.${group.type === 'winners' ? 'winner' : 'partner'}`)
  )
}
</script>

<template>
  <section v-if="visible" class="advanced-content" :aria-label="t('advanced.content.label')">
    <div v-if="highlights.length" class="advanced-content__highlights">
      <article v-for="card in highlights" :key="card.id" class="advanced-content__card">
        <h2 class="advanced-content__title">{{ card.title }}</h2>
        <p class="advanced-content__text">{{ card.body }}</p>
      </article>
    </div>
    <div v-for="group in groups" :key="group.id" class="advanced-content__group">
      <h2 class="advanced-content__title">{{ groupTitle(group) }}</h2>
      <ul class="advanced-content__list">
        <li v-for="item in group.items" :key="item.id" class="advanced-content__item">
          <AppIcon v-if="item.icon" :name="item.icon" />
          <span class="advanced-content__name">{{ localized(item.title) }}</span>
          <span class="advanced-content__note">{{ localized(item.description) }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
