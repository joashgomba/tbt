<template>
  <div class="int-mgmt-enterprise cm-enterprise">
    <section class="int-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-plug-connected"></i></div>
              <div>
                <div class="hero-kicker">{{ t('intHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('intHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('intHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="openSystemModal()">
                <i class="ti ti-plus me-1"></i>{{ t('intAddSystem') }}
              </button>
              <RouterLink :to="{ name: 'admin-ussd-simulator' }" class="btn btn-outline-light btn-sm">
                <i class="ti ti-device-mobile-message me-1"></i>{{ t('ussdSimulator') }}
              </RouterLink>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="int-stat-grid">
              <div class="int-stat"><div class="label">{{ t('intSystems') }}</div><div class="value">{{ summary.systems_active ?? 0 }}</div></div>
              <div class="int-stat"><div class="label">{{ t('intPullFeeds') }}</div><div class="value">{{ summary.feeds_pull ?? 0 }}</div></div>
              <div class="int-stat"><div class="label">{{ t('intApiClients') }}</div><div class="value">{{ summary.api_clients ?? 0 }}</div></div>
              <div class="int-stat"><div class="label">{{ t('intSyncs24h') }}</div><div class="value">{{ summary.syncs_24h ?? 0 }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card int-card mb-3">
      <div class="card-body py-3">
        <ul class="nav int-tabs flex-wrap gap-2">
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
    <div v-if="secretReveal" class="alert alert-warning">
      <strong>{{ t('intSecretOnce') }}</strong>
      <div class="int-secret-box mt-2">{{ secretReveal }}</div>
    </div>

    <div v-if="loading && !bootstrapped" class="card int-card"><div class="card-body text-center p-5 text-muted">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="row g-4 mb-4">
          <div v-for="tile in overviewTiles" :key="tile.label" class="col-md-3">
            <div class="card int-card h-100"><div class="card-body">
              <div class="small text-muted">{{ tile.label }}</div>
              <div class="fs-4 fw-bold">{{ tile.value }}</div>
            </div></div>
          </div>
        </div>
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="card int-card">
              <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('intRecentActivity') }}</h6></div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>{{ t('date') }}</th><th>{{ t('intSystem') }}</th><th>{{ t('intDirection') }}</th><th>{{ t('status') }}</th><th>{{ t('intRecords') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="log in recentActivity" :key="log.id">
                      <td class="small">{{ formatDate(log.started_at) }}</td>
                      <td class="small">{{ log.system?.system_code || '—' }}</td>
                      <td class="small text-capitalize">{{ log.direction }}</td>
                      <td><span class="badge" :class="statusBadge(log.status)">{{ log.status }}</span></td>
                      <td>{{ log.records_processed }}</td>
                    </tr>
                    <tr v-if="!recentActivity.length"><td colspan="5" class="text-muted p-3">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card int-card h-100">
              <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('intByType') }}</h6></div>
              <div class="card-body">
                <ul class="list-unstyled small mb-0">
                  <li v-for="row in byType" :key="row.system_type" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-capitalize">{{ row.system_type }}</span><strong>{{ row.count }}</strong>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Systems registry -->
      <div v-show="activeTab === 'systems'">
        <div class="row g-4">
          <div :class="selected ? 'col-lg-7' : 'col-12'">
            <div class="card int-card mb-3"><div class="card-body">
              <div class="row g-3">
                <div class="col-md-4"><input v-model="filters.search" type="search" class="form-control" :placeholder="t('search')" @keyup.enter="loadSystems" /></div>
                <div class="col-md-3">
                  <select v-model="filters.system_type" class="form-select">
                    <option value="">{{ t('all') }}</option>
                    <option v-for="tp in lookups.system_types" :key="tp" :value="tp">{{ tp }}</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <select v-model="filters.direction" class="form-select">
                    <option value="">{{ t('all') }}</option>
                    <option v-for="d in lookups.directions" :key="d" :value="d">{{ d }}</option>
                  </select>
                </div>
                <div class="col-md-2"><button type="button" class="btn btn-primary w-100" @click="loadSystems">{{ t('applyFilters') }}</button></div>
              </div>
            </div></div>
            <div class="card int-card">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead><tr><th>{{ t('intCode') }}</th><th>{{ t('intSystem') }}</th><th>{{ t('intType') }}</th><th>{{ t('intDirection') }}</th><th>{{ t('status') }}</th><th></th></tr></thead>
                  <tbody>
                    <tr v-for="sys in systems" :key="sys.id" :class="{ 'table-active': selected?.id === sys.id }">
                      <td><span class="int-code-badge">{{ sys.system_code }}</span></td>
                      <td><div class="fw-semibold">{{ sys.name }}</div><div class="small text-muted">{{ sys.country || '—' }}</div></td>
                      <td class="text-capitalize small">{{ sys.system_type }}</td>
                      <td><span class="badge bg-label-info int-direction-badge">{{ sys.direction }}</span></td>
                      <td>{{ sys.is_active ? t('active') : t('inactive') }}</td>
                      <td><button type="button" class="btn btn-sm btn-link" @click="selectSystem(sys)">{{ t('view') }}</button></td>
                    </tr>
                    <tr v-if="!systems.length"><td colspan="6" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div v-if="selected && detail" class="col-lg-5">
            <div class="card int-card int-detail-drawer">
              <div class="card-header bg-white d-flex justify-content-between">
                <div><h6 class="mb-1">{{ detail.system.name }}</h6><span class="int-code-badge">{{ detail.system.system_code }}</span></div>
                <button type="button" class="btn-close" @click="closeDetail"></button>
              </div>
              <div class="card-body pt-2">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3">
                  <li v-for="dt in detailTabs" :key="dt.key">
                    <button type="button" class="nav-link py-1 px-2 small" :class="{ active: detailTab === dt.key }" @click="detailTab = dt.key">{{ dt.label }}</button>
                  </li>
                </ul>
                <div v-show="detailTab === 'profile'">
                  <p class="small text-muted">{{ detail.system.description_en || '—' }}</p>
                  <div class="small mb-3">
                    <p class="mb-1"><strong>{{ t('intBaseUrl') }}:</strong> {{ detail.system.base_url || '—' }}</p>
                    <p class="mb-1"><strong>{{ t('intAuthMethod') }}:</strong> {{ detail.system.auth_method }}</p>
                    <p class="mb-1"><strong>{{ t('intContactName') }}:</strong> {{ detail.system.contact_name || '—' }}</p>
                    <p class="mb-1"><strong>{{ t('intContact') }}:</strong> {{ detail.system.contact_email || '—' }}</p>
                    <p class="mb-1"><strong>{{ t('intHealth') }}:</strong>
                      <span :class="'int-health--' + detail.health.health">{{ detail.health.health }}</span>
                      ({{ detail.health.failed_syncs_7d }} {{ t('intFailed7d') }})</p>
                  </div>
                  <div v-if="connectionTest" class="alert py-2 small mb-2" :class="connectionTest.success ? 'alert-success' : 'alert-danger'">
                    {{ connectionTest.message }} <span v-if="connectionTest.duration_ms">({{ connectionTest.duration_ms }}ms)</span>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="testSystemConnection(selected.id)">{{ t('intTestConnection') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openSystemModal(detail.system)">{{ t('edit') }}</button>
                    <button v-if="!detail.system.is_active" type="button" class="btn btn-sm btn-success" @click="activateSystem">{{ t('uamActivate') }}</button>
                    <button v-else type="button" class="btn btn-sm btn-warning" @click="deactivateSystem">{{ t('uamDeactivate') }}</button>
                  </div>
                </div>
                <div v-show="detailTab === 'feeds'">
                  <div class="d-flex justify-content-between mb-2">
                    <span class="small fw-semibold">{{ t('intFeeds') }}</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openFeedModal()">{{ t('intAddFeed') }}</button>
                  </div>
                  <div v-for="feed in detail.system.feeds || []" :key="feed.id" class="border rounded p-2 mb-2 small">
                    <div class="d-flex justify-content-between">
                      <strong>{{ feed.name }}</strong>
                      <span class="badge bg-label-secondary">{{ feed.direction }}</span>
                    </div>
                    <div class="text-muted">{{ feed.resource_type }} · {{ feed.sync_mode }}</div>
                    <div class="mt-1">
                      <button type="button" class="btn btn-link btn-sm p-0 me-2" @click="runFeed(feed.id)">{{ t('intRunSync') }}</button>
                      <button type="button" class="btn btn-link btn-sm p-0" @click="openFeedModal(feed)">{{ t('edit') }}</button>
                    </div>
                  </div>
                </div>
                <div v-show="detailTab === 'outbound'">
                  <div class="mb-3">
                    <div class="d-flex justify-content-between mb-2">
                      <span class="small fw-semibold">{{ t('intApiClients') }}</span>
                      <button type="button" class="btn btn-sm btn-outline-primary" @click="openClientModal">{{ t('intAddClient') }}</button>
                    </div>
                    <div v-for="client in detail.system.api_clients || []" :key="client.id" class="border rounded p-2 mb-2 small">
                      <strong>{{ client.name }}</strong>
                      <div><code>{{ client.client_id }}</code></div>
                      <div class="text-muted">{{ (client.scopes || []).join(', ') }}</div>
                      <button v-if="client.is_active && !client.revoked_at" type="button" class="btn btn-link btn-sm p-0 text-danger" @click="revokeClient(client.id)">{{ t('intRevoke') }}</button>
                    </div>
                  </div>
                  <div>
                    <div class="d-flex justify-content-between mb-2">
                      <span class="small fw-semibold">{{ t('intWebhooks') }}</span>
                      <button type="button" class="btn btn-sm btn-outline-primary" @click="openWebhookModal">{{ t('intAddWebhook') }}</button>
                    </div>
                    <div v-for="wh in detail.system.webhooks || []" :key="wh.id" class="border rounded p-2 mb-2 small">
                      <strong>{{ wh.name }}</strong>
                      <div class="text-truncate">{{ wh.target_url }}</div>
                      <button type="button" class="btn btn-link btn-sm p-0" @click="testWebhook(wh.id)">{{ t('intTestWebhook') }}</button>
                    </div>
                  </div>
                </div>
                <div v-show="detailTab === 'logs'">
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('date') }}</th><th>{{ t('status') }}</th><th>{{ t('intRecords') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="log in detail.recent_logs || []" :key="log.id">
                          <td class="small">{{ formatDate(log.started_at) }}</td>
                          <td><span class="badge" :class="statusBadge(log.status)">{{ log.status }}</span></td>
                          <td>{{ log.records_processed }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Inbound / sync logs -->
      <div v-show="activeTab === 'inbound'">
        <div class="card int-card mb-3"><div class="card-body small text-muted">{{ t('intInboundSub') }}</div></div>
        <div class="card int-card">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>{{ t('date') }}</th><th>{{ t('intSystem') }}</th><th>{{ t('intFeed') }}</th><th>{{ t('intDirection') }}</th><th>{{ t('status') }}</th><th>{{ t('intRecords') }}</th><th>{{ t('intDuration') }}</th></tr></thead>
              <tbody>
                <tr v-for="log in syncLogs" :key="log.id">
                  <td class="small">{{ formatDate(log.started_at) }}</td>
                  <td class="small">{{ log.system?.system_code }}</td>
                  <td class="small">{{ log.feed?.feed_code || '—' }}</td>
                  <td class="small text-capitalize">{{ log.direction }}</td>
                  <td><span class="badge" :class="statusBadge(log.status)">{{ log.status }}</span></td>
                  <td>{{ log.records_processed }}</td>
                  <td class="small">{{ log.duration_ms ? log.duration_ms + 'ms' : '—' }}</td>
                </tr>
                <tr v-if="!syncLogs.length"><td colspan="7" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Outbound API catalog -->
      <div v-show="activeTab === 'outbound'">
        <div class="card int-card mb-3"><div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
              <div class="small text-muted mb-2">{{ t('intOutboundSub') }}</div>
              <div class="fw-semibold">{{ t('intPartnerBaseUrl') }}: <code>{{ partnerBaseUrl }}</code></div>
              <div class="small text-muted mt-1">{{ t('intAuthHeaders') }}: <code>X-API-Key</code>, <code>X-API-Secret</code></div>
            </div>
            <button type="button" class="btn btn-sm btn-primary" @click="openEndpointModal()">
              <i class="ti ti-plus me-1"></i>{{ t('intAddEndpoint') }}
            </button>
          </div>
        </div></div>
        <div class="card int-card">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr><th>{{ t('intMethod') }}</th><th>{{ t('intPath') }}</th><th>{{ t('intResource') }}</th><th>{{ t('intScopes') }}</th><th>{{ t('catDescriptionEn') }}</th><th></th></tr></thead>
              <tbody>
                <tr v-for="ep in exposedEndpoints" :key="ep.id">
                  <td><span class="badge bg-label-dark">{{ ep.http_method }}</span></td>
                  <td><span class="int-endpoint-path">{{ ep.path }}</span></td>
                  <td class="small text-capitalize">{{ ep.resource_type }}</td>
                  <td class="small"><code>{{ (ep.scopes_required || []).join(', ') || '—' }}</code></td>
                  <td class="small">{{ ep.description_en }}</td>
                  <td><button type="button" class="btn btn-sm btn-link" @click="openEndpointModal(ep)">{{ t('edit') }}</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <!-- System modal -->
    <div v-if="showSystemModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ systemForm.id ? t('intEditSystem') : t('intAddSystem') }}</h5>
          <button type="button" class="btn-close" @click="showSystemModal = false"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">{{ t('intCode') }}</label><input v-model="systemForm.system_code" class="form-control text-uppercase" /></div>
            <div class="col-md-8"><label class="form-label">{{ t('intSystem') }} *</label><input v-model="systemForm.name" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('intType') }}</label>
              <select v-model="systemForm.system_type" class="form-select"><option v-for="tp in lookups.system_types" :key="tp" :value="tp">{{ tp }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t('intDirection') }}</label>
              <select v-model="systemForm.direction" class="form-select"><option v-for="d in lookups.directions" :key="d" :value="d">{{ d }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t('instCountry') }}</label><input v-model="systemForm.country" class="form-control" /></div>
            <div class="col-md-8"><label class="form-label">{{ t('intBaseUrl') }}</label><input v-model="systemForm.base_url" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('intAuthMethod') }}</label>
              <select v-model="systemForm.auth_method" class="form-select"><option v-for="a in lookups.auth_methods" :key="a" :value="a">{{ a }}</option></select></div>
            <div class="col-md-4"><label class="form-label">{{ t('intContactName') }}</label><input v-model="systemForm.contact_name" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('intContact') }}</label><input v-model="systemForm.contact_email" type="email" class="form-control" /></div>
            <div v-if="systemForm.auth_method && systemForm.auth_method !== 'none'" class="col-12">
              <hr class="my-1" />
              <div class="fw-semibold mb-1">{{ t('intAuthCredentials') }}</div>
              <p class="small text-muted">{{ t('intAuthSecretHint') }}</p>
              <div class="row g-3">
                <div v-for="field in authFields" :key="field.key" class="col-md-6">
                  <label class="form-label">{{ field.label }}</label>
                  <select v-if="field.type === 'select'" v-model="systemForm.auth_config[field.key]" class="form-select">
                    <option v-for="opt in (field.options || '').split(',')" :key="opt" :value="opt.trim()">{{ opt.trim() }}</option>
                  </select>
                  <input v-else v-model="systemForm.auth_config[field.key]" :type="field.type === 'password' ? 'password' : 'text'" class="form-control" :placeholder="field.default || ''" />
                </div>
              </div>
              <div class="row g-3 mt-1" v-if="systemForm.id">
                <div class="col-md-8"><label class="form-label">{{ t('intTestUrl') }}</label><input v-model="systemForm.test_url" class="form-control" :placeholder="systemForm.base_url" /></div>
                <div class="col-md-4 d-flex align-items-end">
                  <button type="button" class="btn btn-outline-secondary w-100" :disabled="testingConnection" @click="testSystemConnection(systemForm.id, systemForm.test_url)">
                    {{ testingConnection ? t('loading') : t('intTestConnection') }}
                  </button>
                </div>
              </div>
              <div v-if="connectionTest && showSystemModal" class="alert py-2 small mt-2 mb-0" :class="connectionTest.success ? 'alert-success' : 'alert-danger'">
                {{ connectionTest.message }}
              </div>
            </div>
            <div class="col-md-6"><label class="form-label">{{ t('instIntegrationCode') }}</label>
              <select v-model="systemForm.institution_id" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
              </select></div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionEn') }}</label><textarea v-model="systemForm.description_en" class="form-control" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showSystemModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveSystem">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Feed modal -->
    <div v-if="showFeedModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ feedForm.id ? t('intEditFeed') : t('intAddFeed') }}</h5>
          <button type="button" class="btn-close" @click="showFeedModal = false"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">{{ t('name') }}</label><input v-model="feedForm.name" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('intDirection') }}</label>
              <select v-model="feedForm.direction" class="form-select"><option value="pull">pull</option><option value="push">push</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('intResource') }}</label>
              <select v-model="feedForm.resource_type" class="form-select"><option v-for="r in lookups.resource_types" :key="r" :value="r">{{ r }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('intSyncMode') }}</label>
              <select v-model="feedForm.sync_mode" class="form-select"><option v-for="m in lookups.sync_modes" :key="m" :value="m">{{ m }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('intMethod') }}</label>
              <select v-model="feedForm.http_method" class="form-select"><option>GET</option><option>POST</option></select></div>
            <div class="col-12"><label class="form-label">{{ t('intEndpointUrl') }}</label><input v-model="feedForm.endpoint_url" class="form-control" /></div>
            <div class="col-12"><label class="form-label">{{ t('intSchedule') }}</label><input v-model="feedForm.schedule_cron" class="form-control" placeholder="0 */6 * * *" /></div>
            <div class="col-12"><label class="form-label">{{ t('intFeedHeaders') }}</label><textarea v-model="feedForm.headers_json" class="form-control font-monospace small" rows="3" placeholder='{"Accept":"application/json"}'></textarea></div>
            <div class="col-12"><div class="form-check"><input id="feed-sched" v-model="feedForm.schedule_enabled" type="checkbox" class="form-check-input" /><label class="form-check-label" for="feed-sched">{{ t('intScheduleEnabled') }}</label></div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showFeedModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveFeed">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Client modal -->
    <div v-if="showClientModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('intAddClient') }}</h5><button type="button" class="btn-close" @click="showClientModal = false"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">{{ t('name') }}</label><input v-model="clientForm.name" class="form-control" /></div>
          <div class="mb-3"><label class="form-label">{{ t('intScopes') }}</label>
            <select v-model="clientForm.scopes" class="form-select" multiple size="6">
              <option v-for="s in lookups.scopes" :key="s" :value="s">{{ s }}</option>
            </select></div>
          <div class="mb-3"><label class="form-label">{{ t('intRateLimit') }}</label><input v-model.number="clientForm.rate_limit_per_minute" type="number" min="1" class="form-control" /></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showClientModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveClient">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Exposed endpoint modal -->
    <div v-if="showEndpointModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ endpointForm.id ? t('intEditEndpoint') : t('intAddEndpoint') }}</h5>
          <button type="button" class="btn-close" @click="showEndpointModal = false"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">{{ t('intEndpointCode') }}</label><input v-model="endpointForm.endpoint_code" class="form-control text-uppercase" /></div>
            <div class="col-md-8"><label class="form-label">{{ t('name') }}</label><input v-model="endpointForm.name" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('intMethod') }}</label>
              <select v-model="endpointForm.http_method" class="form-select"><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select></div>
            <div class="col-md-9"><label class="form-label">{{ t('intPath') }}</label><input v-model="endpointForm.path" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('intResource') }}</label>
              <select v-model="endpointForm.resource_type" class="form-select"><option v-for="r in lookups.resource_types" :key="r" :value="r">{{ r }}</option></select></div>
            <div class="col-md-6"><label class="form-label">{{ t('intScopes') }}</label>
              <select v-model="endpointForm.scopes_required" class="form-select" multiple size="4">
                <option v-for="s in lookups.scopes" :key="s" :value="s">{{ s }}</option>
              </select></div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionEn') }}</label><textarea v-model="endpointForm.description_en" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><div class="form-check"><input id="ep-pub" v-model="endpointForm.is_published" type="checkbox" class="form-check-input" /><label class="form-check-label" for="ep-pub">{{ t('intPublished') }}</label></div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showEndpointModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveEndpoint">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Webhook modal -->
    <div v-if="showWebhookModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('intAddWebhook') }}</h5><button type="button" class="btn-close" @click="showWebhookModal = false"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">{{ t('name') }}</label><input v-model="webhookForm.name" class="form-control" /></div>
          <div class="mb-3"><label class="form-label">{{ t('intTargetUrl') }}</label><input v-model="webhookForm.target_url" class="form-control" /></div>
          <div class="mb-3"><label class="form-label">{{ t('intEvents') }}</label>
            <select v-model="webhookForm.event_types" class="form-select" multiple size="5">
              <option v-for="e in lookups.event_types" :key="e" :value="e">{{ e }}</option>
            </select></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showWebhookModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveWebhook">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'

