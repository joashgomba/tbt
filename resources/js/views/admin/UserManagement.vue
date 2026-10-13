<template>
  <div class="uam-enterprise cm-enterprise">
    <section class="uam-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-shield-lock"></i></div>
              <div>
                <div class="hero-kicker">{{ t('uamHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('uamHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('uamHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button v-if="canManageUsers" type="button" class="btn btn-outline-light btn-sm" @click="openUserModal()">
                <i class="ti ti-user-plus me-1"></i>{{ t('uamAddUser') }}
              </button>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="uam-stat-grid">
              <div class="uam-stat"><div class="label">{{ t('uamActiveUsers') }}</div><div class="value">{{ stats.summary.active_users ?? 0 }}</div></div>
              <div class="uam-stat"><div class="label">{{ t('uamPendingUsers') }}</div><div class="value">{{ stats.summary.pending_users ?? 0 }}</div></div>
              <div class="uam-stat"><div class="label">{{ t('uamMfaCompliance') }}</div><div class="value">{{ stats.summary.mfa_compliance_pct ?? 0 }}%</div></div>
              <div class="uam-stat"><div class="label">{{ t('uamDelegations') }}</div><div class="value">{{ stats.summary.active_delegations ?? 0 }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card uam-card mb-3">
      <div class="card-body py-3">
        <ul class="nav uam-tabs flex-wrap gap-2">
          <li v-for="tab in tabs" :key="tab.key">
            <button type="button" class="nav-link" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
              <i :class="tab.icon" class="me-1"></i>{{ tab.label }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div v-if="message" class="alert alert-success">{{ message }}</div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-if="loading && !bootstrapped" class="card uam-card"><div class="card-body text-center p-5 text-muted">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="row g-4 mb-4">
          <div class="col-md-3" v-for="tile in overviewTiles" :key="tile.label">
            <div class="card uam-card h-100"><div class="card-body">
              <div class="small text-muted">{{ tile.label }}</div>
              <div class="fs-4 fw-bold">{{ tile.value }}</div>
            </div></div>
          </div>
        </div>
        <div class="card uam-card">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0">{{ t('uamInstitutionLeaderboard') }}</h6>
            <p class="small text-muted mb-0">{{ t('uamLeaderboardSub') }}</p>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>#</th><th>{{ t('institution') }}</th><th>{{ t('uamActiveUsers') }}</th><th>{{ t('uamFocalPoints') }}</th><th>{{ t('uamMfaCompliance') }}</th><th>{{ t('openCases') }}</th></tr></thead>
              <tbody>
                <tr v-for="(row, i) in leaderboard" :key="row.institution?.id || i">
                  <td>{{ i + 1 }}</td>
                  <td class="fw-semibold">{{ row.institution?.institution_name || '—' }}</td>
                  <td>{{ row.active_users }}</td>
                  <td>{{ row.focal_points }}</td>
                  <td>{{ row.mfa_pct }}%</td>
                  <td>{{ row.open_cases }}</td>
                </tr>
                <tr v-if="!leaderboard.length"><td colspan="6" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Users -->
      <div v-show="activeTab === 'users'">
        <div class="card uam-card mb-3"><div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-4"><label class="form-label">{{ t('search') }}</label><input v-model="userFilters.search" type="search" class="form-control" @keyup.enter="loadUsers" /></div>
            <div class="col-md-2"><label class="form-label">{{ t('institution') }}</label>
              <select v-model="userFilters.institution_id" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option></select></div>
            <div class="col-md-2"><label class="form-label">{{ t('status') }}</label>
              <select v-model="userFilters.status" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="s in lookups.statuses" :key="s" :value="s">{{ s }}</option></select></div>
            <div class="col-md-2"><label class="form-label">{{ t('uamRole') }}</label>
              <select v-model="userFilters.role" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="r in lookups.roles" :key="r.id" :value="r.name">{{ r.name }}</option></select></div>
            <div class="col-md-2"><label class="form-label">{{ t('accountCategory') }}</label>
              <select v-model="userFilters.account_category" class="form-select"><option value="">{{ t('all') }}</option>
                <option v-for="c in lookups.categories" :key="c" :value="c">{{ c }}</option></select></div>
            <div class="col-md-2"><button type="button" class="btn btn-primary w-100" @click="loadUsers">{{ t('applyFilters') }}</button></div>
            <div class="col-md-2"><button type="button" class="btn btn-outline-secondary w-100" @click="filterRegisteredReporters">{{ t('uamRegisteredReporters') }}</button></div>
          </div>
        </div></div>
        <div class="row g-4">
          <div :class="selectedUser ? 'col-lg-7' : 'col-12'">
            <div class="card uam-card">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead><tr><th>{{ t('uamUsername') }}</th><th>{{ t('name') }}</th><th>{{ t('institution') }}</th><th>{{ t('uamRole') }}</th><th>{{ t('status') }}</th><th></th></tr></thead>
                  <tbody>
                    <tr v-for="u in users" :key="u.id" :class="{ 'table-active': selectedUser?.id === u.id }">
                      <td>{{ u.username }}</td>
                      <td>{{ u.full_name || u.name }}</td>
                      <td>{{ u.institution?.institution_name || '—' }}</td>
                      <td><span class="badge bg-label-primary">{{ u.roles?.[0]?.name || '—' }}</span></td>
                      <td><span class="uam-status" :class="statusClass(u.status)">{{ u.status }}</span></td>
                      <td><button type="button" class="btn btn-sm btn-link" @click="selectUser(u)">{{ t('view') }}</button></td>
                    </tr>
                    <tr v-if="!users.length"><td colspan="6" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div v-if="selectedUser" class="col-lg-5">
            <div class="card uam-card uam-detail-drawer">
              <div class="card-header bg-white d-flex justify-content-between"><h6 class="mb-0">{{ selectedUser.full_name || selectedUser.name }}</h6>
                <button type="button" class="btn-close" @click="selectedUser = null"></button></div>
              <div class="card-body">
                <p class="small text-muted mb-2">{{ selectedUser.email }} · {{ selectedUser.username }}</p>
                <p class="small"><strong>{{ t('uamRole') }}:</strong> {{ selectedUser.roles?.[0]?.name }}</p>
                <p class="small"><strong>{{ t('status') }}:</strong> {{ selectedUser.status }}</p>
                <p class="small"><strong>{{ t('uamLastLogin') }}:</strong> {{ formatDate(selectedUser.last_login_at) }}</p>
                <p v-if="selectedUser.account_category" class="small"><strong>{{ t('accountCategory') }}:</strong> {{ selectedUser.account_category }}</p>
                <p v-if="selectedUser.organization_name" class="small"><strong>{{ t('organizationName') }}:</strong> {{ selectedUser.organization_name }}</p>
                <p v-if="selectedUser.country" class="small"><strong>{{ t('country') }}:</strong> {{ selectedUser.country }}</p>
                <p v-if="selectedUser.phone_number" class="small"><strong>{{ t('reporterPhone') }}:</strong> {{ selectedUser.phone_number }}</p>
                <div v-if="canManageUsers" class="d-flex flex-wrap gap-2 mt-3">
                  <button v-if="selectedUser.status !== 'active'" type="button" class="btn btn-sm btn-success" @click="executeUserAction('activate')">{{ t('uamActivate') }}</button>
                  <button v-if="selectedUser.status === 'active'" type="button" class="btn btn-sm btn-warning" @click="openUserActionModal('suspend')">{{ t('uamSuspend') }}</button>
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="openUserActionModal('deactivate')">{{ t('uamDeactivate') }}</button>
                  <button v-if="selectedUser.status === 'locked'" type="button" class="btn btn-sm btn-outline-primary" @click="executeUserAction('unlock')">{{ t('uamUnlock') }}</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="openUserActionModal('reset-password')">{{ t('uamResetPassword') }}</button>
                  <button type="button" class="btn btn-sm btn-outline-warning" @click="openUserActionModal('reset-mfa')">{{ t('uamResetMfa') }}</button>
                </div>
                <div v-if="userDetail?.permissions?.length" class="mt-4">
                  <h6 class="small fw-semibold">{{ t('uamEffectivePermissions') }}</h6>
                  <div class="d-flex flex-wrap gap-1">
                    <span v-for="p in userDetail.permissions.slice(0, 12)" :key="p" class="badge bg-light text-dark border">{{ p }}</span>
                    <span v-if="userDetail.permissions.length > 12" class="small text-muted">+{{ userDetail.permissions.length - 12 }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Roles -->
      <div v-show="activeTab === 'roles'">
        <div class="card uam-card mb-4">
          <div class="card-header bg-white"><h6 class="mb-0">{{ t('uamRoleCatalogue') }}</h6></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>{{ t('uamRoleCode') }}</th><th>{{ t('name') }}</th><th>{{ t('uamCategory') }}</th><th>{{ t('uamScope') }}</th><th>{{ t('uamUsers') }}</th><th></th></tr></thead>
              <tbody>
                <tr v-for="r in roles" :key="r.id">
                  <td><code>{{ r.code || r.name }}</code></td>
                  <td>{{ localizedRoleTitle(r) }}</td>
                  <td>{{ r.category }}</td>
                  <td>{{ r.scope }}</td>
                  <td>{{ r.users_count ?? 0 }}</td>
                  <td><button type="button" class="btn btn-sm btn-link" @click="editRole = r">{{ t('edit') }}</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card uam-card">
          <div class="card-header bg-white"><h6 class="mb-0">{{ t('uamPermissionMatrix') }}</h6></div>
          <div class="table-responsive" style="max-height: 400px">
            <table class="table table-sm uam-matrix-table mb-0">
              <thead><tr><th>{{ t('uamPermission') }}</th><th v-for="r in matrix.roles" :key="r.id">{{ r.code || r.name }}</th></tr></thead>
              <tbody>
                <tr v-for="perm in matrix.permissions" :key="perm">
                  <td><code>{{ perm }}</code></td>
                  <td v-for="r in matrix.roles" :key="r.id" class="text-center">{{ r.permissions?.includes(perm) ? '✓' : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Institutions -->
      <div v-show="activeTab === 'institutions'">
        <div class="row g-3">
          <div v-for="inst in institutions" :key="inst.id" class="col-md-6 col-xl-4">
            <div class="card uam-card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <code class="small">{{ inst.institution_code || '—' }}</code>
                  <span class="badge" :class="inst.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ inst.status }}</span>
                </div>
                <h6>{{ inst.institution_name }}</h6>
                <p class="small text-muted mb-2">{{ inst.institution_type }}</p>
                <div class="small">{{ t('uamActiveUsers') }}: <strong>{{ inst.active_users_count ?? 0 }}</strong></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Delegations -->
      <div v-show="activeTab === 'delegations'">
        <div class="d-flex justify-content-between mb-3">
          <h6 class="mb-0">{{ t('uamDelegations') }}</h6>
          <button type="button" class="btn btn-sm btn-primary" @click="showDelegationModal = true">{{ t('uamAddDelegation') }}</button>
        </div>
        <div class="card uam-card">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>{{ t('uamDelegator') }}</th><th>{{ t('uamDelegate') }}</th><th>{{ t('uamPeriod') }}</th><th>{{ t('status') }}</th><th></th></tr></thead>
              <tbody>
                <tr v-for="d in delegations" :key="d.id">
                  <td>{{ d.delegator?.name }}</td>
                  <td>{{ d.delegate?.name }}</td>
                  <td class="small">{{ formatDate(d.starts_at) }} – {{ formatDate(d.ends_at) }}</td>
                  <td>{{ d.status }}</td>
                  <td><button v-if="d.status === 'active'" type="button" class="btn btn-sm btn-outline-danger" @click="revokeDelegation(d)">{{ t('uamRevoke') }}</button></td>
                </tr>
                <tr v-if="!delegations.length"><td colspan="5" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Governance -->
      <div v-show="activeTab === 'governance'">
        <div class="d-flex justify-content-between mb-3">
          <h6 class="mb-0">{{ t('uamAccessReviews') }}</h6>
          <button v-if="canManageUsers" type="button" class="btn btn-sm btn-primary" @click="openReviewModal()">{{ t('uamStartReview') }}</button>
        </div>
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card uam-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('uamAccessReviews') }}</h6></div>
              <ul class="list-group list-group-flush">
                <li v-for="r in accessReviews" :key="r.id" class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div>{{ r.title }}</div>
                    <div class="small text-muted">{{ formatDate(r.due_at) }}</div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-label-primary">{{ r.status }}</span>
                    <button type="button" class="btn btn-sm btn-link" @click="openCertifyReview(r)">{{ t('uamCertify') }}</button>
                  </div>
                </li>
                <li v-if="!accessReviews.length" class="list-group-item text-muted">{{ t('uamNoData') }}</li>
              </ul>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card uam-card mb-4">
              <div class="card-header bg-white d-flex justify-content-between">
                <h6 class="mb-0">{{ t('uamNotifications') }}</h6>
                <button type="button" class="btn btn-sm btn-link" @click="loadNotifications">{{ t('refresh') }}</button>
              </div>
              <ul class="list-group list-group-flush" style="max-height: 220px; overflow-y: auto">
                <li v-for="n in notifications" :key="n.id" class="list-group-item" :class="{ 'fw-semibold': !n.read_at }">
                  <div class="small">{{ n.subject }}</div>
                  <div class="small text-muted">{{ n.message?.slice(0, 100) }}</div>
                </li>
                <li v-if="!notifications.length" class="list-group-item text-muted">{{ t('uamNoData') }}</li>
              </ul>
            </div>
            <div class="card uam-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('uamLoginAttempts') }}</h6></div>
              <div class="table-responsive" style="max-height: 280px">
                <table class="table table-sm mb-0">
                  <tbody>
                    <tr v-for="a in loginAttempts.slice(0, 20)" :key="a.id">
                      <td class="small">{{ a.identifier }}</td>
                      <td><span :class="a.successful ? 'text-success' : 'text-danger'">{{ a.successful ? 'OK' : 'Fail' }}</span></td>
                      <td class="small text-muted">{{ formatDate(a.created_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Create user modal -->
    <div v-if="showUserModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('uamAddUser') }}</h5><button type="button" class="btn-close" @click="showUserModal = false"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ t('name') }}</label><input v-model="userForm.name" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('uamUsername') }}</label><input v-model="userForm.username" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('email') }}</label><input v-model="userForm.email" type="email" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('password') }}</label><input v-model="userForm.password" type="password" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('institution') }}</label>
              <select v-model="userForm.institution_id" class="form-select"><option value="">{{ t('none') }}</option>
                <option v-for="i in lookups.institutions" :key="i.id" :value="i.id">{{ i.institution_name }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('uamRole') }}</label>
              <select v-model="userForm.role" class="form-select"><option v-for="r in lookups.roles" :key="r.id" :value="r.name">{{ r.name }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('language') }}</label>
              <select v-model="userForm.preferred_language" class="form-select"><option value="fr">Français</option><option value="en">English</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('uamCategory') }}</label>
              <select v-model="userForm.account_category" class="form-select"><option v-for="c in lookups.categories" :key="c" :value="c">{{ c }}</option></select></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showUserModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="createUser">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>
    <!-- Delegation modal -->
    <div v-if="showDelegationModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('uamAddDelegation') }}</h5><button type="button" class="btn-close" @click="showDelegationModal = false"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">{{ t('uamDelegator') }}</label>
              <select v-model="delegationForm.delegator_id" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="u in users" :key="'d-'+u.id" :value="u.id">{{ u.full_name || u.name }}</option>
              </select></div>
            <div class="col-12"><label class="form-label">{{ t('uamDelegate') }}</label>
              <select v-model="delegationForm.delegate_id" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="u in users" :key="'e-'+u.id" :value="u.id">{{ u.full_name || u.name }}</option>
              </select></div>
            <div class="col-md-6"><label class="form-label">{{ t('uamStartsAt') }}</label><input v-model="delegationForm.starts_at" type="datetime-local" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('uamEndsAt') }}</label><input v-model="delegationForm.ends_at" type="datetime-local" class="form-control" /></div>
            <div class="col-12"><label class="form-label">{{ t('uamReasonRequired') }}</label><textarea v-model="delegationForm.reason" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showDelegationModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="createDelegation">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Role permission editor -->
    <div v-if="editRole" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ t('uamEditRole') }} — {{ localizedRoleTitle(editRole) }}</h5>
          <button type="button" class="btn-close" @click="closeRoleEditor"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-md-3"><label class="form-label">{{ t('uamRoleCode') }}</label><input v-model="roleForm.code" class="form-control" disabled /></div>
            <div class="col-md-3"><label class="form-label">{{ t('name') }} EN</label><input v-model="roleForm.title_en" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('name') }} FR</label><input v-model="roleForm.title_fr" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('uamScope') }}</label><input v-model="roleForm.scope" class="form-control" /></div>
          </div>
          <h6 class="mb-3">{{ t('uamPermissionMatrix') }}</h6>
          <div class="row g-2" style="max-height: 360px; overflow-y: auto">
            <div v-for="perm in allPermissions" :key="perm.name" class="col-md-4">
              <label class="form-check uam-perm-check">
                <input v-model="roleForm.permissions" type="checkbox" class="form-check-input" :value="perm.name" />
                <span class="form-check-label small"><code>{{ perm.name }}</code></span>
              </label>
            </div>
          </div>
          <p class="small text-muted mt-3">{{ t('uamRoleImpact') }}: {{ editRole.users_count ?? 0 }} {{ t('uamUsers') }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeRoleEditor">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveRole">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Access review certification -->
    <div v-if="activeReview" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ t('uamCertifyReview') }} — {{ activeReview.title }}</h5>
          <button type="button" class="btn-close" @click="activeReview = null"></button>
        </div>
        <div class="modal-body">
          <div v-if="reviewLoading" class="text-center p-4 text-muted">{{ t('loading') }}</div>
          <div v-else class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>{{ t('name') }}</th><th>{{ t('uamRole') }}</th><th>{{ t('institution') }}</th><th>{{ t('uamDecision') }}</th><th>{{ t('notes') }}</th></tr></thead>
              <tbody>
                <tr v-for="row in reviewSubjects" :key="row.user.id">
                  <td>{{ row.user.full_name || row.user.name }}</td>
                  <td>{{ row.user.roles?.[0]?.name }}</td>
                  <td>{{ row.user.institution?.institution_name || '—' }}</td>
                  <td>
                    <select v-model="certifyForm[row.user.id].decision" class="form-select form-select-sm">
                      <option value="confirmed">{{ t('uamDecisionConfirm') }}</option>
                      <option value="role_changed">{{ t('uamDecisionRoleChange') }}</option>
                      <option value="access_revoked">{{ t('uamDecisionRevoke') }}</option>
                      <option value="flagged">{{ t('uamDecisionFlag') }}</option>
                    </select>
                    <select v-if="certifyForm[row.user.id].decision === 'role_changed'" v-model="certifyForm[row.user.id].new_role_name" class="form-select form-select-sm mt-1">
                      <option v-for="r in lookups.roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                    </select>
                  </td>
                  <td><input v-model="certifyForm[row.user.id].notes" class="form-control form-control-sm" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="activeReview = null">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-outline-primary" @click="submitCertify(false)">{{ t('uamSaveProgress') }}</button>
          <button type="button" class="btn btn-primary" @click="submitCertify(true)">{{ t('uamCompleteReview') }}</button>
        </div>
      </div></div>
    </div>

    <!-- User lifecycle action (no native prompt/confirm — DEF-006) -->
    <div v-if="userActionModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ userActionModalTitle }}</h5>
          <button type="button" class="btn-close" @click="closeUserActionModal"></button>
        </div>
        <div class="modal-body">
          <p v-if="userActionModal === 'reset-mfa'" class="mb-0">{{ t('uamConfirmResetMfa') }}</p>
          <p v-else-if="userActionModal === 'revoke-delegation'" class="mb-0">{{ t('uamConfirmRevoke') }}</p>
          <div v-else-if="userActionModal === 'reset-password'" class="mb-0">
            <label class="form-label" for="uam-new-password">{{ t('uamNewPassword') }}</label>
            <input id="uam-new-password" v-model="userActionInput" type="password" class="form-control" autocomplete="new-password" minlength="8" />
          </div>
          <div v-else class="mb-0">
            <label class="form-label" for="uam-action-reason">{{ t('uamReasonRequired') }}</label>
            <textarea id="uam-action-reason" v-model="userActionInput" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeUserActionModal">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" :disabled="userActionConfirmDisabled" @click="confirmUserActionModal">{{ t('confirm') }}</button>
        </div>
      </div></div>
    </div>

    <!-- New access review -->
    <div v-if="showReviewModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('uamStartReview') }}</h5><button type="button" class="btn-close" @click="showReviewModal = false"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">{{ t('uamReviewTitle') }}</label><input v-model="reviewForm.title" class="form-control" /></div>
          <div class="mb-3"><label class="form-label">{{ t('institution') }}</label>
            <select v-model="reviewForm.institution_id" class="form-select"><option value="">{{ t('all') }}</option>
              <option v-for="i in lookups.institutions" :key="i.id" :value="i.id">{{ i.institution_name }}</option></select></div>
          <div class="mb-3"><label class="form-label">{{ t('uamEndsAt') }}</label><input v-model="reviewForm.due_at" type="date" class="form-control" /></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showReviewModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="createReview">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t, locale } = useI18n()

