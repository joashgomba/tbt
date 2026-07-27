<template>
  <div class="cor-mgmt-enterprise cm-enterprise">
    <section class="cor-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-route"></i></div>
              <div>
                <div class="hero-kicker">{{ t('corHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('corHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('corHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="openCreateModal">
                <i class="ti ti-plus me-1"></i>{{ t('corAddCorridor') }}
              </button>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="cor-stat-grid">
              <div class="cor-stat"><div class="label">{{ t('corTotal') }}</div><div class="value">{{ summary.total ?? 0 }}</div></div>
              <div class="cor-stat"><div class="label">{{ t('corActive') }}</div><div class="value">{{ summary.active ?? 0 }}</div></div>
              <div class="cor-stat"><div class="label">{{ t('corLocations') }}</div><div class="value">{{ summary.total_locations ?? 0 }}</div></div>
              <div class="cor-stat"><div class="label">{{ t('openCases') }}</div><div class="value">{{ summary.open_cases ?? 0 }}</div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="card cor-card mb-3">
      <div class="card-body py-3">
        <ul class="nav cor-tabs flex-wrap gap-2">
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

    <div v-if="loading && !bootstrapped" class="card cor-card"><div class="card-body text-center p-5 text-muted">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="row g-4 mb-4">
          <div v-for="tile in overviewTiles" :key="tile.label" class="col-md-3">
            <div class="card cor-card h-100"><div class="card-body">
              <div class="small text-muted">{{ tile.label }}</div>
              <div class="fs-4 fw-bold">{{ tile.value }}</div>
            </div></div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div v-for="c in corridors.slice(0, 6)" :key="c.id" class="col-md-6 col-xl-4">
            <div class="cor-route-card p-3 h-100 cursor-pointer" @click="selectCorridor(c)">
              <div class="d-flex align-items-start gap-2 mb-2">
                <span class="cor-icon-preview"><i :class="c.icon || 'ti ti-route'"></i></span>
                <div class="flex-grow-1">
                  <span class="cor-code-badge">{{ c.corridor_code }}</span>
                  <div class="fw-semibold mt-1">{{ localizedName(c) }}</div>
                </div>
                <span class="cor-status" :class="c.active ? 'cor-status--active' : 'cor-status--inactive'">{{ c.active ? t('active') : t('inactive') }}</span>
              </div>
              <div class="cor-route-pill mb-2">
                <span>{{ c.origin_country }}</span>
                <i class="ti ti-arrow-right"></i>
                <span>{{ c.destination_country }}</span>
              </div>
              <div class="d-flex flex-wrap gap-2 small text-muted">
                <span><i class="ti ti-map-pin me-1"></i>{{ c.locations_count ?? 0 }} {{ t('corLocations') }}</span>
                <span><i class="ti ti-flag me-1"></i>{{ c.open_cases_count ?? 0 }} {{ t('openCases') }}</span>
                <span v-if="c.primary_mode" class="badge bg-label-warning cor-mode-badge">{{ c.primary_mode }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="card cor-card">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0">{{ t('corLeaderboard') }}</h6>
            <p class="small text-muted mb-0">{{ t('corLeaderboardSub') }}</p>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ t('corridor') }}</th>
                  <th>{{ t('corRoute') }}</th>
                  <th>{{ t('openCases') }}</th>
                  <th>{{ t('catTotalCases') }}</th>
                  <th>{{ t('corLocations') }}</th>
                  <th>{{ t('instResolutionRate') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in leaderboard" :key="row.corridor?.id || i" class="cursor-pointer" @click="selectFromLeaderboard(row.corridor)">
                  <td>{{ i + 1 }}</td>
                  <td>
                    <span class="cor-code-badge me-2">{{ row.corridor?.corridor_code }}</span>
                    <span class="fw-semibold">{{ localizedName(row.corridor) }}</span>
                  </td>
                  <td class="small">{{ row.corridor?.origin_country }} → {{ row.corridor?.destination_country }}</td>
                  <td>{{ row.open_cases }}</td>
                  <td>{{ row.total_cases }}</td>
                  <td>{{ row.locations }}</td>
                  <td>{{ row.resolution_rate }}%</td>
                </tr>
                <tr v-if="!leaderboard.length"><td colspan="7" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Directory -->
      <div v-show="activeTab === 'directory'">
        <div class="card cor-card mb-3"><div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">{{ t('search') }}</label>
              <input v-model="filters.search" type="search" class="form-control" @keyup.enter="loadCorridors" /></div>
            <div class="col-md-2"><label class="form-label">{{ t('status') }}</label>
              <select v-model="filters.active" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option value="true">{{ t('active') }}</option>
                <option value="false">{{ t('inactive') }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('corOrigin') }}</label>
              <select v-model="filters.origin_country" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="c in lookups.countries" :key="'o'+c" :value="c">{{ c }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('corDestination') }}</label>
              <select v-model="filters.destination_country" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="c in lookups.countries" :key="'d'+c" :value="c">{{ c }}</option>
              </select></div>
            <div class="col-md-2"><label class="form-label">{{ t('corPrimaryMode') }}</label>
              <select v-model="filters.primary_mode" class="form-select">
                <option value="">{{ t('all') }}</option>
                <option v-for="m in lookups.modes" :key="m" :value="m">{{ m }}</option>
              </select></div>
            <div class="col-md-1"><button type="button" class="btn btn-primary w-100" @click="loadCorridors">{{ t('applyFilters') }}</button></div>
          </div>
        </div></div>

        <div class="row g-4">
          <div :class="selected ? 'col-lg-7' : 'col-12'">
            <div class="card cor-card">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th></th>
                      <th>{{ t('corCode') }}</th>
                      <th>{{ t('corridor') }}</th>
                      <th>{{ t('corRoute') }}</th>
                      <th>{{ t('corPrimaryMode') }}</th>
                      <th>{{ t('openCases') }}</th>
                      <th>{{ t('status') }}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="c in corridors" :key="c.id" :class="{ 'table-active': selected?.id === c.id }">
                      <td><span class="cor-icon-preview"><i :class="c.icon || 'ti ti-route'"></i></span></td>
                      <td><span class="cor-code-badge">{{ c.corridor_code }}</span></td>
                      <td class="fw-semibold">{{ localizedName(c) }}</td>
                      <td class="small">{{ c.origin_country }} → {{ c.destination_country }}</td>
                      <td><span class="badge bg-label-secondary cor-mode-badge">{{ c.primary_mode || '—' }}</span></td>
                      <td>{{ c.open_cases_count ?? 0 }}</td>
                      <td><span class="cor-status" :class="c.active ? 'cor-status--active' : 'cor-status--inactive'">{{ c.active ? t('active') : t('inactive') }}</span></td>
                      <td><button type="button" class="btn btn-sm btn-link" @click="selectCorridor(c)">{{ t('view') }}</button></td>
                    </tr>
                    <tr v-if="!corridors.length"><td colspan="8" class="text-center text-muted p-4">{{ t('uamNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-if="selected" class="col-lg-5">
            <div class="card cor-card cor-detail-drawer">
              <div class="card-header bg-white d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-start gap-2">
                  <span class="cor-icon-preview"><i :class="detail?.corridor?.icon || selected.icon || 'ti ti-route'"></i></span>
                  <div>
                    <h6 class="mb-1">{{ localizedName(detail?.corridor || selected) }}</h6>
                    <span class="cor-code-badge">{{ detail?.corridor?.corridor_code || selected.corridor_code }}</span>
                    <div class="cor-route-pill mt-2">
                      {{ detail?.corridor?.origin_country || selected.origin_country }}
                      <i class="ti ti-arrow-right"></i>
                      {{ detail?.corridor?.destination_country || selected.destination_country }}
                    </div>
                  </div>
                </div>
                <button type="button" class="btn-close" @click="closeDetail"></button>
              </div>
              <div class="card-body pt-2">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3">
                  <li v-for="dt in detailTabs" :key="dt.key">
                    <button type="button" class="nav-link py-1 px-2 small" :class="{ active: detailTab === dt.key }" @click="detailTab = dt.key">{{ dt.label }}</button>
                  </li>
                </ul>

                <!-- Profile -->
                <div v-show="detailTab === 'profile'">
                  <p class="small mb-2">{{ localizedDescription(detail?.corridor || selected) }}</p>
                  <div class="row g-2 mb-3 small">
                    <div class="col-6"><strong>{{ t('corPrimaryMode') }}:</strong> {{ detail?.corridor?.primary_mode || '—' }}</div>
                    <div class="col-6"><strong>{{ t('corDistance') }}:</strong> {{ detail?.corridor?.distance_km ? `${detail.corridor.distance_km} km` : '—' }}</div>
                    <div class="col-6"><strong>{{ t('corTransitTime') }}:</strong> {{ detail?.corridor?.transit_time_hours ? `${detail.corridor.transit_time_hours}h` : '—' }}</div>
                    <div class="col-6"><strong>{{ t('status') }}:</strong>
                      <span class="cor-status" :class="(detail?.corridor?.active ?? selected.active) ? 'cor-status--active' : 'cor-status--inactive'">
                        {{ (detail?.corridor?.active ?? selected.active) ? t('active') : t('inactive') }}
                      </span></div>
                  </div>
                  <div v-if="detail?.impact" class="row g-2 mb-3">
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('openCases') }}</div><strong>{{ detail.impact.open_cases }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('catTotalCases') }}</div><strong>{{ detail.impact.total_cases }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('corLocations') }}</div><strong>{{ detail.impact.locations }}</strong></div></div>
                    <div class="col-6"><div class="border rounded p-2 small"><div class="text-muted">{{ t('corBorderPosts') }}</div><strong>{{ detail.impact.border_posts }}</strong></div></div>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <button v-if="!(detail?.corridor?.active ?? selected.active)" type="button" class="btn btn-sm btn-success" @click="lifecycleAction('activate')">{{ t('uamActivate') }}</button>
                    <button v-if="detail?.corridor?.active ?? selected.active" type="button" class="btn btn-sm btn-warning" @click="lifecycleModal = true">{{ t('uamDeactivate') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openEditModal">{{ t('edit') }}</button>
                  </div>
                </div>

                <!-- Locations -->
                <div v-show="detailTab === 'locations'">
                  <div class="d-flex justify-content-between mb-2">
                    <h6 class="small fw-semibold mb-0">{{ t('corLocations') }} ({{ corridorLocations.length }})</h6>
                    <button type="button" class="btn btn-sm btn-link" @click="openLocationModal()">{{ t('corAddLocation') }}</button>
                  </div>
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('name') }}</th><th>{{ t('corBorderPost') }}</th><th></th></tr></thead>
                      <tbody>
                        <tr v-for="loc in corridorLocations" :key="loc.id">
                          <td>
                            <div>{{ loc.location_name }}</div>
                            <div class="text-muted small">{{ loc.country }}{{ loc.latitude ? ` · ${loc.latitude}, ${loc.longitude}` : '' }}</div>
                          </td>
                          <td>{{ loc.border_post ? t('yes') : t('no') }}</td>
                          <td class="text-end">
                            <button type="button" class="btn btn-sm btn-link p-0 me-2" @click="openLocationModal(loc)">{{ t('edit') }}</button>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="deleteLocation(loc)">{{ t('delete') }}</button>
                          </td>
                        </tr>
                        <tr v-if="!corridorLocations.length"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Cases -->
                <div v-show="detailTab === 'cases'">
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('caseCode') }}</th><th>{{ t('category') }}</th><th>{{ t('corLocation') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="c in detail?.recent_cases || []" :key="c.id">
                          <td><code>{{ c.complaint_code }}</code></td>
                          <td>{{ c.category?.code || '—' }}</td>
                          <td class="small">{{ c.location?.location_name || '—' }}</td>
                        </tr>
                        <tr v-if="!(detail?.recent_cases?.length)"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Institutions -->
                <div v-show="detailTab === 'institutions'">
                  <ul class="list-unstyled small mb-0">
                    <li v-for="inst in detail?.corridor?.institutions || []" :key="inst.id" class="mb-2">
                      <code>{{ inst.institution_code }}</code> — {{ inst.institution_name }}
                      <span class="text-muted">({{ inst.country_code }})</span>
                    </li>
                    <li v-if="!(detail?.corridor?.institutions?.length)" class="text-muted">{{ t('uamNoData') }}</li>
                  </ul>
                </div>

                <!-- Analytics -->
                <div v-show="detailTab === 'analytics'">
                  <h6 class="small fw-semibold">{{ t('corCasesByCategory') }}</h6>
                  <ul class="list-unstyled small mb-3">
                    <li v-for="row in detail?.cases_by_category || []" :key="row.code" class="d-flex justify-content-between border-bottom py-1">
                      <span><code>{{ row.code }}</code> {{ row.name_en }}</span>
                      <strong>{{ row.count }}</strong>
                    </li>
                    <li v-if="!(detail?.cases_by_category?.length)" class="text-muted">{{ t('uamNoData') }}</li>
                  </ul>
                  <h6 class="small fw-semibold">{{ t('corCasesByLocation') }}</h6>
                  <ul class="list-unstyled small mb-3">
                    <li v-for="row in detail?.cases_by_location || []" :key="row.location_name" class="d-flex justify-content-between border-bottom py-1">
                      <span>{{ row.location_name }}</span>
                      <strong>{{ row.count }}</strong>
                    </li>
                    <li v-if="!(detail?.cases_by_location?.length)" class="text-muted">{{ t('uamNoData') }}</li>
                  </ul>
                  <h6 class="small fw-semibold">{{ t('corCaseTrend') }}</h6>
                  <ul class="list-unstyled small mb-0">
                    <li v-for="row in detail?.case_trend || []" :key="row.month" class="d-flex justify-content-between py-1">
                      <span>{{ row.month }}</span><strong>{{ row.count }}</strong>
                    </li>
                    <li v-if="!(detail?.case_trend?.length)" class="text-muted">{{ t('uamNoData') }}</li>
                  </ul>
                </div>

                <!-- Audit -->
                <div v-show="detailTab === 'audit'">
                  <div class="table-responsive" style="max-height:320px">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>{{ t('date') }}</th><th>{{ t('instAction') }}</th><th>{{ t('user') }}</th></tr></thead>
                      <tbody>
                        <tr v-for="log in auditLogs" :key="log.id">
                          <td class="small">{{ formatDate(log.created_at) }}</td>
                          <td class="small"><code>{{ log.action }}</code></td>
                          <td class="small">{{ log.user?.name || '—' }}</td>
                        </tr>
                        <tr v-if="!auditLogs.length"><td colspan="3" class="text-muted">{{ t('uamNoData') }}</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Corridor form -->
    <div v-if="showFormModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ editingId ? t('corEditCorridor') : t('corAddCorridor') }}</h5>
          <button type="button" class="btn-close" @click="closeFormModal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">{{ t('corCode') }} *</label>
              <input v-model="form.corridor_code" class="form-control text-uppercase" maxlength="50" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('corSortOrder') }}</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('corIcon') }}</label>
              <select v-model="form.icon" class="form-select">
                <option value="">{{ t('none') }}</option>
                <option v-for="ic in lookups.icons" :key="ic" :value="ic">{{ ic }}</option>
              </select></div>
            <div class="col-md-6"><label class="form-label">{{ t('corridor') }} *</label>
              <input v-model="form.corridor_name" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('name') }} (EN)</label>
              <input v-model="form.name_en" class="form-control" /></div>
            <div class="col-md-3"><label class="form-label">{{ t('name') }} (FR)</label>
              <input v-model="form.name_fr" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('corOrigin') }} *</label>
              <select v-model="form.origin_country" class="form-select">
                <option v-for="c in lookups.countries" :key="'fo'+c" :value="c">{{ c }}</option>
              </select></div>
            <div class="col-md-4"><label class="form-label">{{ t('corDestination') }} *</label>
              <select v-model="form.destination_country" class="form-select">
                <option v-for="c in lookups.countries" :key="'fd'+c" :value="c">{{ c }}</option>
              </select></div>
            <div class="col-md-4"><label class="form-label">{{ t('corPrimaryMode') }}</label>
              <select v-model="form.primary_mode" class="form-select">
                <option v-for="m in lookups.modes" :key="m" :value="m">{{ m }}</option>
              </select></div>
            <div class="col-md-4"><label class="form-label">{{ t('corDistance') }} (km)</label>
              <input v-model.number="form.distance_km" type="number" min="1" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">{{ t('corTransitTime') }} (h)</label>
              <input v-model.number="form.transit_time_hours" type="number" min="1" class="form-control" /></div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionEn') }}</label>
              <textarea v-model="form.description_en" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">{{ t('catDescriptionFr') }}</label>
              <textarea v-model="form.description_fr" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">{{ t('catLinkedInstitutions') }}</label>
              <select v-model="form.institution_ids" class="form-select" multiple size="4">
                <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
              </select></div>
            <div class="col-md-6"><div class="form-check mt-2">
              <input id="cor-active" v-model="form.active" type="checkbox" class="form-check-input" />
              <label class="form-check-label" for="cor-active">{{ t('active') }}</label>
            </div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeFormModal">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveCorridor">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Location form -->
    <div v-if="showLocationModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ editingLocationId ? t('corEditLocation') : t('corAddLocation') }}</h5>
          <button type="button" class="btn-close" @click="showLocationModal = false"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">{{ t('name') }} *</label><input v-model="locationForm.location_name" class="form-control" /></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ t('name') }} (EN)</label><input v-model="locationForm.name_en" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('name') }} (FR)</label><input v-model="locationForm.name_fr" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('instCountry') }}</label><input v-model="locationForm.country" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('corRegion') }}</label><input v-model="locationForm.region" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('corLatitude') }}</label><input v-model.number="locationForm.latitude" type="number" step="any" class="form-control" /></div>
            <div class="col-md-6"><label class="form-label">{{ t('corLongitude') }}</label><input v-model.number="locationForm.longitude" type="number" step="any" class="form-control" /></div>
          </div>
          <div class="form-check mt-3">
            <input id="loc-border" v-model="locationForm.border_post" type="checkbox" class="form-check-input" />
            <label class="form-check-label" for="loc-border">{{ t('corBorderPost') }}</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="showLocationModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-primary" @click="saveLocation">{{ t('save') }}</button>
        </div>
      </div></div>
    </div>

    <!-- Deactivate -->
    <div v-if="lifecycleModal" class="modal d-block" tabindex="-1" style="background:rgba(15,23,42,.45)">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ t('uamDeactivate') }}</h5>
          <button type="button" class="btn-close" @click="lifecycleModal = false"></button></div>
        <div class="modal-body">
          <p class="small text-muted">{{ t('corDeactivateHint') }}</p>
          <label class="form-label">{{ t('notes') }}</label>
          <textarea v-model="lifecycleReason" class="form-control" rows="3"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="lifecycleModal = false">{{ t('cancel') }}</button>
          <button type="button" class="btn btn-danger" @click="confirmDeactivate">{{ t('confirm') }}</button>
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
const activeTab = ref('overview')
const detailTab = ref('profile')
const stats = ref({ summary: {}, leaderboard: [] })
const lookups = ref({ countries: [], modes: [], icons: [], institutions: [] })
const corridors = ref([])
const selected = ref(null)
const detail = ref(null)
const auditLogs = ref([])
const corridorLocations = ref([])
const filters = ref({ search: '', active: '', origin_country: '', destination_country: '', primary_mode: '' })
const showFormModal = ref(false)
const editingId = ref(null)
const form = ref(emptyForm())
const showLocationModal = ref(false)
const editingLocationId = ref(null)
const locationForm = ref(emptyLocationForm())
const lifecycleModal = ref(false)
const lifecycleReason = ref('')

