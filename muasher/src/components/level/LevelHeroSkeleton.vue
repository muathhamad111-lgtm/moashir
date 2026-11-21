<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="level-hero__skeleton" data-testid="level-hero-skeleton">
    <span class="level-hero__sr">{{ t('level.hero.loading') }}</span>
    <div class="level-hero__skeleton-identity" aria-hidden="true">
      <span class="level-hero__bone level-hero__bone--medal"></span>
      <span class="level-hero__skeleton-lines">
        <span class="level-hero__bone level-hero__bone--name"></span>
        <span class="level-hero__bone level-hero__bone--chip"></span>
        <span class="level-hero__bone level-hero__bone--meta"></span>
        <span class="level-hero__bone level-hero__bone--action"></span>
      </span>
    </div>
    <div class="level-hero__skeleton-progress" aria-hidden="true">
      <span class="level-hero__skeleton-score">
        <span class="level-hero__bone level-hero__bone--figure"></span>
        <span class="level-hero__skeleton-lines">
          <span class="level-hero__bone level-hero__bone--caption"></span>
          <span class="level-hero__bone level-hero__bone--line"></span>
        </span>
      </span>
      <span class="level-hero__bone level-hero__bone--title"></span>
      <span class="level-hero__bone level-hero__bone--track"></span>
    </div>
  </div>
</template>