const { t, locale } = useI18n()

const loading = ref(true)
const bootstrapped = ref(false)
const error = ref('')
const message = ref('')
const secretReveal = ref('')
const activeTab = ref('overview')
const detailTab = ref('profile')
const stats = ref({ summary: {}, by_type: [], recent_activity: [] })
const lookups = ref({ system_types: [], directions: [], auth_methods: [], auth_field_schemas: {}, resource_types: [], sync_modes: [], scopes: [], event_types: [], institutions: [] })
const systems = ref([])
const selected = ref(null)
const detail = ref(null)
const syncLogs = ref([])
const exposedEndpoints = ref([])
const partnerBaseUrl = ref('')
const filters = ref({ search: '', system_type: '', direction: '' })

const showSystemModal = ref(false)
const showFeedModal = ref(false)
const showClientModal = ref(false)
const showWebhookModal = ref(false)
const showEndpointModal = ref(false)
const systemForm = ref(emptySystemForm())
const feedForm = ref(emptyFeedForm())
const endpointForm = ref(emptyEndpointForm())
const connectionTest = ref(null)
const testingConnection = ref(false)
const clientForm = ref({ name: '', scopes: [], rate_limit_per_minute: 60 })
const webhookForm = ref({ name: '', target_url: '', event_types: [] })