const loading = ref(true)
const bootstrapped = ref(false)
const error = ref('')
const message = ref('')
const activeTab = ref('overview')
const stats = ref({ summary: {}, leaderboard: [] })
const lookups = ref({ institutions: [], roles: [], statuses: [], categories: [] })
const users = ref([])
const roles = ref([])
const matrix = ref({ roles: [], permissions: [] })
const institutions = ref([])
const delegations = ref([])
const accessReviews = ref([])
const loginAttempts = ref([])
const notifications = ref([])
const allPermissions = ref([])
const selectedUser = ref(null)
const userDetail = ref(null)
const showUserModal = ref(false)
const showDelegationModal = ref(false)
const editRole = ref(null)
const roleForm = ref({ title_en: '', title_fr: '', scope: '', code: '', permissions: [] })
const activeReview = ref(null)
const reviewSubjects = ref([])
const reviewLoading = ref(false)
const certifyForm = ref({})
const showReviewModal = ref(false)
const reviewForm = ref({ title: '', institution_id: '', due_at: '' })
const me = ref(null)

const userFilters = ref({ search: '', institution_id: '', status: '', role: '', account_category: '' })
const userForm = ref({ name: '', username: '', email: '', password: '', institution_id: '', role: 'institutional_officer', preferred_language: 'fr', account_category: 'institutional' })
const delegationForm = ref({ delegator_id: '', delegate_id: '', starts_at: '', ends_at: '', reason: '' })
const userActionModal = ref(null)
const userActionInput = ref('')
const pendingRevokeDelegation = ref(null)

