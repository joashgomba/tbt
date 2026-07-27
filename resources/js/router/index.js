import { createRouter, createWebHistory } from 'vue-router'
import { registerAuthNavigationGuard } from '@/auth/session'

const PublicLayout = () => import('@/layouts/PublicLayout.vue')
const AdminLayout = () => import('@/layouts/AdminLayout.vue')

const PublicHome = () => import('@/views/public/PublicHome.vue')
const PublicReport = () => import('@/views/public/PublicReport.vue')
const PublicReportConfirmation = () => import('@/views/public/PublicReportConfirmation.vue')
const PublicTrack = () => import('@/views/public/PublicTrack.vue')
const PublicTransparency = () => import('@/views/public/PublicTransparency.vue')
const PublicResources = () => import('@/views/public/PublicResources.vue')
const Login = () => import('@/views/auth/Login.vue')

const Dashboard = () => import('@/views/admin/Dashboard.vue')
const Complaints = () => import('@/views/admin/Complaints.vue')
const ComplaintDetail = () => import('@/views/admin/ComplaintDetail.vue')
const WorkflowSettings = () => import('@/views/admin/WorkflowSettings.vue')
const Reports = () => import('@/views/admin/Reports.vue')
const AdminPlaceholder = () => import('@/views/admin/AdminPlaceholder.vue')

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    {
      path: '/',
      component: PublicLayout,
      meta: { public: true },
      children: [
        { path: '', name: 'public-home', component: PublicHome },
        { path: 'report', name: 'public-report', component: PublicReport },
        { path: 'report/confirmation/:code', name: 'public-report-confirmation', component: PublicReportConfirmation },
        { path: 'track', name: 'public-track', component: PublicTrack },
        { path: 'transparency', name: 'public-transparency', component: PublicTransparency },
        { path: 'resources', name: 'public-resources', component: PublicResources },
      ],
    },
    {
      path: '/login',
      name: 'login',
      component: Login,
      meta: { guest: true, public: true },
    },
    {
      path: '/login/mfa',
      name: 'admin-mfa-verify',
      component: () => import('@/views/auth/MfaVerify.vue'),
      meta: { public: true },
    },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'admin-dashboard', component: Dashboard, meta: { permission: 'dashboards.operational' } },
        { path: 'complaints', name: 'admin-complaints', component: Complaints, meta: { permission: 'complaints.view_all' } },
        { path: 'complaints/:id', name: 'admin-complaint-detail', component: ComplaintDetail, meta: { permission: 'complaints.view_all' } },
        { path: 'workflow', name: 'admin-workflow', component: WorkflowSettings, meta: { permission: 'admin.workflows' } },
        { path: 'reports', name: 'admin-reports', component: Reports, meta: { permission: 'reports.generate' } },
        { path: 'users', name: 'admin-users', component: () => import('@/views/admin/UserManagement.vue'), meta: { permission: 'admin.users' } },
        { path: 'security', name: 'admin-security', component: () => import('@/views/admin/SecuritySettings.vue'), meta: { requiresAuth: true } },
        { path: 'institutions', name: 'admin-institutions', component: () => import('@/views/admin/InstitutionManagement.vue'), meta: { permission: 'admin.institutions' } },
        { path: 'categories', name: 'admin-categories', component: () => import('@/views/admin/CategoryManagement.vue'), meta: { permission: 'admin.categories' } },
        { path: 'corridors', name: 'admin-corridors', component: () => import('@/views/admin/CorridorManagement.vue'), meta: { permission: 'admin.corridors' } },
        { path: 'locations', name: 'admin-locations', component: () => import('@/views/admin/LocationManagement.vue'), meta: { permission: 'admin.locations' } },
        { path: 'audit', name: 'admin-audit', component: () => import('@/views/admin/AuditManagement.vue'), meta: { permission: 'audit.view' } },
        { path: 'integrations', name: 'admin-integrations', component: () => import('@/views/admin/IntegrationManagement.vue'), meta: { permission: 'admin.integrations' } },
      ],
    },
  ],
})

registerAuthNavigationGuard(router)

export default router