const summary = computed(() => stats.value.summary || {})
const recentActivity = computed(() => stats.value.recent_activity || [])
const byType = computed(() => stats.value.by_type || [])

const tabs = computed(() => [
  { key: 'overview', label: t('intTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'systems', label: t('intTabSystems'), icon: 'ti ti-server' },
  { key: 'inbound', label: t('intTabInbound'), icon: 'ti ti-download' },
  { key: 'outbound', label: t('intTabOutbound'), icon: 'ti ti-upload' },
])

const detailTabs = computed(() => [
  { key: 'profile', label: t('instTabProfile') },
  { key: 'feeds', label: t('intFeeds') },
  { key: 'outbound', label: t('intTabOutbound') },
  { key: 'logs', label: t('intTabLogs') },
])

const authFields = computed(() => {
  const method = systemForm.value.auth_method || 'none'
  return lookups.value.auth_field_schemas?.[method] || []
})

const overviewTiles = computed(() => [
  { label: t('intWebhooks'), value: summary.value.webhooks ?? 0 },
  { label: t('intExposedApis'), value: summary.value.exposed_endpoints ?? 0 },
  { label: t('intPushFeeds'), value: summary.value.feeds_push ?? 0 },
  { label: t('intFailed7d'), value: summary.value.syncs_failed_7d ?? 0 },
])

function defaultAuthConfig() {
  return {
    api_key: '', header_name: 'X-Api-Key', header_prefix: '', token: '',
    username: '', password: '', client_id: '', client_secret: '', token_url: '',
    grant_type: 'client_credentials', scope: '', login_url: '', login_method: 'POST',
    login_username: '', login_password: '', username_field: 'username', password_field: 'password',
    token_path: 'access_token', auth_header: 'Authorization', auth_prefix: 'Bearer', token_ttl_seconds: 3600,
  }
}

function emptySystemForm() {
  return {
    id: null, system_code: '', name: '', system_type: 'national', direction: 'bidirectional', country: '',
    base_url: '', auth_method: 'api_key', auth_config: defaultAuthConfig(), contact_name: '', contact_email: '',
    institution_id: '', description_en: '', is_active: true, test_url: '',
  }
}

function emptyFeedForm() {
  return { id: null, name: '', direction: 'pull', resource_type: 'complaints', sync_mode: 'incremental', endpoint_url: '', http_method: 'GET', schedule_cron: '', schedule_enabled: false, headers_json: '' }
}

function emptyEndpointForm() {
  return { id: null, endpoint_code: '', name: '', http_method: 'GET', path: '', resource_type: 'custom', scopes_required: [], description_en: '', is_published: true, sort_order: 100 }
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
}

function statusBadge(status) {
  if (status === 'success') return 'bg-label-success'
  if (status === 'failed') return 'bg-label-danger'
  if (status === 'running') return 'bg-label-warning'
  return 'bg-label-secondary'
}

async function refresh() {
  error.value = ''
  message.value = ''
  secretReveal.value = ''
  await loadAll()
}

async function loadAll(initial = false) {
  if (initial) loading.value = true
  try {
    const [statsRes, lookupRes, endpointsRes] = await Promise.all([
      http.get('/integration-mgmt/stats'),
      http.get('/integration-mgmt/lookups'),
      http.get('/integration-mgmt/exposed-endpoints'),
    ])
    stats.value = statsRes.data
    lookups.value = lookupRes.data
    exposedEndpoints.value = endpointsRes.data.endpoints || []
    partnerBaseUrl.value = endpointsRes.data.base_url || ''
    await Promise.all([loadSystems(), loadSyncLogs()])
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('intLoadError')
  } finally {
    loading.value = false
  }
}

async function loadSystems() {
  const params = {}
  Object.entries(filters.value).forEach(([k, v]) => { if (v) params[k] = v })
  const { data } = await http.get('/integration-mgmt/systems', { params })
  systems.value = data.data || []
}

async function loadSyncLogs() {
  const { data } = await http.get('/integration-mgmt/sync-logs', { params: { per_page: 50 } })
  syncLogs.value = data.data || []
}

async function selectSystem(sys) {
  selected.value = sys
  detailTab.value = 'profile'
  activeTab.value = 'systems'
  const { data } = await http.get(`/integration-mgmt/systems/${sys.id}`)
  detail.value = data
}

function closeDetail() {
  selected.value = null
  detail.value = null
}

function openSystemModal(sys = null) {
  connectionTest.value = null
  if (sys) {
    systemForm.value = {
      ...emptySystemForm(),
      ...sys,
      auth_config: { ...defaultAuthConfig(), ...(sys.auth_config || {}) },
      institution_id: sys.institution_id ? String(sys.institution_id) : '',
      test_url: '',
    }
  } else {
    systemForm.value = emptySystemForm()
  }
  showSystemModal.value = true
}

async function testSystemConnection(systemId, testUrl = '') {
  if (!systemId) return
  testingConnection.value = true
  connectionTest.value = null
  try {
    const { data } = await http.post(`/integration-mgmt/systems/${systemId}/test-connection`, { test_url: testUrl || undefined })
    connectionTest.value = data
  } catch (e) {
    connectionTest.value = { success: false, message: e?.response?.data?.message || e?.message || t('intConnectionFailed') }
  } finally {
    testingConnection.value = false
  }
}

async function saveSystem() {
  const payload = { ...systemForm.value }
  delete payload.id
  delete payload.test_url
  payload.institution_id = payload.institution_id || null
  try {
    if (systemForm.value.id) {
      await http.put(`/integration-mgmt/systems/${systemForm.value.id}`, payload)
    } else {
      await http.post('/integration-mgmt/systems', payload)
    }
    message.value = t('cmActionSuccess')
    showSystemModal.value = false
    await loadAll()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function activateSystem() {
  await http.post(`/integration-mgmt/systems/${selected.value.id}/activate`)
  message.value = t('cmActionSuccess')
  await selectSystem(selected.value)
  await loadSystems()
}

async function deactivateSystem() {
  await http.post(`/integration-mgmt/systems/${selected.value.id}/deactivate`)
  message.value = t('cmActionSuccess')
  await selectSystem(selected.value)
  await loadSystems()
}

function openFeedModal(feed = null) {
  if (feed) {
    feedForm.value = {
      ...feed,
      headers_json: feed.headers ? JSON.stringify(feed.headers, null, 2) : '',
    }
  } else {
    feedForm.value = emptyFeedForm()
  }
  showFeedModal.value = true
}

async function saveFeed() {
  const id = selected.value?.id
  if (!id) return
  const payload = { ...feedForm.value }
  delete payload.id
  delete payload.headers_json
  if (feedForm.value.headers_json?.trim()) {
    try {
      payload.headers = JSON.parse(feedForm.value.headers_json)
    } catch {
      error.value = t('cmActionFailed') + ': invalid headers JSON'
      return
    }
  } else {
    payload.headers = null
  }
  try {
    if (feedForm.value.id) {
      await http.put(`/integration-mgmt/feeds/${feedForm.value.id}`, payload)
    } else {
      await http.post(`/integration-mgmt/systems/${id}/feeds`, payload)
    }
    message.value = t('cmActionSuccess')
    showFeedModal.value = false
    await selectSystem(selected.value)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function runFeed(feedId) {
  try {
    const { data } = await http.post(`/integration-mgmt/feeds/${feedId}/run`)
    message.value = t('intSyncComplete') + ': ' + data.log.status
    await loadAll()
    if (selected.value) await selectSystem(selected.value)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

function openClientModal() {
  clientForm.value = { name: '', scopes: ['complaints.read'], rate_limit_per_minute: 60 }
  showClientModal.value = true
}

async function saveClient() {
  try {
    const { data } = await http.post(`/integration-mgmt/systems/${selected.value.id}/api-clients`, clientForm.value)
    secretReveal.value = data.client_secret
    message.value = data.message || t('cmActionSuccess')
    showClientModal.value = false
    await selectSystem(selected.value)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function revokeClient(clientId) {
  await http.post(`/integration-mgmt/api-clients/${clientId}/revoke`)
  message.value = t('cmActionSuccess')
  await selectSystem(selected.value)
}

function openWebhookModal() {
  webhookForm.value = { name: '', target_url: '', event_types: ['complaint.closed'] }
  showWebhookModal.value = true
}

async function saveWebhook() {
  try {
    const { data } = await http.post(`/integration-mgmt/systems/${selected.value.id}/webhooks`, webhookForm.value)
    if (data.signing_secret) secretReveal.value = data.signing_secret
    message.value = t('cmActionSuccess')
    showWebhookModal.value = false
    await selectSystem(selected.value)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function testWebhook(webhookId) {
  try {
    const { data } = await http.post(`/integration-mgmt/webhooks/${webhookId}/test`)
    message.value = t('intWebhookTest') + ': ' + data.log.status
    await loadSyncLogs()
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

function openEndpointModal(ep = null) {
  endpointForm.value = ep ? { ...ep, scopes_required: [...(ep.scopes_required || [])] } : emptyEndpointForm()
  showEndpointModal.value = true
}

async function saveEndpoint() {
  const payload = { ...endpointForm.value }
  delete payload.id
  try {
    if (endpointForm.value.id) {
      await http.put(`/integration-mgmt/exposed-endpoints/${endpointForm.value.id}`, payload)
    } else {
      await http.post('/integration-mgmt/exposed-endpoints', payload)
    }
    message.value = t('cmActionSuccess')
    showEndpointModal.value = false
    const { data } = await http.get('/integration-mgmt/exposed-endpoints')
    exposedEndpoints.value = data.endpoints || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

onMounted(() => loadAll(true))
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>
