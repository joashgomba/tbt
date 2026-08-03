<?php

use App\Http\Controllers\Api\V1\Admin\AuditController;
use App\Http\Controllers\Api\V1\Admin\AuditManagementController;
use App\Http\Controllers\Api\V1\Admin\ComplaintController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\CategoryManagementController;
use App\Http\Controllers\Api\V1\Admin\CorridorManagementController;
use App\Http\Controllers\Api\V1\Admin\IntegrationManagementController;
use App\Http\Controllers\Api\V1\Admin\InstitutionManagementController;
use App\Http\Controllers\Api\V1\Admin\LocationManagementController;
use App\Http\Controllers\Api\V1\Admin\MasterDataController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\UserAccessController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\WorkflowConfigController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Public\PublicComplaintController;
use App\Http\Controllers\Api\V1\Public\PublicTransparencyController;
use App\Http\Controllers\Api\V1\Partner\PartnerIntegrationController;
use App\Http\Controllers\Api\V1\Public\SmsComplaintController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/mfa/verify', [AuthController::class, 'verifyMfa']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('/me', [AuthController::class, 'me']);
    Route::middleware('auth')->prefix('mfa')->group(function () {
        Route::get('/status', [MfaController::class, 'status']);
        Route::post('/enroll', [MfaController::class, 'enroll']);
        Route::post('/enroll/verify', [MfaController::class, 'verifyEnrollment']);
    });
});

Route::prefix('public')->group(function () {
    Route::get('/lookups', [PublicComplaintController::class, 'lookups']);
    Route::post('/complaints', [PublicComplaintController::class, 'store']);
    Route::get('/complaints/{code}', [PublicComplaintController::class, 'show']);
    Route::post('/sms/complaints', [SmsComplaintController::class, 'store']);
    Route::get('/transparency/lookups', [PublicTransparencyController::class, 'lookups']);
    Route::get('/transparency/stats', [PublicTransparencyController::class, 'stats']);
    Route::get('/transparency/reports', [PublicTransparencyController::class, 'reports']);
    Route::get('/transparency/map', [PublicTransparencyController::class, 'mapPoints']);
});

