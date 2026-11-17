<script setup>
import { useI18n } from 'vue-i18n'
import { qrPatternPath, QR_SIZE } from '@/utils/landing/qrPattern'
import sealGold from '@/assets/landing/seals/seal-gold.webp'

defineProps({
  screen: { type: String, required: true },
})

const { t } = useI18n()
const qrPath = qrPatternPath()
const HOST = 'moashir.ttshub.live'
const EVIDENCE = [
  { key: 'plan', state: 'ok' },
  { key: 'governance', state: 'ok' },
  { key: 'satisfaction', state: 'wait' },
  { key: 'risks', state: 'need' },
]
const TIMELINE = [
  { key: 'sent', state: 'done' },
  { key: 'assigned', state: 'done' },
  { key: 'review', state: 'now' },
  { key: 'complete', state: 'next' },
]
const STAGES = [
  { key: 'high', state: 'ok', width: 'full' },
  { key: 'medium', state: 'wait', width: 'part' },
  { key: 'low', state: 'need', width: 'none' },
]
const PATHS = ['self', 'accredited', 'advanced']
</script>

<template>
  <div class="lp-screen" aria-hidden="true">
    <div class="lp-screen__bar">
      <span class="lp-screen__host" dir="ltr">{{ HOST }}</span
      ><span class="lp-screen__dot"></span><span class="lp-screen__dot"></span
      ><span class="lp-screen__dot"></span>
    </div>
    <div class="lp-screen__body">
      <template v-if="screen === 'register'">
        <h4>{{ t('landing.screens.register.title') }}</h4>
        <span v-for="field in ['name', 'type', 'region']" :key="field" class="lp-screen__field">
          <span>{{ t(`landing.screens.register.${field}`) }}</span>
          <em>{{ t(`landing.screens.register.${field}Value`) }}</em>
        </span>
        <span class="lp-screen__btn">{{ t('landing.screens.register.submit') }}</span>
      </template>

      <template v-else-if="screen === 'choose_path'">
        <h4>{{ t('landing.screens.paths.title') }}</h4>
        <p class="lp-screen__sub">{{ t('landing.screens.paths.subtitle') }}</p>
        <div
          v-for="path in PATHS"
          :key="path"
          class="lp-screen__row"
          :class="{ 'is-on': path === 'accredited' }"
        >
          <span class="lp-screen__ri">
            <svg viewBox="0 0 24 24"><use :href="`#lp-i-${path}`" /></svg>
            {{ t(`landing.screens.paths.${path}`) }}
          </span>
          <b v-if="path === 'self'" class="lp-st lp-st--ok">{{
            t('landing.screens.paths.free')
          }}</b>
          <b v-else-if="path === 'accredited'" class="lp-st lp-st--ok">
            <svg viewBox="0 0 24 24"><use href="#lp-i-check" /></svg>
          </b>
          <b v-else class="lp-st">{{ t('landing.screens.paths.cycleFee') }}</b>
        </div>
      </template>

      <template v-else-if="screen === 'evidence'">
        <h4>{{ t('landing.screens.evidence.title') }}</h4>
        <p class="lp-screen__sub">{{ t('landing.screens.evidence.progress') }}</p>
        <div class="lp-screen__meter"><span class="lp-screen__meter-fill is-most"></span></div>
        <div v-for="row in EVIDENCE" :key="row.key" class="lp-screen__row">
          <span>{{ t(`landing.screens.evidence.${row.key}`) }}</span>
          <b class="lp-st" :class="`lp-st--${row.state}`">
            {{ t(`landing.screens.evidence.states.${row.state}`) }}
          </b>
        </div>
      </template>

      <template v-else-if="screen === 'review'">
        <h4>{{ t('landing.screens.review.title') }}</h4>
        <ol class="lp-screen__timeline">
          <li v-for="step in TIMELINE" :key="step.key" :class="`is-${step.state}`">
            {{ t(`landing.screens.review.${step.key}`) }}
            <b v-if="step.state === 'now'">{{ t('landing.screens.review.now') }}</b>
          </li>
        </ol>
      </template>

      <template v-else-if="screen === 'result'">
        <h4>{{ t('landing.screens.result.title') }}</h4>
        <div class="lp-screen__result">
          <img :src="sealGold" alt="" width="70" height="70" loading="lazy" />
          <div>
            <b dir="ltr">{{ t('landing.screens.result.score') }}</b>
            <span>{{ t('landing.screens.result.level') }}</span>
          </div>
        </div>
        <div class="lp-screen__row">
          <span>{{ t('landing.screens.result.report') }}</span>
          <b class="lp-st lp-st--ok" dir="ltr">PDF</b>
        </div>
        <div class="lp-screen__row">
          <span>{{ t('landing.screens.result.qr') }}</span>
          <svg class="lp-screen__qr" :viewBox="`0 0 ${QR_SIZE} ${QR_SIZE}`">
            <path :d="qrPath" fill="currentColor" />
          </svg>
        </div>
      </template>

      <template v-else>
        <h4>{{ t('landing.screens.plan.title') }}</h4>
        <div v-for="stage in STAGES" :key="stage.key" class="lp-screen__stage">
          <div>
            <span>{{ t(`landing.screens.plan.${stage.key}`) }}</span>
            <b class="lp-st" :class="`lp-st--${stage.state}`">
              {{ t(`landing.screens.plan.states.${stage.state}`) }}
            </b>
          </div>
          <div class="lp-screen__meter">
            <span class="lp-screen__meter-fill" :class="`is-${stage.width}`"></span>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
