<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePublicHome } from '@/composables/useHomeContent'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { SOCIAL_PLATFORMS } from '@/constants/socialPlatforms'
import { DEVELOPED_BY } from '@/constants/branding'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'
import { MOCK_MODULES } from '@/constants/mockModules'

const { t } = useI18n()
const router = useRouter()
const { themedLogo } = useBrandLogo()
const { siteSettings, sectionByKey, localized } = usePublicHome()

const year = new Date().getFullYear()

const quickLinks = computed(() => siteSettings.value?.quickLinks ?? [])
const contact = computed(() => siteSettings.value?.contact ?? null)
const footerNote = computed(() =>
  siteSettings.value?.footerNote ? localized(siteSettings.value.footerNote) : '',
)

const social = computed(() =>
  (siteSettings.value?.social ?? [])
    .map((link) => ({ ...link, meta: SOCIAL_PLATFORMS[link.platform] }))
    .filter((link) => link.meta && link.url),
)

const developedBy = computed(() => {
  const meta = sectionByKey('footer')?.meta?.developedBy
  return {
    label: meta?.label ? localized(meta.label) : t(DEVELOPED_BY.labelKey),
    url: meta?.url ?? DEVELOPED_BY.url,
  }
})

const email = computed(() => contact.value?.email ?? '')
const phone = computed(() => contact.value?.phone ?? '')

function onQuickLink(event, url) {
  if (!url || /^https?:\/\//.test(url) || url.startsWith('mailto:') || url.startsWith('tel:'))
    return
  if (url.startsWith('#')) {
    const target = document.getElementById(url.slice(1))
    if (target) {
      event.preventDefault()
      target.scrollIntoView({ behavior: 'smooth' })
    }
    return
  }
  event.preventDefault()
  router.push(url)
}

const DEMO_MODULES = [MOCK_MODULES.CMS]
</script>

<template>
  <footer class="footer">
    <div class="footer__container">
      <DemoDataNotice scope="section" :modules="DEMO_MODULES" />
      <div class="footer__top">
        <div class="footer__brand">
          <img class="footer__logo" :src="themedLogo" :alt="t('app.name')" />
          <p class="footer__tagline">{{ t('app.tagline') }}</p>
          <p v-if="footerNote" class="footer__note">{{ footerNote }}</p>
        </div>

        <nav v-if="quickLinks.length" class="footer__nav" :aria-label="t('nav.footerLabel')">
          <span class="footer__col-title">{{ t('footer.quickLinks') }}</span>
          <a
            v-for="link in quickLinks"
            :key="link.url"
            class="footer__link"
            :href="link.url"
            @click="onQuickLink($event, link.url)"
          >
            {{ localized(link.label) }}
          </a>
        </nav>

        <div v-if="contact" class="footer__contact">
          <span class="footer__col-title">{{ t('footer.contactTitle') }}</span>
          <a v-if="email" class="footer__link" :href="`mailto:${email}`">{{ email }}</a>
          <a v-if="phone" class="footer__link" :href="`tel:${phone.replace(/\s+/g, '')}`" dir="ltr">
            {{ phone }}
          </a>
          <ul
            v-if="social.length"
            class="footer__social"
            :aria-label="t('public.home.contact.social')"
          >
            <li v-for="link in social" :key="link.platform">
              <a
                class="footer__social-link"
                :href="link.url"
                target="_blank"
                rel="noopener"
                :aria-label="t(link.meta.labelKey)"
              >
                <i :class="link.meta.icon" aria-hidden="true"></i>
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div class="footer__bottom">
        <p class="footer__copyright">{{ t('footer.copyright', { year }) }}</p>
        <p class="footer__developed">
          <a class="footer__developed-link" :href="developedBy.url" target="_blank" rel="noopener">
            {{ developedBy.label }}
          </a>
        </p>
      </div>
    </div>
  </footer>
</template>
