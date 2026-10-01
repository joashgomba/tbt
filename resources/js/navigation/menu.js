export default [
  { sectionKey: 'menuSectionOverview' },
  { titleKey: 'dashboard', icon: 'ti ti-layout-dashboard', to: { name: 'admin-dashboard' }, permission: 'dashboards.operational' },

  { sectionKey: 'menuSectionCaseManagement' },
  { titleKey: 'complaints', icon: 'ti ti-flag', to: { name: 'admin-complaints' }, permission: 'complaints.view_all' },
  { titleKey: 'workflowSettings', icon: 'ti ti-git-branch', to: { name: 'admin-workflow' }, permission: 'admin.workflows' },
  { titleKey: 'reports', icon: 'ti ti-chart-bar', to: { name: 'admin-reports' }, permission: 'reports.generate' },

  { sectionKey: 'menuSectionAdministration' },
  { titleKey: 'users', icon: 'ti ti-users', to: { name: 'admin-users' }, permission: 'admin.users' },
  { titleKey: 'securitySettings', icon: 'ti ti-shield-lock', to: { name: 'admin-security' } },
  { titleKey: 'institutions', icon: 'ti ti-building-community', to: { name: 'admin-institutions' }, permission: 'admin.institutions' },
  { titleKey: 'categories', icon: 'ti ti-category', to: { name: 'admin-categories' }, permission: 'admin.categories' },
  { titleKey: 'corridors', icon: 'ti ti-route', to: { name: 'admin-corridors' }, permission: 'admin.corridors' },
  { titleKey: 'locations', icon: 'ti ti-map-pin', to: { name: 'admin-locations' }, permission: 'admin.locations' },
  { titleKey: 'audit', icon: 'ti ti-shield-check', to: { name: 'admin-audit' }, permission: 'audit.view' },
  { titleKey: 'integrations', icon: 'ti ti-plug-connected', to: { name: 'admin-integrations' }, permission: 'admin.integrations' },
  { titleKey: 'ussdSimulator', icon: 'ti ti-device-mobile-message', to: { name: 'admin-ussd-simulator' }, permission: 'admin.integrations' },
  { titleKey: 'developerDocs', icon: 'ti ti-book-2', to: { name: 'admin-developer-docs' }, permission: 'admin.integrations' },
]