const summary = computed(() => stats.value.summary || {})
const leaderboard = computed(() => stats.value.leaderboard || [])

const tabs = computed(() => [
  { key: 'overview', label: t('corTabOverview'), icon: 'ti ti-layout-dashboard' },
  { key: 'directory', label: t('corTabDirectory'), icon: 'ti ti-list-details' },
])

const detailTabs = computed(() => [
  { key: 'profile', label: t('instTabProfile') },
  { key: 'locations', label: t('corLocations') },
  { key: 'cases', label: t('instTabCases') },
  { key: 'institutions', label: t('uamTabInstitutions') },
  { key: 'analytics', label: t('corTabAnalytics') },
  { key: 'audit', label: t('instTabAudit') },
])

const overviewTiles = computed(() => [
  { label: t('corActive'), value: summary.value.active ?? 0 },
  { label: t('corInactive'), value: summary.value.inactive ?? 0 },
  { label: t('corLocations'), value: summary.value.total_locations ?? 0 },
  { label: t('corBorderPosts'), value: summary.value.border_posts ?? 0 },
])

function emptyForm() {
  return {
    corridor_code: '', corridor_name: '', name_en: '', name_fr: '',
    description_en: '', description_fr: '', origin_country: 'Ethiopia', destination_country: 'Djibouti',
    primary_mode: 'multimodal', distance_km: null, transit_time_hours: null,
    icon: 'ti ti-route', sort_order: 0, institution_ids: [], active: true,
  }
}