const canManageUsers = computed(() => me.value?.permissions?.includes('admin.users') ?? false)
const canManageRoles = computed(() => me.value?.permissions?.includes('admin.roles') ?? false)
const leaderboard = computed(() => stats.value.leaderboard || [])

const tabs = computed(() => {
  const list = [
    { key: 'overview', label: t('uamTabOverview'), icon: 'ti ti-layout-dashboard' },
    { key: 'users', label: t('uamTabUsers'), icon: 'ti ti-users' },
  ]
  if (canManageRoles.value) list.push({ key: 'roles', label: t('uamTabRoles'), icon: 'ti ti-key' })
  list.push(
    { key: 'institutions', label: t('uamTabInstitutions'), icon: 'ti ti-building-community' },
    { key: 'delegations', label: t('uamTabDelegations'), icon: 'ti ti-arrows-exchange' },
    { key: 'governance', label: t('uamTabGovernance'), icon: 'ti ti-shield-check' },
  )
  return list
})

const userActionModalTitle = computed(() => {
  switch (userActionModal.value) {
    case 'suspend': return t('uamSuspend')
    case 'deactivate': return t('uamDeactivate')
    case 'reset-password': return t('uamResetPassword')
    case 'reset-mfa': return t('uamResetMfa')
    case 'revoke-delegation': return t('uamConfirmRevoke')
    default: return ''
  }
})

