<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePublicHome } from '@/composables/useHomeContent'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { HOME_ANCHORS, HOME_NAV_LINKS } from '@/constants/homeSections'
import LegalLink from '@/components/legal/LegalLink.vue'
import { FOOTER_LEGAL_LINKS } from '@/constants/footer'
import { scrollBehavior } from '@/utils/motion'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const router = useRouter()
const { logoOnDark } = useBrandLogo()
const { siteSettings, localized } = usePublicHome()

const year = new Date().getFullYear()

const sectionLinks = HOME_NAV_LINKS.filter((link) => link.id !== HOME_ANCHORS.hero).map((link) => ({
  key: link.id,
  labelKey: link.labelKey,
  url: link.routeName ? router.resolve({ name: link.routeName }).fullPath : `/#${link.id}`,
}))

const quickLinks = computed(() => {
  const managed = siteSettings.value?.quickLinks ?? []
  if (!managed.length) return sectionLinks.map((link) => ({ ...link, label: t(link.labelKey) }))
  return managed.map((link) => ({ key: link.url, label: localized(link.label), url: link.url }))
})
const contact = computed(() => siteSettings.value?.contact ?? null)
const email = computed(() => contact.value?.email ?? '')
const phone = computed(() => contact.value?.phone ?? '')
const address = computed(() => (contact.value?.address ? localized(contact.value.address) : ''))

function isExternal(url) {
  return /^https?:\/\//.test(url) || url.startsWith('mailto:') || url.startsWith('tel:')
}

function onQuickLink(event, url) {
  if (!url || isExternal(url)) return
  event.preventDefault()
  const anchor = url.startsWith('#') ? url.slice(1) : url.startsWith('/#') ? url.slice(2) : ''
  const target = anchor ? document.getElementById(anchor) : null
  if (target) {
    target.scrollIntoView({ behavior: scrollBehavior() })
    return
  }
  router.push(anchor ? { path: '/', hash: `#${anchor}` } : url)
}
</script>

<template>
  <footer class="footer">
    <div class="footer__container">
      <div class="footer__top">
        <div class="footer__brand">
          <img class="footer__logo" :src="logoOnDark" :alt="t('app.name')" loading="lazy" />
          <p class="footer__tagline">{{ t('footer.description') }}</p>
        </div>

        <nav v-if="quickLinks.length" class="footer__nav" :aria-label="t('nav.footerLabel')">
          <h2 class="footer__col-title">{{ t('footer.quickLinks') }}</h2>
          <a
            v-for="link in quickLinks"
            :key="link.key"
            class="footer__link"
            :href="link.url"
            @click="onQuickLink($event, link.url)"
          >
            {{ link.label }}
          </a>
        </nav>

        <div class="footer__legal">
          <h2 class="footer__col-title">{{ t('footer.legalTitle') }}</h2>
          <LegalLink
            v-for="item in FOOTER_LEGAL_LINKS"
            :key="item"
            :kind="item"
            link-class="footer__link footer__soon"
            soon-class="footer__soon-tag"
          />
        </div>

        <div v-if="contact" class="footer__contact">
          <h2 class="footer__col-title">{{ t('footer.contactTitle') }}</h2>
          <a v-if="email" class="footer__link footer__link--icon" :href="`mailto:${email}`">
            <AppIcon name="envelope" />
            <bdi dir="ltr">{{ email }}</bdi>
          </a>
          <a
            v-if="phone"
            class="footer__link footer__link--icon"
            :href="`tel:${phone.replace(/\s+/g, '')}`"
          >
            <AppIcon name="phone" />
            <bdi dir="ltr">{{ phone }}</bdi>
          </a>
          <span v-if="address" class="footer__link footer__link--icon">
            <AppIcon name="location" />
            {{ address }}
          </span>
        </div>
      </div>

      <div class="footer__bottom">
        <p class="footer__copyright">{{ t('footer.copyright', { year }) }}</p>
        <p class="footer__platform" lang="en" dir="ltr">{{ t('footer.platformLine') }}</p>
      </div>
    </div>
  </footer>
</template>