function emptyLocationForm() {
  return { location_name: '', name_en: '', name_fr: '', country: 'Djibouti', region: '', latitude: null, longitude: null, border_post: false }
}

function localizedName(item) {
  if (!item) return '—'
  return locale.value === 'fr' ? (item.name_fr || item.name_en || item.corridor_name) : (item.name_en || item.name_fr || item.corridor_name)
}

function localizedDescription(item) {
  if (!item) return ''
  return locale.value === 'fr' ? (item.description_fr || item.description_en || '') : (item.description_en || item.description_fr || '')
}

function formatDate(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
}

function buildFilterParams() {
  const p = {}
  Object.entries(filters.value).forEach(([k, v]) => { if (v !== '') p[k] = v })
  return p
}

async function refresh() {
  error.value = ''
  message.value = ''
  await loadAll()
}

async function loadAll(initial = false) {
  if (initial) loading.value = true
  try {
    const [statsRes, lookupRes] = await Promise.all([
      http.get('/corridor-mgmt/stats'),
      http.get('/corridor-mgmt/lookups'),
    ])
    stats.value = statsRes.data
    lookups.value = lookupRes.data
    await loadCorridors()
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('corLoadError')
  } finally {
    loading.value = false
  }
}

async function loadCorridors() {
  const { data } = await http.get('/corridor-mgmt', { params: buildFilterParams() })
  corridors.value = data.data || []
}

