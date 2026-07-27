<?php

namespace Database\Seeders;

use App\Models\ReportDefinition;
use App\Models\ReportInstance;
use App\Models\ReportKpiDefinition;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;

class NtbReportsSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['RPT-01', 'operational', 'Daily Operational Dashboard', 'Tableau opérationnel quotidien', 'Daily summary for NFP and NMC', 'Quotidien pour le PFN et le CNM', 'daily', ['web', 'pdf'], ['period'], 'internal', ['kpi', 'line']],
            ['RPT-02', 'operational', 'Open Cases by Institution', 'Cas ouverts par institution', 'Weekly open cases grouped by institution', 'Cas ouverts hebdomadaires par institution', 'weekly', ['excel', 'pdf'], ['period', 'institution_id'], 'internal', ['bar', 'table']],
            ['RPT-03', 'operational', 'Cases in SLA Breach', 'Cas en dépassement SLA', 'Cases exceeding applicable SLA deadlines', 'Cas dépassant les délais SLA applicables', 'daily', ['web', 'pdf', 'email'], ['sla_condition'], 'confidential', ['table']],
            ['RPT-04', 'operational', 'Escalated Cases in Progress', 'Cas escaladés en cours', 'Real-time escalated case register', 'Registre des cas escaladés en temps réel', 'real_time', ['web'], ['escalation_level'], 'internal', ['table']],
            ['RPT-05', 'operational', 'Complete Case History', 'Historique complet du dossier', 'Chronological workflow history for a selected case', 'Historique workflow chronologique pour un dossier', 'on_demand', ['pdf'], ['complaint_id'], 'confidential', ['timeline']],
            ['RPT-06', 'strategic', 'Monthly Activity Report', 'Rapport mensuel d\'activité', 'Monthly volumes, resolutions, SLA and trends', 'Volumes, résolutions, SLA et tendances mensuels', 'monthly', ['pdf', 'excel'], ['month'], 'internal', ['kpi', 'line', 'bar']],
            ['RPT-07', 'strategic', 'Quarterly Evaluation Report', 'Rapport d\'évaluation trimestriel', 'Quarterly performance vs prior periods', 'Performance trimestrielle vs périodes antérieures', 'quarterly', ['pdf'], ['quarter', 'year'], 'confidential', ['kpi', 'comparison']],
            ['RPT-08', 'strategic', 'National Annual NTB Report', 'Rapport national annuel OTN', 'Consolidated annual national report', 'Rapport national annuel consolidé', 'annual', ['pdf'], ['year'], 'restricted', ['kpi', 'narrative']],
            ['RPT-09', 'strategic', 'Sectoral Report by NTB Type', 'Rapport sectoriel par type OTN', 'Sectoral breakdown by NTB category', 'Ventilation sectorielle par catégorie OTN', 'quarterly', ['pdf', 'excel'], ['category_id', 'quarter'], 'internal', ['bar', 'pie']],
            ['RPT-10', 'regional', 'COMESA Regional Report', 'Rapport régional COMESA', 'Approved COMESA regional dataset', 'Jeu de données régional COMESA approuvé', 'monthly', ['pdf', 'json'], ['month'], 'restricted', ['table', 'api']],
            ['RPT-11', 'regional', 'AfCFTA Continental Report', 'Rapport continental ZLECAf', 'Approved AfCFTA continental dataset', 'Jeu de données continental ZLECAf approuvé', 'quarterly', ['pdf', 'json'], ['quarter'], 'restricted', ['table', 'api']],
            ['RPT-12', 'analytical', 'NTB Trend Analysis', 'Analyse des tendances OTN', 'Complaint volume trends over time', 'Tendances des volumes de plaintes', 'monthly', ['web', 'pdf'], ['year'], 'internal', ['line']],
            ['RPT-13', 'analytical', 'Recurring Causes Analysis', 'Analyse des causes récurrentes', 'Repeated NTB categories and locations', 'Catégories et localisations OTN répétées', 'quarterly', ['pdf'], ['period'], 'internal', ['table']],
            ['RPT-14', 'analytical', 'Evolution of Resolution Timeframes', 'Évolution des délais de résolution', 'Resolution time analysis by stage', 'Analyse des délais par étape', 'monthly', ['web', 'excel'], ['year'], 'internal', ['line', 'bar']],
            ['RPT-15', 'analytical', 'Institutional Performance Analysis', 'Analyse de performance institutionnelle', 'Institution comparison on workload and SLA', 'Comparaison institutionnelle charge et SLA', 'monthly', ['web', 'pdf', 'excel'], ['institution_id'], 'internal', ['bar', 'leaderboard']],
            ['RPT-16', 'analytical', 'Geographic Mapping of NTBs', 'Cartographie géographique des OTN', 'Interactive NTB concentration map', 'Carte interactive des concentrations OTN', 'monthly', ['web', 'pdf'], ['corridor_id'], 'internal', ['map', 'heatmap']],
            ['RPT-17', 'analytical', 'Affected Corridors Analysis', 'Analyse des corridors affectés', 'Corridor performance and bottlenecks', 'Performance et goulots d\'étranglement des corridors', 'monthly', ['web', 'pdf'], ['corridor_id'], 'internal', ['bar']],
            ['RPT-18', 'analytical', 'Gender and Inclusion Report', 'Rapport genre et inclusion', 'Gender-disaggregated indicators', 'Indicateurs désagrégés par genre', 'quarterly', ['pdf', 'excel'], ['gender'], 'internal', ['stacked', 'bar']],
            ['RPT-19', 'operational', 'Complaints by Submission Channel', 'Plaintes par canal de soumission', 'Channel usage analysis', 'Analyse d\'utilisation des canaux', 'monthly', ['web', 'excel'], ['submission_channel'], 'internal', ['pie', 'bar']],
            ['RPT-20', 'operational', 'Case Ageing and Backlog Report', 'Rapport d\'ancienneté et backlog', 'Open case ageing bands and backlog', 'Tranches d\'ancienneté et backlog', 'weekly', ['web', 'pdf', 'excel'], ['period'], 'internal', ['bar', 'table']],
        ];

        foreach ($definitions as $i => [$code, $category, $titleEn, $titleFr, $descEn, $descFr, $freq, $formats, $params, $conf, $charts]) {
            ReportDefinition::query()->updateOrCreate(
                ['code' => $code],
                [
                    'category' => $category,
                    'title_en' => $titleEn,
                    'title_fr' => $titleFr,
                    'description_en' => $descEn,
                    'description_fr' => $descFr,
                    'default_frequency' => $freq,
                    'output_formats' => $formats,
                    'parameters' => $params,
                    'required_permissions' => in_array($category, ['regional'], true)
                        ? ['reports.generate', 'dashboards.executive']
                        : ['reports.generate'],
                    'confidentiality' => $conf,
                    'chart_types' => $charts,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ],
            );
        }

        $kpis = [
            ['total_complaints', 'Total complaints', 'Total des plaintes', 'Count of complaints received in period', 'Nombre de plaintes reçues sur la période', 'COUNT(complaints)', 'cases', 100, null],
            ['validated_complaints', 'Validation rate', 'Taux de validation', 'Validated as NTB cases / total × 100', 'Validées OTN / total × 100', 'validated / total × 100', '%', 85, 70],
            ['resolution_rate', 'Resolution rate', 'Taux de résolution', 'Resolved / eligible × 100', 'Résolus / éligibles × 100', 'resolved / eligible × 100', '%', 80, 65],
            ['closure_rate', 'Closure rate', 'Taux de clôture', 'Closed / total cohort × 100', 'Clôturés / cohorte × 100', 'closed / total × 100', '%', 75, 60],
            ['sla_compliance_rate', 'SLA compliance', 'Conformité SLA', 'Completed within SLA / subject to SLA × 100', 'Dans SLA / soumis au SLA × 100', 'within_sla / sla_subject × 100', '%', 90, 75],
            ['sla_breach_rate', 'SLA breach rate', 'Taux de dépassement SLA', 'Breached / subject to SLA × 100', 'Dépassés / soumis au SLA × 100', 'breached / sla_subject × 100', '%', 10, 20],
            ['escalation_rate', 'Escalation rate', 'Taux d\'escalade', 'Escalated / eligible × 100', 'Escaladés / éligibles × 100', 'escalated / eligible × 100', '%', 15, 25],
            ['backlog', 'Backlog', 'Backlog', 'Unresolved cases at cut-off', 'Cas non résolus à la date de référence', 'COUNT(open)', 'cases', 50, 80],
            ['avg_resolution_time', 'Average resolution time', 'Délai moyen de résolution', 'Mean days from creation to resolution', 'Jours moyens création → résolution', 'AVG(resolution_days)', 'days', 30, 45],
            ['median_resolution_time', 'Median resolution time', 'Délai médian de résolution', 'Median resolution days', 'Jours médians de résolution', 'MEDIAN(resolution_days)', 'days', 25, 40],
            ['avg_validation_time', 'Average validation time', 'Délai moyen de validation', 'Mean days submission to validation', 'Jours moyens soumission → validation', 'AVG(validation_days)', 'days', 3, 5],
            ['avg_assignment_time', 'Average assignment time', 'Délai moyen d\'assignation', 'Mean days validation to assignment', 'Jours moyens validation → assignation', 'AVG(assignment_days)', 'days', 2, 4],
            ['reopening_rate', 'Reopening rate', 'Taux de réouverture', 'Reopened / resolved or closed × 100', 'Réouverts / résolus ou clôturés × 100', 'reopened / resolved × 100', '%', 5, 10],
        ];

        foreach ($kpis as [$code, $titleEn, $titleFr, $descEn, $descFr, $formula, $unit, $target, $warning]) {
            ReportKpiDefinition::query()->updateOrCreate(
                ['code' => $code],
                [
                    'title_en' => $titleEn,
                    'title_fr' => $titleFr,
                    'description_en' => $descEn,
                    'description_fr' => $descFr,
                    'formula' => $formula,
                    'unit' => $unit,
                    'target_value' => $target,
                    'warning_threshold' => $warning,
                    'frequency' => 'monthly',
                    'version' => 1,
                    'is_active' => true,
                ],
            );
        }

        $focal = User::query()->where('email', 'focal@ntb.dj')->first();
        $monthly = ReportDefinition::query()->where('code', 'RPT-06')->first();
        $daily = ReportDefinition::query()->where('code', 'RPT-01')->first();

        if ($focal && $monthly) {
            ReportSchedule::query()->updateOrCreate(
                ['report_definition_id' => $monthly->id, 'owner_id' => $focal->id, 'frequency' => 'monthly'],
                [
                    'parameters' => ['period' => 'last_month'],
                    'recipients' => [['email' => $focal->email, 'name' => $focal->name]],
                    'channels' => ['email', 'portal'],
                    'output_format' => 'pdf',
                    'starts_at' => now()->subMonths(2),
                    'is_active' => true,
                    'is_suspended' => false,
                    'last_run_at' => now()->subMonth(),
                    'next_run_at' => now()->addDays(3),
                ],
            );
        }

        if ($focal && $daily) {
            ReportSchedule::query()->updateOrCreate(
                ['report_definition_id' => $daily->id, 'owner_id' => $focal->id, 'frequency' => 'daily'],
                [
                    'parameters' => ['period' => 'today'],
                    'recipients' => [['email' => $focal->email]],
                    'channels' => ['portal'],
                    'output_format' => 'pdf',
                    'is_active' => true,
                    'next_run_at' => now()->addDay(),
                ],
            );
        }

        if ($focal && $monthly) {
            ReportInstance::query()->updateOrCreate(
                ['instance_code' => 'RPT-06-DEMO-001'],
                [
                    'report_definition_id' => $monthly->id,
                    'status' => 'completed',
                    'parameters' => ['format' => 'pdf'],
                    'filters' => ['period' => 'last_month'],
                    'generated_by' => $focal->id,
                    'generated_at' => now()->subDays(5),
                    'completed_at' => now()->subDays(5),
                    'file_format' => 'pdf',
                    'approval_status' => 'approved',
                    'metadata' => ['title' => 'Monthly Activity Report', 'confidentiality' => 'internal'],
                ],
            );
        }
    }
}
