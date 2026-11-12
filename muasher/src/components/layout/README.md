# components/layout

The layouts, wired through `meta.layout` (`public` | `auth` | `app`) in `src/App.vue`. An `app` route opens in
`PortalLayout` for entity-only accounts and in `AppLayout` for every other account (`utils/portalAudience.js`), so every
route keeps its path for both audiences:

- `PortalLayout` — entity portal (Figma page 14): skip link, `portal/PortalTopBar` (logo, رحلتي · تقييماتي ·
  شهاداتي · مدفوعاتي · صندوق الرسائل with the unread-reply badge, bell, account menu overlay; logo, bell and avatar → `/more`
  below `lg`), `portal/AttentionStrip` on hub screens, the `<main id="portal-main">` slot, `portal/PortalFooter` and
  `portal/MobileTabBar` below `lg`. Task screens (`PORTAL_TASK_ROUTES` in `constants/portal.js`) hide the strip and the
  tab bar; the thread route hides them on mobile only.

- `AppLayout` — back-office shell: skip link, full-height `Sidebar` at the inline start, then a
  column with `TopNavbar` and the `<main id="app-main">` slot. Loads the shared branding theme
  (`stores/branding.js`) once; `App.vue` applies `data-accent` on `<html>` for `app` routes only.
- `Sidebar` — brand logo (branding override or the brand default), items from
  `src/constants/navigation.js` filtered by role and grouped by module (`NAV_GROUPS`), one caption
  per group, no role switcher. The active item (route name or a child route name) gets
  `aria-current="page"` and the "Curved Active Tab" shape. Icons-only rail below `md`;
  collapsible from `md` up through the ui store.
- `TopNavbar` (`.navbar.navbar--app`) — sidebar toggle, page title, LocaleSwitcher, ThemeToggle,
  settings shortcut, NotificationBell, avatar user menu (My account, entity profile, sign out).
- `PublicLayout` / `PublicNavbar` / `AppFooter` — public pages (base `.navbar` styles).
- `AuthLayout` — centered card in `<main>` first, then the brand panel `<aside>` (logo on dark +
  `auth/AuthValuePanel`: an `h2` that names the aside and the three value cards from
  `constants/authPanel.js`). The aside sits at the
  inline start through CSS `order`; below `lg` it is a compact header (logo + heading); from `lg`
  with a tall viewport its inner column is sticky. Also hosts the maintenance page.

Styling: `_sidebar.scss` (sidebar + curved tab via `mixins/_curved-tab.scss`), `_navbar.scss`,
`_app-layout.scss`, `_portal-layout.scss`, `_portal-top-bar.scss`, `_attention-strip.scss`, `_mobile-tab-bar.scss`, `_public-layout.scss`, `_auth-layout.scss`, `_auth-value-panel.scss`, `_footer.scss`. No style block or
inline style in any `.vue`.
