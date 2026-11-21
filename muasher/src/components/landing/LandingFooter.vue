<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePublicHome } from '@/composables/useHomeContent'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { useSiteBranding } from '@/composables/useSiteBranding'
import { usePlatformIdentity } from '@/composables/usePlatformIdentity'
import { FOOTER_LEGAL_LINKS } from '@/constants/footer'
import LegalLink from '@/components/legal/LegalLink.vue'
import LandingLink from '@/components/landing/LandingLink.vue'
import ttsColor from '@/assets/landing/logos/tts-company-color.webp'
import ttsWhite from '@/assets/landing/logos/tts-company-white.webp'

const TTS_URL = 'https://mytts.sa'

const { t } = useI18n()
const { siteSettings, localized } = usePublicHome({ auto: false })
const { logoOnDark, logoOnLight } = useBrandLogo()
const { siteLogo } = useSiteBranding()
const { platformName, platformDescription } = usePlatformIdentity()

const year = new Date().getFullYear()
const lightLogo = computed(() => siteLogo.value || logoOnLight)
const darkLogo = computed(() => siteLogo.value || logoOnDark)
const quickLinks = computed(() =>
  (siteSettings.value?.quickLinks ?? []).map((link) => ({
    key: link.url,
    url: link.url,
    label: localized(link.label),
  })),
)
const copyright = computed(
  () => localized(siteSettings.value?.footerNote) || t('footer.copyright', { year }),
)
</script>

<template>
  <footer class="lp-foot">
    <div class="lp-wrap">
      <div class="lp-foot__top">
        <div class="lp-foot__brand">
          <img
            class="lp-only-light"
            :src="lightLogo"
            :alt="platformName"
            width="132"
            height="77"
            loading="lazy"
          />
          <img
            class="lp-only-dark"
            :src="darkLogo"
            :alt="platformName"
            width="132"
            height="77"
            loading="lazy"
          />
          <p>{{ platformDescription }}</p>
        </div>
        <nav v-if="quickLinks.length" class="lp-foot__nav" :aria-label="t('nav.footerLabel')">
          <h2 class="lp-foot__title">{{ t('footer.quickLinks') }}</h2>
          <LandingLink v-for="link in quickLinks" :key="link.key" :to="link.url">
            {{ link.label }}
          </LandingLink>
        </nav>
        <nav class="lp-foot__nav lp-foot__nav--legal" :aria-label="t('footer.legalTitle')">
          <h2 class="lp-foot__title">{{ t('footer.legalTitle') }}</h2>
          <LegalLink
            v-for="kind in FOOTER_LEGAL_LINKS"
            :key="kind"
            :kind="kind"
            link-class="lp-foot__legal"
            soon-class="lp-foot__soon"
          />
        </nav>
      </div>
      <div class="lp-foot__base">
        <span>{{ copyright }}</span>
        <span class="lp-foot__dev">
          {{ t('landing.footer.developedBy') }}
          <a
            class="lp-foot__tts"
            :href="TTS_URL"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="t('landing.footer.developer')"
          >
            <img
              class="lp-only-light"
              :src="ttsColor"
              :alt="t('landing.footer.developer')"
              width="360"
              height="187"
              loading="lazy"
            />
            <img
              class="lp-only-dark"
              :src="ttsWhite"
              :alt="t('landing.footer.developer')"
              width="360"
              height="187"
              loading="lazy"
            />
          </a>
        </span>
      </div>
    </div>
  </footer>
</template>