Route::prefix('partner')->middleware('integration.client')->group(function () {
    Route::get('/catalog', [PartnerIntegrationController::class, 'catalog']);
    Route::get('/complaints', [PartnerIntegrationController::class, 'complaints']);
    Route::get('/complaints/{code}', [PartnerIntegrationController::class, 'showComplaint']);
    Route::get('/institutions', [PartnerIntegrationController::class, 'institutions']);
    Route::get('/master-data', [PartnerIntegrationController::class, 'masterData']);
    Route::get('/reports/summary', [PartnerIntegrationController::class, 'reportsSummary']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('complaints')->group(function () {
        Route::get('/stats', [ComplaintController::class, 'stats']);
        Route::get('/lookups', [ComplaintController::class, 'lookups']);
        Route::get('/', [ComplaintController::class, 'index']);
        Route::get('/{id}', [ComplaintController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/attachments/{attachmentId}', [ComplaintController::class, 'downloadAttachment'])->whereNumber(['id', 'attachmentId']);
        Route::post('/{id}/validate', [ComplaintController::class, 'validateComplaint'])->whereNumber('id');
        Route::post('/{id}/reject', [ComplaintController::class, 'reject'])->whereNumber('id');
        Route::post('/{id}/request-info', [ComplaintController::class, 'requestInfo'])->whereNumber('id');
        Route::post('/{id}/assign', [ComplaintController::class, 'assign'])->whereNumber('id');
        Route::post('/{id}/process', [ComplaintController::class, 'process'])->whereNumber('id');
        Route::post('/{id}/resolve', [ComplaintController::class, 'resolve'])->whereNumber('id');
        Route::post('/{id}/close', [ComplaintController::class, 'close'])->whereNumber('id');
        Route::post('/{id}/archive', [ComplaintController::class, 'archive'])->whereNumber('id');
        Route::post('/{id}/reopen', [ComplaintController::class, 'reopen'])->whereNumber('id');
        Route::post('/{id}/escalate', [ComplaintController::class, 'escalate'])->whereNumber('id');
        Route::post('/{id}/comment', [ComplaintController::class, 'comment'])->whereNumber('id');
    });

    Route::prefix('workflow')->group(function () {
        Route::get('/stats', [WorkflowConfigController::class, 'stats']);
        Route::get('/lookups', [WorkflowConfigController::class, 'lookups']);
        Route::match(['get', 'post'], '/transitions', [WorkflowConfigController::class, 'transitions']);
        Route::put('/transitions/{id}', [WorkflowConfigController::class, 'updateTransition'])->whereNumber('id');
        Route::delete('/transitions/{id}', [WorkflowConfigController::class, 'deleteTransition'])->whereNumber('id');
        Route::match(['get', 'post'], '/sla-configurations', [WorkflowConfigController::class, 'slaConfigurations']);
        Route::put('/sla-configurations/{id}', [WorkflowConfigController::class, 'updateSlaConfiguration'])->whereNumber('id');
        Route::delete('/sla-configurations/{id}', [WorkflowConfigController::class, 'deleteSlaConfiguration'])->whereNumber('id');
        Route::match(['get', 'post'], '/notification-templates', [WorkflowConfigController::class, 'notificationTemplates']);
        Route::put('/notification-templates/{id}', [WorkflowConfigController::class, 'updateNotificationTemplate'])->whereNumber('id');
        Route::delete('/notification-templates/{id}', [WorkflowConfigController::class, 'deleteNotificationTemplate'])->whereNumber('id');
        Route::match(['get', 'put'], '/system-settings', [WorkflowConfigController::class, 'systemSettings']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/stats', [ReportController::class, 'stats']);
        Route::get('/lookups', [ReportController::class, 'lookups']);
        Route::get('/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/catalogue', [ReportController::class, 'catalogue']);
        Route::get('/kpis', [ReportController::class, 'kpis']);
        Route::get('/operational/{type}', [ReportController::class, 'operational']);
        Route::get('/institution-workload', [ReportController::class, 'institutionWorkload']);
        Route::get('/gender-inclusion', [ReportController::class, 'genderInclusion']);
        Route::get('/cases', [ReportController::class, 'cases']);
        Route::get('/case-history/{id}', [ReportController::class, 'caseHistory'])->whereNumber('id');
        Route::post('/preview', [ReportController::class, 'preview']);
        Route::post('/generate', [ReportController::class, 'generate']);
        Route::get('/instances', [ReportController::class, 'instances']);
        Route::get('/instances/{id}', [ReportController::class, 'showInstance'])->whereNumber('id');
        Route::get('/instances/{id}/download', [ReportController::class, 'downloadInstance'])->whereNumber('id');
        Route::post('/instances/{id}/retry', [ReportController::class, 'retryInstance'])->whereNumber('id');
        Route::get('/schedules', [ReportController::class, 'schedules']);
        Route::post('/schedules', [ReportController::class, 'storeSchedule']);
        Route::put('/schedules/{id}', [ReportController::class, 'updateSchedule'])->whereNumber('id');
        Route::post('/schedules/{id}/suspend', [ReportController::class, 'suspendSchedule'])->whereNumber('id');
        Route::post('/schedules/{id}/resume', [ReportController::class, 'resumeSchedule'])->whereNumber('id');
        Route::delete('/schedules/{id}', [ReportController::class, 'deleteSchedule'])->whereNumber('id');
        Route::get('/saved-filters', [ReportController::class, 'savedFilters']);
        Route::post('/saved-filters', [ReportController::class, 'storeSavedFilter']);
        Route::delete('/saved-filters/{id}', [ReportController::class, 'deleteSavedFilter'])->whereNumber('id');
        Route::get('/export-logs', [ReportController::class, 'exportLogs']);
        Route::get('/export.csv', [ReportController::class, 'exportCsv']);
        Route::get('/export.xlsx', [ReportController::class, 'exportExcel']);
        Route::get('/export.pdf', [ReportController::class, 'exportPdf']);
        Route::match(['get', 'post'], '/admin/definitions', [ReportController::class, 'adminDefinitions']);
        Route::put('/admin/definitions/{id}', [ReportController::class, 'updateDefinition'])->whereNumber('id');
        Route::match(['get', 'post'], '/admin/kpi-definitions', [ReportController::class, 'adminKpiDefinitions']);
        Route::put('/admin/kpi-definitions/{id}', [ReportController::class, 'updateKpiDefinition'])->whereNumber('id');
    });

    Route::match(['get', 'post'], '/institutions', [MasterDataController::class, 'institutions']);
    Route::put('/institutions/{id}', [MasterDataController::class, 'updateInstitution'])->whereNumber('id');

    Route::prefix('institution-mgmt')->group(function () {
        Route::get('/stats', [InstitutionManagementController::class, 'stats']);
        Route::get('/lookups', [InstitutionManagementController::class, 'lookups']);
        Route::get('/', [InstitutionManagementController::class, 'index']);
        Route::post('/', [InstitutionManagementController::class, 'store']);
        Route::get('/{id}', [InstitutionManagementController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [InstitutionManagementController::class, 'update'])->whereNumber('id');
        Route::post('/{id}/activate', [InstitutionManagementController::class, 'activate'])->whereNumber('id');
        Route::post('/{id}/suspend', [InstitutionManagementController::class, 'suspend'])->whereNumber('id');
        Route::post('/{id}/deactivate', [InstitutionManagementController::class, 'deactivate'])->whereNumber('id');
        Route::get('/{id}/users', [InstitutionManagementController::class, 'users'])->whereNumber('id');
        Route::match(['get', 'post'], '/{id}/units', [InstitutionManagementController::class, 'units'])->whereNumber('id');
        Route::put('/units/{unitId}', [InstitutionManagementController::class, 'updateUnit'])->whereNumber('unitId');
        Route::get('/{id}/audit-logs', [InstitutionManagementController::class, 'auditLogs'])->whereNumber('id');
    });
    Route::match(['get', 'post'], '/categories', [MasterDataController::class, 'categories']);
    Route::put('/categories/{id}', [MasterDataController::class, 'updateCategory'])->whereNumber('id');

    Route::prefix('category-mgmt')->group(function () {
        Route::get('/stats', [CategoryManagementController::class, 'stats']);
        Route::get('/lookups', [CategoryManagementController::class, 'lookups']);
        Route::get('/', [CategoryManagementController::class, 'index']);
        Route::post('/', [CategoryManagementController::class, 'store']);
        Route::get('/{id}', [CategoryManagementController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [CategoryManagementController::class, 'update'])->whereNumber('id');
        Route::post('/{id}/activate', [CategoryManagementController::class, 'activate'])->whereNumber('id');
        Route::post('/{id}/deactivate', [CategoryManagementController::class, 'deactivate'])->whereNumber('id');
        Route::get('/{id}/audit-logs', [CategoryManagementController::class, 'auditLogs'])->whereNumber('id');
    });
    Route::match(['get', 'post'], '/corridors', [MasterDataController::class, 'corridors']);

    Route::prefix('corridor-mgmt')->group(function () {
        Route::get('/stats', [CorridorManagementController::class, 'stats']);
        Route::get('/lookups', [CorridorManagementController::class, 'lookups']);
        Route::get('/', [CorridorManagementController::class, 'index']);
        Route::post('/', [CorridorManagementController::class, 'store']);
        Route::get('/{id}', [CorridorManagementController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [CorridorManagementController::class, 'update'])->whereNumber('id');
        Route::post('/{id}/activate', [CorridorManagementController::class, 'activate'])->whereNumber('id');
        Route::post('/{id}/deactivate', [CorridorManagementController::class, 'deactivate'])->whereNumber('id');
        Route::match(['get', 'post'], '/{id}/locations', [CorridorManagementController::class, 'locations'])->whereNumber('id');
        Route::put('/locations/{locationId}', [CorridorManagementController::class, 'updateLocation'])->whereNumber('locationId');
        Route::delete('/locations/{locationId}', [CorridorManagementController::class, 'deleteLocation'])->whereNumber('locationId');
        Route::get('/{id}/audit-logs', [CorridorManagementController::class, 'auditLogs'])->whereNumber('id');
    });
    Route::match(['get', 'post'], '/locations', [MasterDataController::class, 'locations']);

    Route::prefix('location-mgmt')->group(function () {
        Route::get('/stats', [LocationManagementController::class, 'stats']);
        Route::get('/lookups', [LocationManagementController::class, 'lookups']);
        Route::get('/map', [LocationManagementController::class, 'mapData']);
        Route::get('/', [LocationManagementController::class, 'index']);
        Route::post('/', [LocationManagementController::class, 'store']);
        Route::get('/{id}', [LocationManagementController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [LocationManagementController::class, 'update'])->whereNumber('id');
        Route::post('/{id}/activate', [LocationManagementController::class, 'activate'])->whereNumber('id');
        Route::post('/{id}/deactivate', [LocationManagementController::class, 'deactivate'])->whereNumber('id');
        Route::get('/{id}/audit-logs', [LocationManagementController::class, 'auditLogs'])->whereNumber('id');
    });

    Route::prefix('uam')->group(function () {
        Route::get('/stats', [UserAccessController::class, 'stats']);
        Route::get('/lookups', [UserAccessController::class, 'lookups']);
        Route::get('/users', [UserAccessController::class, 'users']);
        Route::post('/users', [UserAccessController::class, 'storeUser']);
        Route::get('/users/{id}', [UserAccessController::class, 'showUser'])->whereNumber('id');
        Route::put('/users/{id}', [UserAccessController::class, 'updateUser'])->whereNumber('id');
        Route::post('/users/{id}/activate', [UserAccessController::class, 'activateUser'])->whereNumber('id');
        Route::post('/users/{id}/suspend', [UserAccessController::class, 'suspendUser'])->whereNumber('id');
        Route::post('/users/{id}/deactivate', [UserAccessController::class, 'deactivateUser'])->whereNumber('id');
        Route::post('/users/{id}/unlock', [UserAccessController::class, 'unlockUser'])->whereNumber('id');
        Route::post('/users/{id}/role', [UserAccessController::class, 'assignRole'])->whereNumber('id');
        Route::post('/users/{id}/reset-password', [UserAccessController::class, 'resetPassword'])->whereNumber('id');
        Route::get('/roles', [UserAccessController::class, 'roles']);
        Route::post('/roles', [UserAccessController::class, 'storeRole']);
        Route::put('/roles/{id}', [UserAccessController::class, 'updateRole'])->whereNumber('id');
        Route::get('/roles/{id}', [UserAccessController::class, 'showRole'])->whereNumber('id');
        Route::get('/permissions', [UserAccessController::class, 'permissions']);
        Route::get('/permission-matrix', [UserAccessController::class, 'permissionMatrix']);
        Route::get('/institutions', [UserAccessController::class, 'institutions']);
        Route::post('/institutions', [UserAccessController::class, 'storeInstitution']);
        Route::put('/institutions/{id}', [UserAccessController::class, 'updateInstitution'])->whereNumber('id');
        Route::get('/institutions/{id}/users', [UserAccessController::class, 'institutionUsers'])->whereNumber('id');
        Route::match(['get', 'post'], '/institutions/{id}/units', [UserAccessController::class, 'units'])->whereNumber('id');
        Route::match(['get', 'post'], '/delegations', [UserAccessController::class, 'delegations']);
        Route::post('/delegations/{id}/revoke', [UserAccessController::class, 'revokeDelegation'])->whereNumber('id');
        Route::get('/access-reviews', [UserAccessController::class, 'accessReviews']);
        Route::post('/access-reviews', [UserAccessController::class, 'storeAccessReview']);
        Route::get('/access-reviews/{id}', [UserAccessController::class, 'showAccessReview'])->whereNumber('id');
        Route::post('/access-reviews/{id}/certify', [UserAccessController::class, 'certifyAccessReview'])->whereNumber('id');
        Route::post('/users/{id}/reset-mfa', [UserAccessController::class, 'resetUserMfa'])->whereNumber('id');
        Route::get('/notifications', [UserAccessController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [UserAccessController::class, 'markNotificationRead'])->whereNumber('id');
        Route::get('/login-attempts', [UserAccessController::class, 'loginAttempts']);
        Route::get('/reports/privileged', [UserAccessController::class, 'privilegedReport']);
    });

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update'])->whereNumber('id');
    Route::get('/roles', [UserController::class, 'roles']);

    Route::get('/audit-logs', [AuditController::class, 'index']);

    Route::prefix('audit-mgmt')->group(function () {
        Route::get('/stats', [AuditManagementController::class, 'stats']);
        Route::get('/lookups', [AuditManagementController::class, 'lookups']);
        Route::get('/entity-trail', [AuditManagementController::class, 'entityTrail']);
        Route::get('/', [AuditManagementController::class, 'index']);
        Route::get('/{id}', [AuditManagementController::class, 'show'])->whereNumber('id');
    });

    Route::prefix('integration-mgmt')->group(function () {
        Route::get('/stats', [IntegrationManagementController::class, 'stats']);
        Route::get('/lookups', [IntegrationManagementController::class, 'lookups']);
        Route::get('/systems', [IntegrationManagementController::class, 'indexSystems']);
        Route::post('/systems', [IntegrationManagementController::class, 'storeSystem']);
        Route::get('/systems/{id}', [IntegrationManagementController::class, 'showSystem'])->whereNumber('id');
        Route::put('/systems/{id}', [IntegrationManagementController::class, 'updateSystem'])->whereNumber('id');
        Route::post('/systems/{id}/activate', [IntegrationManagementController::class, 'activateSystem'])->whereNumber('id');
        Route::post('/systems/{id}/deactivate', [IntegrationManagementController::class, 'deactivateSystem'])->whereNumber('id');
        Route::get('/systems/{id}/audit-logs', [IntegrationManagementController::class, 'auditLogs'])->whereNumber('id');
        Route::post('/systems/{id}/feeds', [IntegrationManagementController::class, 'storeFeed'])->whereNumber('id');
        Route::put('/feeds/{feedId}', [IntegrationManagementController::class, 'updateFeed'])->whereNumber('feedId');
        Route::post('/feeds/{feedId}/run', [IntegrationManagementController::class, 'runFeed'])->whereNumber('feedId');
        Route::post('/systems/{id}/api-clients', [IntegrationManagementController::class, 'storeApiClient'])->whereNumber('id');
        Route::post('/api-clients/{clientId}/revoke', [IntegrationManagementController::class, 'revokeApiClient'])->whereNumber('clientId');
        Route::post('/api-clients/{clientId}/rotate-secret', [IntegrationManagementController::class, 'rotateApiClientSecret'])->whereNumber('clientId');
        Route::post('/systems/{id}/webhooks', [IntegrationManagementController::class, 'storeWebhook'])->whereNumber('id');
        Route::put('/webhooks/{webhookId}', [IntegrationManagementController::class, 'updateWebhook'])->whereNumber('webhookId');
        Route::post('/webhooks/{webhookId}/test', [IntegrationManagementController::class, 'testWebhook'])->whereNumber('webhookId');
        Route::get('/exposed-endpoints', [IntegrationManagementController::class, 'exposedEndpoints']);
        Route::get('/sync-logs', [IntegrationManagementController::class, 'syncLogs']);
    });
});
