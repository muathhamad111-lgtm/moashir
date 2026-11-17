# components/layout

The layouts, wired through `meta.layout` (`public` | `auth` | `app`) in `src/App.vue`. An `app` route opens in
`PortalLayout` for entity-only accounts and in `AppLayout` for every other account (`utils/portalAudience.js`), so every
route keeps its path for both audiences:

- `PortalLayout` — entity portal (Figma page 14): skip link, `portal/PortalTopBar` (logo, رحلتي · تقييماتي ·
  شهاداتي · مدفوعاتي · صندوق الرسائل with the unread-reply badge, bell, account menu overlay; logo, bell and avatar → `/more`
  below `lg`), `portal/AttentionStrip` on hub screens, the `<main id="portal-main">` slot, `portal/PortalFooter` and
  `portal/MobileTabBar` below `lg`. Task screens (`PORTAL_TASK_ROUTES` in `constants/portal.js`) hide the strip and the
  tab bar; the thread route hides them on mobile only.

- `AppLayout` — back-office shell on the TTS Panel Design System "Modern layout": skip link, a floating
  `TopNavbar` across the page (secondary brand colour), then a body row with the white `Sidebar` at the inline start
  and the `<main id="app-main">` slot. Loads the shared branding theme (`stores/branding.js`) once. For `app` routes
  `App.vue` forces `data-theme="light"` (the back office has no dark mode), sets `data-shell="tts"` and injects the
  saved brand scheme as `--brand-*` custom properties (`composables/useBrandSchemeStyle.js`).
- `Sidebar` — white panel, items from `src/constants/navigation.js` filtered by role and grouped by module
  (`NAV_GROUPS`), one caption per group, dividers between groups. The active item (route name or a child route name)
  gets `aria-current="page"` and the primary brand fill. Icons-only rail below `md`; collapsible from `md` up through
  the ui store. It opens with `SidebarIdentityCard`.
- `SidebarIdentityCard` — the platform card at the top of `Sidebar` and of the portal `PortalSideNav` (TTS model):
  the site logo (made for a light background) linking to the dashboard, then «معاينة المنصة», which opens the public
  home page in a new tab. Collapsed, it shows the small logo and an icon-only link that keeps its accessible name.
- `TopNavbar` (`.navbar.navbar--app`) — no logo (the logo sits in the sidebar card), sidebar toggle, global search,
  LocaleSwitcher, settings shortcut, NotificationBell, avatar user menu (My account, entity profile, sign out).
  No theme toggle.
- `PublicLayout` / `PublicNavbar` / `AppFooter` — public pages (base `.navbar` styles).
- `AuthLayout` — centered card in `<main>` first, then the brand panel `<aside>` (logo on dark +
  `auth/AuthValuePanel`: an `h2` that names the aside and the three value cards from
  `constants/authPanel.js`). The aside sits at the
  inline start through CSS `order`; below `lg` it is a compact header (logo + heading); from `lg`
  with a tall viewport its inner column is sticky. Also hosts the maintenance page.

Styling: `_sidebar.scss`, `_identity-card.scss`, `_navbar.scss`,
`_app-layout.scss`, `_portal-layout.scss`, `_portal-top-bar.scss`, `_attention-strip.scss`, `_mobile-tab-bar.scss`, `_public-layout.scss`, `_auth-layout.scss`, `_auth-value-panel.scss`, `_footer.scss`. No style block or
inline style in any `.vue`.
The TTS look of the shared components inside the back office (buttons, fields, tables, menus, tabs, pagination,
modals, badges, stats cards) lives in `src/styles/tts/` and applies only under `:root[data-shell='tts']`
(`mixins/_tts-shell.scss`); tokens in `tokens/_tts.scss`, theme variables in `themes/_tts-shell.scss`.