const userActionConfirmDisabled = computed(() => {
  if (userActionModal.value === 'reset-mfa' || userActionModal.value === 'revoke-delegation') return false
  return !userActionInput.value.trim()
})

const overviewTiles = computed(() => {
  const s = stats.value.summary || {}
  return [
    { label: t('uamTotalUsers'), value: s.total_users ?? 0 },
    { label: t('uamSuspendedLocked'), value: s.suspended_locked ?? 0 },
    { label: t('uamDormant'), value: s.dormant_accounts ?? 0 },
    { label: t('uamRoles'), value: s.roles_count ?? 0 },
  ]
})

function statusClass(s) {
  if (s === 'active') return 'uam-status--active'
  if (['locked', 'suspended', 'deactivated'].includes(s)) return 'uam-status--locked'
  return 'uam-status--pending'
}

function localizedRoleTitle(r) {
  return locale.value === 'fr' ? (r.title_fr || r.name) : (r.title_en || r.name)
}

function formatDate(v) {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return String(v)
  }
}

function buildUserParams() {
  const p = {}
  Object.entries(userFilters.value).forEach(([k, v]) => { if (v) p[k] = v })
  return p
}

async function refresh() { await loadAll(true) }

async function loadAll(manual = false) {
  if (manual) loading.value = true
  error.value = ''
  try {
    const [meRes, statsRes, lookupRes] = await Promise.all([
      http.get('/auth/me'),
      http.get('/uam/stats'),
      http.get('/uam/lookups'),
    ])
    me.value = meRes.data?.user
    stats.value = statsRes.data
    lookups.value = lookupRes.data
    await Promise.all([loadUsers(), loadRoles(), loadInstitutions(), loadDelegations(), loadGovernance()])
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('uamLoadError')
  } finally {
    loading.value = false
  }
}

