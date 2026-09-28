/**
 * Identity of the application and its own menu entries: the rest of the interface (layout, dashboard,
 * administration pages) is shared by every Rocket application.
 */
export default defineAppConfig({
  ui: {
    colors: {
      primary: 'green',
      neutral: 'slate',
    },
  },
  rocket: {
    id: 'place',
    name: 'Rocket Place',
    icon: 'i-lucide-map-pin',
    // Login page subtitle.
    tagline: 'Tes lieux : serrures connectées, domotique, documents et stock — sans PMS.',
    // Main menu: the domain pages ("label" entries start a group).
    navigation: [
      { label: 'Lieux', type: 'label' },
      { label: 'Tous les lieux', icon: 'i-lucide-map-pin', to: '/places', exact: true },
    ] as { label: string, icon?: string, to?: string, type?: 'label', exact?: boolean, exactQuery?: boolean, admin?: boolean }[],
    // Extra entries of the Administration menu.
    adminNavigation: [
      { label: 'Serrures', icon: 'i-lucide-lock', to: '/locks' },
      { label: 'Plugins', icon: 'i-lucide-puzzle', to: '/plugins' },
    ] as { label: string, icon: string, to: string, exactQuery?: boolean }[],
    // "Services & raccourcis" of the dashboard, besides the documentation, changelog and API.
    shortcuts: [] as { label: string, description: string, icon: string, to: string, admin?: boolean }[],
    // Hero banner of the dashboard: one quote per day.
    quotes: [
      ['Un lieu bien tenu se remarque à peine ; c’est justement le but.', 'Adage d’intendance'],
      ['La clé la plus sûre est celle qu’on n’a jamais besoin de refaire.', 'Adage d’hôte'],
      ['Ce qui n’est pas noté n’est pas fait.', 'Adage de gestion'],
    ] as [string, string][],
  },
})