async function selectCorridor(c) {
  selected.value = c
  detailTab.value = 'profile'
  activeTab.value = 'directory'
  await loadDetail(c.id)
}

async function selectFromLeaderboard(c) {
  if (!c?.id) return
  activeTab.value = 'directory'
  const found = corridors.value.find(x => x.id === c.id)
  await selectCorridor(found || c)
}

async function loadDetail(id) {
  try {
    const [detailRes, auditRes, locRes] = await Promise.all([
      http.get(`/corridor-mgmt/${id}`),
      http.get(`/corridor-mgmt/${id}/audit-logs`),
      http.get(`/corridor-mgmt/${id}/locations`),
    ])
    detail.value = detailRes.data
    auditLogs.value = auditRes.data.logs || []
    corridorLocations.value = locRes.data.locations || detailRes.data.corridor?.locations || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('corLoadError')
  }
}

function closeDetail() {
  selected.value = null
  detail.value = null
  corridorLocations.value = []
}

function openCreateModal() {
  editingId.value = null
  form.value = emptyForm()
  showFormModal.value = true
}

function openEditModal() {
  const c = detail.value?.corridor
  if (!c) return
  editingId.value = c.id
  form.value = {
    corridor_code: c.corridor_code || '',
    corridor_name: c.corridor_name || '',
    name_en: c.name_en || '',
    name_fr: c.name_fr || '',
    description_en: c.description_en || '',
    description_fr: c.description_fr || '',
    origin_country: c.origin_country || 'Ethiopia',
    destination_country: c.destination_country || 'Djibouti',
    primary_mode: c.primary_mode || 'multimodal',
    distance_km: c.distance_km || null,
    transit_time_hours: c.transit_time_hours || null,
    icon: c.icon || '',
    sort_order: c.sort_order ?? 0,
    institution_ids: (c.institutions || []).map(i => String(i.id)),
    active: c.active ?? true,
  }
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  editingId.value = null
}