async function loadUsers() {
  const { data } = await http.get('/uam/users', { params: buildUserParams() })
  users.value = data.data || data || []
}

async function filterRegisteredReporters() {
  userFilters.value = {
    search: '',
    institution_id: '',
    status: '',
    role: 'economic_operator',
    account_category: 'external',
  }
  try {
    const { data } = await http.get('/uam/portal-reporters')
    users.value = data.data || []
  } catch {
    await loadUsers()
  }
}

async function loadRoles() {
  if (!canManageRoles.value) return
  const [rolesRes, matrixRes] = await Promise.all([
    http.get('/uam/roles'),
    http.get('/uam/permission-matrix'),
  ])
  roles.value = rolesRes.data.roles || []
  matrix.value = matrixRes.data
}

async function loadInstitutions() {
  const { data } = await http.get('/uam/institutions')
  institutions.value = data.institutions || []
}

async function loadDelegations() {
  const { data } = await http.get('/uam/delegations')
  delegations.value = data.delegations || []
}

async function loadGovernance() {
  const [rev, att] = await Promise.all([
    http.get('/uam/access-reviews').catch(() => ({ data: { reviews: [] } })),
    http.get('/uam/login-attempts').catch(() => ({ data: { attempts: [] } })),
  ])
  accessReviews.value = rev.data.reviews || []
  loginAttempts.value = att.data.attempts || []
  await loadNotifications()
}

