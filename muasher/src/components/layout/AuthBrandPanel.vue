<script setup>
import { computed, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { usePlatformIdentity } from '@/composables/usePlatformIdentity'
import { useHeroDots } from '@/composables/landing/useHeroDots'

const props = defineProps({
  interactive: { type: Boolean, default: false },
})

const { t } = useI18n()
const { platformName } = usePlatformIdentity()
const { logoOnDark } = useBrandLogo()

const panelRef = useTemplateRef('panel')
const patternRef = useTemplateRef('pattern')
const canvasRef = useTemplateRef('canvas')
const emblemRef = useTemplateRef('emblem')
const bandIsDark = computed(() => true)

if (props.interactive) {
  useHeroDots({
    canvasRef,
    pinRef: panelRef,
    copyRef: emblemRef,
    patternRef,
    isDark: bandIsDark,
  })
}
</script>

<template>
  <aside ref="panel" class="auth-layout__brand" :aria-label="t('auth.panel.label')">
    <div ref="pattern" class="auth-layout__pattern" aria-hidden="true"></div>
    <div class="auth-layout__brand-inner">
      <canvas v-if="interactive" ref="canvas" class="auth-layout__dots" aria-hidden="true"></canvas>
      <div ref="emblem" class="auth-layout__emblem">
        <span class="auth-layout__halo" aria-hidden="true"></span>
        <span class="auth-layout__orbit" aria-hidden="true"></span>
        <img class="auth-layout__logo" :src="logoOnDark" :alt="platformName" />
        <span class="auth-layout__sheen" aria-hidden="true"></span>
      </div>
    </div>
  </aside>
</template>