function payloadFromForm() {
  const p = { ...form.value }
  p.corridor_code = (p.corridor_code || '').toUpperCase()
  p.institution_ids = (p.institution_ids || []).map(Number)
  if (!p.distance_km) p.distance_km = null
  if (!p.transit_time_hours) p.transit_time_hours = null
  return p
}

async function saveCorridor() {
  const savedId = editingId.value
  try {
    if (savedId) {
      await http.put(`/corridor-mgmt/${savedId}`, payloadFromForm())
    } else {
      const { data } = await http.post('/corridor-mgmt', payloadFromForm())
      selected.value = data.corridor
    }
    message.value = t('cmActionSuccess')
    closeFormModal()
    await loadAll()
    const id = savedId || selected.value?.id
    if (id) await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

function openLocationModal(loc = null) {
  editingLocationId.value = loc?.id || null
  locationForm.value = loc ? {
    location_name: loc.location_name || '',
    name_en: loc.name_en || '',
    name_fr: loc.name_fr || '',
    country: loc.country || 'Djibouti',
    region: loc.region || '',
    latitude: loc.latitude ?? null,
    longitude: loc.longitude ?? null,
    border_post: loc.border_post ?? false,
  } : emptyLocationForm()
  showLocationModal.value = true
}

async function saveLocation() {
  const id = selected.value?.id
  if (!id) return
  try {
    if (editingLocationId.value) {
      await http.put(`/corridor-mgmt/locations/${editingLocationId.value}`, locationForm.value)
    } else {
      await http.post(`/corridor-mgmt/${id}/locations`, locationForm.value)
    }
    showLocationModal.value = false
    message.value = t('cmActionSuccess')
    await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function deleteLocation(loc) {
  if (!confirm(t('corConfirmDeleteLocation'))) return
  try {
    await http.delete(`/corridor-mgmt/locations/${loc.id}`)
    message.value = t('cmActionSuccess')
    await loadDetail(selected.value.id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function lifecycleAction(action) {
  const id = selected.value?.id
  if (!id) return
  try {
    if (action === 'activate') await http.post(`/corridor-mgmt/${id}/activate`)
    message.value = t('cmActionSuccess')
    await loadAll()
    await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

async function confirmDeactivate() {
  const id = selected.value?.id
  if (!id) return
  try {
    await http.post(`/corridor-mgmt/${id}/deactivate`, { reason: lifecycleReason.value || null })
    lifecycleModal.value = false
    lifecycleReason.value = ''
    message.value = t('cmActionSuccess')
    await loadAll()
    await loadDetail(id)
  } catch (e) {
    error.value = e?.response?.data?.message || t('cmActionFailed')
  }
}

onMounted(() => loadAll(true))
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>