async function loadNotifications() {
  try {
    const { data } = await http.get('/uam/notifications')
    notifications.value = data.notifications || []
  } catch { notifications.value = [] }
}

watch(editRole, async (role) => {
  if (!role) return
  try {
    const [roleRes, permRes] = await Promise.all([
      http.get(`/uam/roles/${role.id}`),
      http.get('/uam/permissions'),
    ])
    allPermissions.value = permRes.data.permissions || []
    const r = roleRes.data.role
    roleForm.value = {
      code: r.code || r.name,
      title_en: r.title_en || '',
      title_fr: r.title_fr || '',
      scope: r.scope || '',
      permissions: (r.permissions || []).map(p => p.name),
    }
  } catch (e) { error.value = t('uamLoadError') }
})

function closeRoleEditor() {
  editRole.value = null
  roleForm.value = { title_en: '', title_fr: '', scope: '', code: '', permissions: [] }
}

async function saveRole() {
  try {
    await http.put(`/uam/roles/${editRole.value.id}`, roleForm.value)
    message.value = t('cmActionSuccess')
    closeRoleEditor()
    await loadRoles()
    matrix.value = (await http.get('/uam/permission-matrix')).data
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

function openReviewModal() {
  reviewForm.value = { title: '', institution_id: '', due_at: '' }
  showReviewModal.value = true
}

async function createReview() {
  try {
    await http.post('/uam/access-reviews', reviewForm.value)
    showReviewModal.value = false
    message.value = t('cmActionSuccess')
    await loadGovernance()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

async function openCertifyReview(review) {
  activeReview.value = review
  reviewLoading.value = true
  certifyForm.value = {}
  try {
    const { data } = await http.get(`/uam/access-reviews/${review.id}`)
    reviewSubjects.value = data.subjects || []
    reviewSubjects.value.forEach(({ user }) => {
      certifyForm.value[user.id] = {
        decision: 'confirmed',
        new_role_name: user.roles?.[0]?.name || '',
        notes: '',
      }
    })
  } catch (e) { error.value = t('uamLoadError') }
  finally { reviewLoading.value = false }
}

async function submitCertify(complete) {
  const decisions = Object.entries(certifyForm.value).map(([userId, row]) => ({
    user_id: Number(userId),
    ...row,
  }))
  try {
    await http.post(`/uam/access-reviews/${activeReview.value.id}/certify`, { decisions, complete })
    message.value = t('cmActionSuccess')
    activeReview.value = null
    await loadGovernance()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

async function selectUser(u) {
  selectedUser.value = u
  const { data } = await http.get(`/uam/users/${u.id}`)
  userDetail.value = data
}

function openUserModal() {
  userForm.value = { name: '', username: '', email: '', password: '', institution_id: '', role: 'institutional_officer', preferred_language: 'fr', account_category: 'institutional' }
  showUserModal.value = true
}

async function createUser() {
  try {
    await http.post('/uam/users', { ...userForm.value, roles: [userForm.value.role] })
    showUserModal.value = false
    message.value = t('cmActionSuccess')
    await loadAll()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

function openUserActionModal(action) {
  userActionModal.value = action
  userActionInput.value = ''
  pendingRevokeDelegation.value = null
}

function closeUserActionModal() {
  userActionModal.value = null
  userActionInput.value = ''
  pendingRevokeDelegation.value = null
}

function openRevokeDelegationModal(d) {
  pendingRevokeDelegation.value = d
  userActionModal.value = 'revoke-delegation'
  userActionInput.value = ''
}

async function confirmUserActionModal() {
  if (userActionModal.value === 'revoke-delegation') {
    const d = pendingRevokeDelegation.value
    closeUserActionModal()
    if (!d) return
    try {
      await http.post(`/uam/delegations/${d.id}/revoke`)
      message.value = t('cmActionSuccess')
      await loadDelegations()
    } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
    return
  }
  const action = userActionModal.value
  const extra = action === 'suspend' || action === 'deactivate'
    ? { reason: userActionInput.value.trim() }
    : action === 'reset-password'
      ? { password: userActionInput.value }
      : null
  closeUserActionModal()
  await executeUserAction(action, extra)
}

async function executeUserAction(action, extra = null) {
  const id = selectedUser.value?.id
  if (!id) return
  try {
    if (action === 'activate') await http.post(`/uam/users/${id}/activate`)
    else if (action === 'suspend') await http.post(`/uam/users/${id}/suspend`, { reason: extra?.reason })
    else if (action === 'deactivate') await http.post(`/uam/users/${id}/deactivate`, { reason: extra?.reason })
    else if (action === 'unlock') await http.post(`/uam/users/${id}/unlock`)
    else if (action === 'reset-password') await http.post(`/uam/users/${id}/reset-password`, { password: extra?.password })
    else if (action === 'reset-mfa') await http.post(`/uam/users/${id}/reset-mfa`)
    message.value = t('cmActionSuccess')
    await loadAll()
    await selectUser(selectedUser.value)
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

async function revokeDelegation(d) {
  openRevokeDelegationModal(d)
}

async function createDelegation() {
  try {
    await http.post('/uam/delegations', delegationForm.value)
    showDelegationModal.value = false
    delegationForm.value = { delegator_id: '', delegate_id: '', starts_at: '', ends_at: '', reason: '' }
    message.value = t('cmActionSuccess')
    await loadDelegations()
  } catch (e) { error.value = e?.response?.data?.message || t('cmActionFailed') }
}

onMounted(() => loadAll(true))
</script>
