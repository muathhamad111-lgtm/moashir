<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import PortalBreadcrumb from '@/components/portal/PortalBreadcrumb.vue'
import { usePathNames } from '@/composables/usePathNames'

const props = defineProps({
  pathKey: { type: String, required: true },
  title: { type: String, default: '' },
  lead: { type: String, default: '' },
  trail: { type: Array, default: () => [] },
})

const { t, te } = useI18n()
const { pathName, pathDescription } = usePathNames()

const leadFallbackKey = computed(() => {
  const key = `paths.pages.${props.pathKey}.lead`
  return te(key) ? key : ''
})
const leadText = computed(() => props.lead || pathDescription(props.pathKey, leadFallbackKey.value))
const breadcrumbs = computed(() => [
  {
    key: 'journey',
    label: t('portal.breadcrumb.journey'),
    to: { name: ROUTE_NAMES.APP_DASHBOARD },
  },
  ...(props.trail.length ? props.trail : [{ key: 'path', label: pathName(props.pathKey) }]),
])
</script>

<template>
  <header class="b-band path-header" :data-testid="`path-header-${pathKey}`">
    <div class="b-band__inner path-header__inner">
      <div class="path-header__intro">
        <PortalBreadcrumb class="path-header__crumbs" :items="breadcrumbs" />
        <h1 class="path-header__title">{{ title || pathName(pathKey) }}</h1>
        <p v-if="leadText" class="path-header__lead">{{ leadText }}</p>
        <slot name="chips" />
      </div>
      <div v-if="$slots.default" class="path-header__cards">
        <slot />
      </div>
    </div>
  </header>
</template>
