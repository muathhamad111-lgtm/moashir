<script setup>
import { useI18n } from 'vue-i18n'
import { FIXED_COLOR_GROUPS } from '@/constants/branding'
import Card from '@/components/ui/Card.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
</script>

<template>
  <Card class="brand-fixed">
    <template #header>
      <span class="brand-fixed__header">
        <span>{{ t('branding.fixed.title') }}</span>
        <span class="brand-fixed__lock">
          <AppIcon name="lock" />
          {{ t('branding.fixed.readOnly') }}
        </span>
      </span>
    </template>
    <p class="brand-colors__hint">{{ t('branding.fixed.hint') }}</p>
    <div class="brand-fixed__groups">
      <section v-for="group in FIXED_COLOR_GROUPS" :key="group.key" class="brand-fixed__group">
        <h3 class="brand-fixed__group-title">{{ t(`branding.fixed.groups.${group.key}`) }}</h3>
        <ul class="brand-fixed__list">
          <li v-for="item in group.items" :key="item" class="brand-fixed__item">
            <span class="brand-fixed__dot" :data-color="item" aria-hidden="true" />
            {{ t(`branding.fixed.colors.${item}`) }}
          </li>
        </ul>
      </section>
    </div>
  </Card>
</template>
