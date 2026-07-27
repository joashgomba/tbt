<template>
  <div class="rpt-enterprise cm-enterprise">
    <!-- Hero -->
    <section class="rpt-hero cm-hero card border-0 mb-4">
      <div class="card-body p-4 p-xl-5">
        <div class="row g-4 align-items-center">
          <div class="col-xl-8">
            <div class="d-flex align-items-start gap-3">
              <div class="hero-emblem"><i class="ti ti-chart-bar"></i></div>
              <div>
                <div class="hero-kicker">{{ t('rptHeroKicker') }}</div>
                <h1 class="hero-title mb-2">{{ t('rptHeroTitle') }}</h1>
                <p class="hero-sub mb-0">{{ t('rptHeroSubtitle') }}</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <button type="button" class="btn btn-light btn-sm" :disabled="loading" @click="refresh">
                <i class="ti ti-refresh me-1"></i>{{ loading ? t('refreshing') : t('refreshNow') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="exportCurrent('csv')">
                <i class="ti ti-table me-1"></i>{{ t('rptExportCsv') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="exportCurrent('pdf')">
                <i class="ti ti-file-type-pdf me-1"></i>{{ t('rptExportPdf') }}
              </button>
              <button type="button" class="btn btn-outline-light btn-sm" @click="exportCurrent('xlsx')">
                <i class="ti ti-file-spreadsheet me-1"></i>{{ t('rptExportExcel') }}
              </button>
            </div>
          </div>
          <div class="col-xl-4">
            <div class="rpt-stat-grid">
              <div class="rpt-stat">
                <div class="label">{{ t('totalCases') }}</div>
                <div class="value">{{ stats.summary.total ?? stats.summary.open + stats.summary.closed }}</div>
              </div>
              <div class="rpt-stat">
                <div class="label">{{ t('rptCatalogueCount') }}</div>
                <div class="value">{{ stats.summary.catalogue_count ?? 0 }}</div>
              </div>
              <div class="rpt-stat">
                <div class="label">{{ t('slaCompliance') }}</div>
                <div class="value">{{ stats.summary.sla_compliance ?? 0 }}%</div>
              </div>
              <div class="rpt-stat">
                <div class="label">{{ t('rptCutoff') }}</div>
                <div class="value small">{{ cutoffLabel }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div v-if="me?.institution_id && !canViewAll" class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-center gap-3">
      <i class="ti ti-building fs-4"></i>
      <div>
        <strong>{{ t('rptInstitutionScoped') }}</strong>
        <div class="small">{{ t('rptInstitutionScopedText') }} <strong>{{ me.institution }}</strong>.</div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="card rpt-card mb-3">
      <div class="card-body py-3">
        <ul class="nav rpt-tabs flex-wrap gap-2">
          <li v-for="tab in visibleTabs" :key="tab.key">
            <button type="button" class="nav-link" :class="{ active: activeTab === tab.key }" @click="setTab(tab.key)">
              <i :class="tab.icon" class="me-1"></i>{{ tab.label }}
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Filters -->
    <div class="card rpt-card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-2">
            <label class="form-label">{{ t('filterYear') }}</label>
            <select v-model="filters.year" class="form-select">
              <option value="">{{ t('all') }}</option>
              <option v-for="y in yearOptions" :key="y" :value="String(y)">{{ y }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('period') }}</label>
            <select v-model="filters.period" class="form-select">
              <option value="">{{ t('all') }}</option>
              <option v-for="p in lookups.periods" :key="p.value" :value="p.value">{{ localizedPeriod(p) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('category') }}</label>
            <select v-model="filters.category_id" class="form-select">
              <option value="">{{ t('allCategories') }}</option>
              <option v-for="c in lookups.categories" :key="c.id" :value="String(c.id)">{{ localizedName(c) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('corridor') }}</label>
            <select v-model="filters.corridor_id" class="form-select">
              <option value="">{{ t('allCorridors') }}</option>
              <option v-for="c in lookups.corridors" :key="c.id" :value="String(c.id)">{{ localizedName(c) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('institution') }}</label>
            <select v-model="filters.institution_id" class="form-select">
              <option value="">{{ t('allInstitutions') }}</option>
              <option v-for="i in lookups.institutions" :key="i.id" :value="String(i.id)">{{ i.institution_name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('gender') }}</label>
            <select v-model="filters.gender" class="form-select">
              <option value="">{{ t('all') }}</option>
              <option v-for="g in lookups.genders" :key="g.value" :value="g.value">{{ localizedGender(g) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ t('priority') }}</label>
            <select v-model="filters.priority" class="form-select">
              <option value="">{{ t('allPriorities') }}</option>
              <option value="HIGH">HIGH</option>
              <option value="MED">MED</option>
              <option value="LOW">LOW</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">SLA</label>
            <select v-model="filters.sla_condition" class="form-select">
              <option value="">{{ t('all') }}</option>
              <option v-for="s in lookups.sla_conditions" :key="s.value" :value="s.value">{{ localizedSla(s) }}</option>
            </select>
          </div>
          <div class="col-md-3 d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm" :disabled="loading" @click="applyFilters">{{ t('applyFilters') }}</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters">{{ t('resetFilters') }}</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="saveFilterPrompt">{{ t('rptSaveFilter') }}</button>
          </div>
        </div>
        <div v-if="activeFilterChips.length" class="mt-3 d-flex flex-wrap gap-2 align-items-center">
          <span class="small text-muted">{{ t('rptActiveFilters') }}:</span>
          <span v-for="chip in activeFilterChips" :key="chip" class="rpt-filter-chip">{{ chip }}</span>
        </div>
        <div v-if="savedFilters.length" class="mt-2 d-flex flex-wrap gap-2 align-items-center">
          <span class="small text-muted">{{ t('rptSavedFilters') }}:</span>
          <button v-for="sf in savedFilters" :key="sf.id" type="button" class="btn btn-sm btn-outline-secondary" @click="applySavedFilter(sf)">{{ sf.name }}</button>
        </div>
      </div>
    </div>

    <div v-if="qualityWarnings.length" class="alert alert-warning border-0 mb-4">
      <strong>{{ t('rptQualityWarning') }}</strong>
      <ul class="mb-0 small mt-1">
        <li v-for="w in qualityWarnings" :key="w.code">{{ w.code }} — {{ w.count }} ({{ w.pct }}%)</li>
      </ul>
    </div>

    <div v-if="message" class="alert alert-success">{{ message }}</div>
    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div v-if="loading && !bootstrapped" class="card rpt-card"><div class="card-body rpt-empty">{{ t('loading') }}</div></div>

    <template v-else>
      <!-- Overview -->
      <div v-show="activeTab === 'overview'">
        <div class="rpt-kpi-grid mb-4">
          <div v-for="tile in kpiTiles" :key="tile.label" class="rpt-kpi" :class="tile.trendClass">
            <div class="kpi-label">{{ tile.label }}</div>
            <div class="kpi-value">{{ tile.value }}</div>
            <div class="kpi-meta">{{ tile.sub }}</div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-lg-8">
            <div class="card rpt-card h-100">
              <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('monthlyTrend') }}</h6></div>
              <div class="card-body">
                <apexchart v-if="dashboard.by_month?.length" type="area" height="280" :options="monthOptions" :series="monthSeries" />
                <div v-else class="rpt-empty">{{ t('rptNoData') }}</div>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card rpt-card h-100">
              <div class="card-header bg-white border-0"><h6 class="mb-0">{{ t('byCategory') }}</h6></div>
              <div class="card-body">
                <apexchart v-if="dashboard.by_category?.length" type="donut" height="280" :options="categoryOptions" :series="categorySeries" />
                <div v-else class="rpt-empty">{{ t('rptNoData') }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card rpt-card mb-4">
          <div class="card-header bg-white border-0">
            <h6 class="mb-0">{{ t('rptLeaderboard') }}</h6>
            <p class="small text-muted mb-0">{{ t('rptLeaderboardSub') }}</p>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0 rpt-leaderboard-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ t('institution') }}</th>
                  <th>{{ t('casesAssigned') }}</th>
                  <th>{{ t('openCases') }}</th>
                  <th>{{ t('resolutionRate') }}</th>
                  <th>{{ t('slaCompliance') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!leaderboard.length"><td colspan="6" class="rpt-empty">{{ t('rptNoData') }}</td></tr>
                <tr v-for="(row, idx) in leaderboard" :key="row.institution?.id || idx">
                  <td>{{ idx + 1 }}</td>
                  <td class="fw-semibold">{{ row.institution?.institution_name || '—' }}</td>
                  <td>{{ row.total_cases }}</td>
                  <td>{{ row.open_cases }}</td>
                  <td>{{ row.resolution_rate }}%</td>
                  <td><span :class="pctClass(row.sla_pct)">{{ row.sla_pct }}%</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Catalogue -->
      <div v-show="activeTab === 'catalogue'">
        <div class="row g-3">
          <div v-for="item in catalogue" :key="item.id" class="col-md-6 col-xl-4">
            <div class="rpt-catalogue-item">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-label-primary rpt-badge">{{ item.code }}</span>
                <span class="badge bg-label-secondary rpt-badge">{{ item.confidentiality }}</span>
              </div>
              <h6 class="mb-1">{{ item.title }}</h6>
              <p class="small text-muted mb-2">{{ item.description }}</p>
              <div class="small text-muted mb-3">{{ item.category }} · {{ item.default_frequency }}</div>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="previewReport(item)">{{ t('rptPreview') }}</button>
                <button type="button" class="btn btn-sm btn-primary" @click="generateReport(item, 'pdf')">{{ t('rptGenerate') }}</button>
              </div>
            </div>
          </div>
        </div>
        <div v-if="previewData" class="card rpt-card mt-4">
          <div class="card-header bg-white d-flex justify-content-between">
            <h6 class="mb-0">{{ previewData.report?.title }}</h6>
            <button type="button" class="btn-close" @click="previewData = null"></button>
          </div>
          <div class="card-body">
            <pre class="small bg-light p-3 rounded mb-0" style="max-height: 320px; overflow: auto">{{ JSON.stringify(previewData.summary, null, 2) }}</pre>
          </div>
        </div>
      </div>

      <!-- Operational -->
      <div v-show="activeTab === 'operational'">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button v-for="op in operationalTypes" :key="op.key" type="button" class="btn btn-sm" :class="operationalType === op.key ? 'btn-primary' : 'btn-outline-primary'" @click="loadOperational(op.key)">{{ op.label }}</button>
        </div>
        <div class="card rpt-card">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ t('caseCode') }}</th>
                  <th>{{ t('title') }}</th>
                  <th>{{ t('category') }}</th>
                  <th>{{ t('status') }}</th>
                  <th>{{ t('institution') }}</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!operationalRows.length"><td colspan="6" class="rpt-empty">{{ t('rptNoData') }}</td></tr>
                <tr v-for="row in operationalRows" :key="row.id">
                  <td><RouterLink :to="{ name: 'admin-complaint-detail', params: { id: row.id } }">{{ row.complaint_code }}</RouterLink></td>
                  <td>{{ row.title }}</td>
                  <td>{{ localizedName(row.category) }}</td>
                  <td><span class="badge bg-label-primary">{{ localizedName(row.status) }}</span></td>
                  <td>{{ row.assigned_institution?.institution_name || '—' }}</td>
                  <td><RouterLink class="btn btn-sm btn-link" :to="{ name: 'admin-complaint-detail', params: { id: row.id } }">{{ t('rptDrillDown') }}</RouterLink></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Strategic -->
      <div v-show="activeTab === 'strategic'">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptComparison') }}</h6></div>
              <div class="card-body">
                <div v-if="!comparisonKeys.length" class="rpt-empty">{{ t('rptNoData') }}</div>
                <div v-for="key in comparisonKeys" :key="key" class="mb-3">
                  <div class="fw-semibold text-capitalize">{{ key.replace(/_/g, ' ') }}</div>
                  <div class="small">{{ dashboard.comparison?.[key]?.current }} vs {{ dashboard.comparison?.[key]?.previous }}
                    <span v-if="dashboard.comparison?.[key]?.growth_rate != null"> ({{ dashboard.comparison[key].growth_rate }}%)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('byCorridor') }}</h6></div>
              <div class="card-body">
                <apexchart v-if="dashboard.by_corridor?.length" type="bar" height="260" :options="corridorOptions" :series="corridorSeries" />
                <div v-else class="rpt-empty">{{ t('rptNoData') }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Analytical -->
      <div v-show="activeTab === 'analytical'">
        <div class="row g-4 mb-4">
          <div class="col-lg-6">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptAgeing') }}</h6></div>
              <div class="card-body">
                <apexchart v-if="dashboard.ageing?.length" type="bar" height="240" :options="ageingOptions" :series="ageingSeries" />
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptRecurrence') }}</h6></div>
              <div class="card-body p-0">
                <table class="table mb-0">
                  <thead><tr><th>{{ t('category') }}</th><th>{{ t('location') }}</th><th>#</th></tr></thead>
                  <tbody>
                    <tr v-for="r in dashboard.recurrence" :key="r.category + r.location"><td>{{ r.category }}</td><td>{{ r.location }}</td><td>{{ r.occurrences }}</td></tr>
                    <tr v-if="!dashboard.recurrence?.length"><td colspan="3" class="rpt-empty">{{ t('rptNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="card rpt-card">
          <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptStageTimes') }}</h6></div>
          <div class="card-body">
            <apexchart v-if="dashboard.stage_times?.length" type="bar" height="220" :options="stageOptions" :series="stageSeries" />
          </div>
        </div>
      </div>

      <!-- Geographic -->
      <div v-show="activeTab === 'geographic'">
        <div class="card rpt-card mb-4">
          <div class="card-header bg-white"><h6 class="mb-0">{{ t('mapTitle') }}</h6></div>
          <div class="card-body p-0 p-md-3">
            <div class="rpt-map-container">
              <PublicBarrierMap
                v-if="activeTab === 'geographic' && mapPoints.length"
                ref="geoMapRef"
                :points="mapPoints"
                height="28rem"
              />
              <div v-else-if="activeTab === 'geographic'" class="rpt-empty">{{ t('rptNoData') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Inclusion -->
      <div v-show="activeTab === 'inclusion'">
        <div class="row g-4">
          <div class="col-lg-5">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('gender') }}</h6></div>
              <div class="card-body">
                <apexchart v-if="inclusion.volumes?.length" type="donut" height="260" :options="genderOptions" :series="genderSeries" />
              </div>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptWomenCategories') }}</h6></div>
              <div class="card-body p-0">
                <table class="table mb-0">
                  <tbody>
                    <tr v-for="(c, idx) in inclusion.women_trader_categories" :key="c.ntb_category_id || idx"><td>{{ localizedName(c.category) }}</td><td>{{ c.total }}</td></tr>
                    <tr v-if="!inclusion.women_trader_categories?.length"><td colspan="2" class="rpt-empty">{{ t('rptNoData') }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-if="inclusion.privacy_suppressed?.length" class="small text-muted mt-2">{{ t('rptPrivacySuppressed') }}</p>
          </div>
        </div>
      </div>

      <!-- KPIs -->
      <div v-show="activeTab === 'kpis'">
        <div class="row g-3">
          <div v-for="kpi in kpiDefinitions" :key="kpi.code" class="col-md-6 col-xl-4">
            <div class="rpt-kpi h-100" :class="kpiTrendClass(kpi.trend)">
              <div class="kpi-label">{{ localizedKpiTitle(kpi) }}</div>
              <div class="kpi-value">{{ kpi.value ?? '—' }}{{ kpi.unit === '%' ? '%' : kpi.unit ? ` ${kpi.unit}` : '' }}</div>
              <div class="kpi-meta">{{ t('rptTarget') }}: {{ kpi.target_value ?? '—' }}</div>
              <div class="small text-muted mt-2">{{ t('rptFormula') }}: {{ kpi.formula }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Schedules -->
      <div v-show="activeTab === 'schedules'">
        <div class="d-flex justify-content-between mb-3">
          <h6 class="mb-0">{{ t('rptTabSchedules') }}</h6>
          <button type="button" class="btn btn-sm btn-primary" @click="showScheduleModal = true">{{ t('rptAddSchedule') }}</button>
        </div>
        <div class="card rpt-card">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>{{ t('rptReportCode') }}</th><th>{{ t('rptFrequency') }}</th><th>{{ t('rptFormat') }}</th><th>{{ t('status') }}</th><th></th></tr></thead>
              <tbody>
                <tr v-for="s in schedules" :key="s.id">
                  <td>{{ s.definition?.code }}</td>
                  <td>{{ s.frequency }}</td>
                  <td>{{ s.output_format }}</td>
                  <td><span class="badge" :class="s.is_suspended ? 'bg-warning' : 'bg-success'">{{ s.is_suspended ? t('rptSuspend') : t('wfActive') }}</span></td>
                  <td class="text-end">
                    <button v-if="s.is_suspended" type="button" class="btn btn-sm btn-outline-primary me-1" @click="resumeSchedule(s)">{{ t('rptResume') }}</button>
                    <button v-else type="button" class="btn btn-sm btn-outline-warning me-1" @click="suspendSchedule(s)">{{ t('rptSuspend') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteSchedule(s)">{{ t('delete') }}</button>
                  </td>
                </tr>
                <tr v-if="!schedules.length"><td colspan="5" class="rpt-empty">{{ t('rptNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- History -->
      <div v-show="activeTab === 'history'">
        <div class="card rpt-card mb-4">
          <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptGenerated') }}</h6></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>{{ t('rptReportCode') }}</th><th>{{ t('status') }}</th><th>{{ t('rptFormat') }}</th><th>{{ t('submittedOn') }}</th><th></th></tr></thead>
              <tbody>
                <tr v-for="inst in instances" :key="inst.id">
                  <td>{{ inst.definition?.code || inst.metadata?.report_code }}</td>
                  <td><span class="badge bg-label-primary">{{ statusLabel(inst.status) }}</span></td>
                  <td>{{ inst.file_format }}</td>
                  <td>{{ formatDate(inst.generated_at) }}</td>
                  <td class="text-end">
                    <button v-if="inst.status === 'completed'" type="button" class="btn btn-sm btn-outline-primary me-1" @click="downloadInstance(inst)">{{ t('rptDownload') }}</button>
                    <button v-if="inst.status === 'failed'" type="button" class="btn btn-sm btn-outline-warning" @click="retryInstance(inst)">{{ t('rptRetry') }}</button>
                  </td>
                </tr>
                <tr v-if="!instances.length"><td colspan="5" class="rpt-empty">{{ t('rptNoData') }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card rpt-card">
          <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptTabHistory') }}</h6></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>{{ t('rptFormat') }}</th><th>{{ t('status') }}</th><th>{{ t('submittedOn') }}</th></tr></thead>
              <tbody>
                <tr v-for="log in exportLogs" :key="log.id"><td>{{ log.format }}</td><td>{{ log.status }}</td><td>{{ formatDate(log.created_at) }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Admin -->
      <div v-show="activeTab === 'admin' && canAdmin">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptTabCatalogue') }}</h6></div>
              <div class="list-group list-group-flush">
                <div v-for="def in adminDefinitions" :key="def.id" class="list-group-item d-flex justify-content-between">
                  <span>{{ def.code }} — {{ def.title_en }}</span>
                  <span class="badge" :class="def.is_active ? 'bg-success' : 'bg-secondary'">{{ def.is_active ? t('wfActive') : t('wfInactive') }}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card rpt-card">
              <div class="card-header bg-white"><h6 class="mb-0">{{ t('rptTabKpis') }}</h6></div>
              <div class="list-group list-group-flush">
                <div v-for="k in adminKpis" :key="k.id" class="list-group-item">
                  <div class="fw-semibold">{{ k.code }}</div>
                  <div class="small text-muted">v{{ k.version }} · {{ k.formula }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Schedule modal -->
    <div v-if="showScheduleModal" class="modal d-block" tabindex="-1" style="background: rgba(15,23,42,0.45)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ t('rptAddSchedule') }}</h5>
            <button type="button" class="btn-close" @click="showScheduleModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">{{ t('rptReportCode') }}</label>
              <select v-model="scheduleForm.report_definition_id" class="form-select">
                <option v-for="item in catalogue" :key="item.id" :value="item.id">{{ item.code }} — {{ item.title }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ t('rptFrequency') }}</label>
              <select v-model="scheduleForm.frequency" class="form-select">
                <option value="daily">daily</option>
                <option value="weekly">weekly</option>
                <option value="monthly">monthly</option>
                <option value="quarterly">quarterly</option>
                <option value="annual">annual</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ t('rptFormat') }}</label>
              <select v-model="scheduleForm.output_format" class="form-select">
                <option value="pdf">PDF</option>
                <option value="xlsx">Excel</option>
                <option value="csv">CSV</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showScheduleModal = false">{{ t('cancel') }}</button>
            <button type="button" class="btn btn-primary" @click="createSchedule">{{ t('save') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { http } from '@/api/http'
import { useI18n } from '@/composables/useI18n'
import PublicBarrierMap from '@/components/public/PublicBarrierMap.vue'

const { t, localizedName, locale } = useI18n()

const loading = ref(true)
const bootstrapped = ref(false)
const error = ref('')
const message = ref('')
const me = ref(null)
const activeTab = ref('overview')
const stats = ref({ summary: {} })
const dashboard = ref({})
const catalogue = ref([])
const kpiDefinitions = ref([])
const inclusion = ref({})
const schedules = ref([])
const instances = ref([])
const exportLogs = ref([])
const savedFilters = ref([])
const lookups = ref({ categories: [], corridors: [], institutions: [], periods: [], genders: [], sla_conditions: [] })
const operationalRows = ref([])
const operationalType = ref('open')
const previewData = ref(null)
const showScheduleModal = ref(false)
const adminDefinitions = ref([])
const adminKpis = ref([])
const cutoffAt = ref(null)
const geoMapRef = ref(null)

const scheduleForm = ref({ report_definition_id: '', frequency: 'monthly', output_format: 'pdf' })

const yearOptions = (() => { const y = new Date().getFullYear(); return [y - 3, y - 2, y - 1, y, y + 1] })()

const filters = ref({
  year: String(new Date().getFullYear()),
  period: '',
  category_id: '',
  corridor_id: '',
  institution_id: '',
  gender: '',
  priority: '',
  sla_condition: '',
})

const canViewAll = computed(() => me.value?.permissions?.includes('complaints.view_all') ?? false)
const canAdmin = computed(() => me.value?.permissions?.includes('reports.admin') ?? false)

const visibleTabs = computed(() => {
  const tabs = [
    { key: 'overview', label: t('rptTabOverview'), icon: 'ti ti-layout-dashboard' },
    { key: 'catalogue', label: t('rptTabCatalogue'), icon: 'ti ti-books' },
    { key: 'operational', label: t('rptTabOperational'), icon: 'ti ti-list-check' },
    { key: 'strategic', label: t('rptTabStrategic'), icon: 'ti ti-chart-line' },
    { key: 'analytical', label: t('rptTabAnalytical'), icon: 'ti ti-chart-dots' },
    { key: 'geographic', label: t('rptTabGeographic'), icon: 'ti ti-map' },
    { key: 'inclusion', label: t('rptTabInclusion'), icon: 'ti ti-users' },
    { key: 'kpis', label: t('rptTabKpis'), icon: 'ti ti-gauge' },
    { key: 'schedules', label: t('rptTabSchedules'), icon: 'ti ti-calendar-event' },
    { key: 'history', label: t('rptTabHistory'), icon: 'ti ti-history' },
  ]
  if (canAdmin.value) tabs.push({ key: 'admin', label: t('rptTabAdmin'), icon: 'ti ti-settings' })
  return tabs
})

const operationalTypes = computed(() => [
  { key: 'open', label: t('rptOperationalOpen') },
  { key: 'closed', label: t('rptOperationalClosed') },
  { key: 'overdue', label: t('rptOperationalOverdue') },
  { key: 'escalated', label: t('rptOperationalEscalated') },
  { key: 'pending_validation', label: t('rptOperationalValidation') },
])

const leaderboard = computed(() => stats.value.leaderboard || dashboard.value.leaderboard || [])
const qualityWarnings = computed(() => dashboard.value.quality_warnings || [])
const mapPoints = computed(() => dashboard.value.map_points || [])
const comparisonKeys = computed(() => Object.keys(dashboard.value.comparison || {}))

const cutoffLabel = computed(() =>
  cutoffAt.value ? new Date(cutoffAt.value).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB') : '—',
)

const kpiTiles = computed(() => {
  const s = dashboard.value.summary || stats.value.summary || {}
  return [
    { label: t('totalCases'), value: s.total ?? '—', sub: t('filteredPeriod'), trendClass: 'trend-neutral' },
    { label: t('resolutionRate'), value: `${s.resolution_rate ?? 0}%`, sub: t('closedVsTotal'), trendClass: 'trend-good' },
    { label: t('slaCompliance'), value: `${s.sla_compliance ?? 0}%`, sub: t('withinSlaTarget'), trendClass: (s.sla_compliance ?? 0) >= 80 ? 'trend-good' : 'trend-warn' },
    { label: t('backlog'), value: s.backlog ?? s.open ?? '—', sub: t('openCases'), trendClass: 'trend-neutral' },
    { label: t('avgResolutionDays'), value: s.avg_resolution_days ?? '—', sub: t('daysToClose'), trendClass: 'trend-neutral' },
    { label: t('escalationRate'), value: `${s.escalation_rate ?? 0}%`, sub: t('rptEscalations'), trendClass: 'trend-neutral' },
  ]
})

const activeFilterChips = computed(() =>
  Object.entries(filters.value).filter(([, v]) => v).map(([k, v]) => `${k}: ${v}`),
)

function buildParams() {
  const p = {}
  Object.entries(filters.value).forEach(([k, v]) => { if (v) p[k] = v })
  p.locale = locale.value
  return p
}

function localizedPeriod(p) { return locale.value === 'fr' ? p.label_fr : p.label_en }
function localizedGender(g) { return locale.value === 'fr' ? g.label_fr : g.label_en }
function localizedSla(s) { return locale.value === 'fr' ? s.label_fr : s.label_en }
function localizedKpiTitle(k) { return locale.value === 'fr' ? k.title_fr : k.title_en }

function pctClass(v) {
  if (v >= 90) return 'text-success fw-semibold'
  if (v >= 70) return 'text-warning fw-semibold'
  return 'text-danger fw-semibold'
}

function kpiTrendClass(trend) {
  if (trend === 'improved') return 'trend-good'
  if (trend === 'deteriorated') return 'trend-warn'
  return 'trend-neutral'
}

function statusLabel(s) {
  return { queued: t('rptStatusQueued'), processing: t('rptStatusProcessing'), completed: t('rptStatusCompleted'), failed: t('rptStatusFailed') }[s] || s
}

function formatDate(v) {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return new Date(v).toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-GB')
  }
}

async function refresh() { await loadAll(true) }

async function loadAll(manual = false) {
  if (manual) loading.value = true
  error.value = ''
  try {
    const params = buildParams()
    const [meRes, statsRes, dashRes, catRes, kpiRes, inclRes, schedRes, instRes, logsRes, sfRes, lookupsRes] = await Promise.all([
      http.get('/auth/me').catch(() => ({ data: {} })),
      http.get('/reports/stats', { params }),
      http.get('/reports/dashboard', { params }),
      http.get('/reports/catalogue', { params }),
      http.get('/reports/kpis', { params }),
      http.get('/reports/gender-inclusion', { params }),
      http.get('/reports/schedules'),
      http.get('/reports/instances'),
      http.get('/reports/export-logs').catch(() => ({ data: { logs: [] } })),
      http.get('/reports/saved-filters').catch(() => ({ data: { saved_filters: [] } })),
      http.get('/reports/lookups'),
    ])
    me.value = meRes.data?.user || null
    stats.value = statsRes.data
    dashboard.value = dashRes.data
    catalogue.value = catRes.data.catalogue || []
    kpiDefinitions.value = kpiRes.data.kpis || []
    inclusion.value = inclRes.data || {}
    schedules.value = schedRes.data.schedules || []
    instances.value = Array.isArray(instRes.data?.data) ? instRes.data.data : []
    exportLogs.value = logsRes.data.logs || []
    savedFilters.value = sfRes.data.saved_filters || []
    lookups.value = {
      categories: lookupsRes?.data?.categories || [],
      corridors: lookupsRes?.data?.corridors || [],
      institutions: lookupsRes?.data?.institutions || [],
      periods: lookupsRes?.data?.periods || [],
      genders: lookupsRes?.data?.genders || [],
      sla_conditions: lookupsRes?.data?.sla_conditions || [],
    }
    cutoffAt.value = statsRes.data.cutoff_at || dashRes.data.cutoff_at
    if (canAdmin.value) {
      const [defRes, adminKpiRes] = await Promise.all([
        http.get('/reports/admin/definitions'),
        http.get('/reports/admin/kpi-definitions'),
      ])
      adminDefinitions.value = defRes.data.definitions || []
      adminKpis.value = adminKpiRes.data.kpis || []
    }
    await loadOperational(operationalType.value)
    bootstrapped.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || t('rptLoadError')
  } finally {
    loading.value = false
  }
}

onMounted(() => loadAll(true))

function resetFilters() {
  filters.value = { year: String(new Date().getFullYear()), period: '', category_id: '', corridor_id: '', institution_id: '', gender: '', priority: '', sla_condition: '' }
  loadAll(true)
}

function setTab(key) {
  activeTab.value = key
  if (key === 'geographic') {
    nextTick(() => geoMapRef.value?.refreshMapSize?.())
  }
}

watch(activeTab, (key) => {
  if (key === 'geographic') {
    nextTick(() => geoMapRef.value?.refreshMapSize?.())
  }
})

async function loadOperational(type) {
  operationalType.value = type
  try {
    const { data } = await http.get(`/reports/operational/${type}`, { params: buildParams() })
    operationalRows.value = data.rows || []
  } catch { operationalRows.value = [] }
}

async function previewReport(item) {
  try {
    const { data } = await http.post('/reports/preview', { report_definition_id: item.id, ...buildParams() })
    previewData.value = data
  } catch (e) { error.value = e?.response?.data?.message || t('rptLoadError') }
}

async function generateReport(item, format) {
  try {
    await http.post('/reports/generate', { report_definition_id: item.id, format, ...buildParams() })
    message.value = t('rptGenerateSuccess')
    const { data } = await http.get('/reports/instances')
    instances.value = data.data || data || []
  } catch (e) { error.value = e?.response?.data?.message || t('rptLoadError') }
}

function exportCurrent(format) {
  const q = new URLSearchParams(buildParams()).toString()
  window.open(`/api/v1/reports/export.${format === 'xlsx' ? 'xlsx' : format}?${q}`, '_blank')
}

async function downloadInstance(inst) {
  window.open(`/api/v1/reports/instances/${inst.id}/download`, '_blank')
}

async function retryInstance(inst) {
  await http.post(`/reports/instances/${inst.id}/retry`)
  message.value = t('rptGenerateSuccess')
  await loadAll()
}

async function createSchedule() {
  await http.post('/reports/schedules', scheduleForm.value)
  showScheduleModal.value = false
  message.value = t('cmActionSuccess')
  const { data } = await http.get('/reports/schedules')
  schedules.value = data.schedules || []
}

async function suspendSchedule(s) { await http.post(`/reports/schedules/${s.id}/suspend`); await loadAll() }
async function resumeSchedule(s) { await http.post(`/reports/schedules/${s.id}/resume`); await loadAll() }
async function deleteSchedule(s) { if (confirm(t('wfConfirmDelete'))) { await http.delete(`/reports/schedules/${s.id}`); await loadAll() } }

async function saveFilterPrompt() {
  const name = prompt(t('rptSaveFilter'))
  if (!name) return
  await http.post('/reports/saved-filters', { name, filters: filters.value })
  const { data } = await http.get('/reports/saved-filters')
  savedFilters.value = data.saved_filters || []
}

function applySavedFilter(sf) {
  filters.value = { ...filters.value, ...sf.filters }
  applyFilters()
}

const monthSeries = computed(() => [{ name: t('cases'), data: (dashboard.value.by_month || []).map((x) => Number(x.total || 0)) }])
const monthOptions = computed(() => ({
  chart: { toolbar: { show: false }, fontFamily: 'inherit' },
  stroke: { curve: 'smooth' },
  colors: ['#0b4f6c'],
  xaxis: { categories: (dashboard.value.by_month || []).map((x) => x.month) },
}))

const categorySeries = computed(() => (dashboard.value.by_category || []).map((x) => Number(x.count || 0)))
const categoryOptions = computed(() => ({
  labels: (dashboard.value.by_category || []).map((x) => x.category?.code || 'N/A'),
  legend: { position: 'bottom' },
}))

const corridorSeries = computed(() => [{ name: t('cases'), data: (dashboard.value.by_corridor || []).map((x) => Number(x.count || 0)) }])
const corridorOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
  xaxis: { categories: (dashboard.value.by_corridor || []).map((x) => localizedName(x.corridor) || x.corridor?.corridor_code) },
}))

const ageingSeries = computed(() => [{ name: t('cases'), data: (dashboard.value.ageing || []).map((x) => x.count) }])
const ageingOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  xaxis: { categories: (dashboard.value.ageing || []).map((x) => x.label) },
}))

const stageSeries = computed(() => [{ name: 'hours', data: (dashboard.value.stage_times || []).map((x) => x.avg_hours || 0) }])
const stageOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  xaxis: { categories: (dashboard.value.stage_times || []).map((x) => x.stage) },
}))

const genderSeries = computed(() => (inclusion.value.volumes || []).map((x) => Number(x.total || 0)))
const genderOptions = computed(() => ({
  labels: (inclusion.value.volumes || []).map((x) => x.gender_key === 'not_stated' ? t('rptNotStated') : x.gender_key),
  legend: { position: 'bottom' },
}))

async function applyFilters() { await loadAll(true) }
</script>
