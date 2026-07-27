<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\SlaConfiguration;
use App\Models\SystemSetting;
use App\Models\WorkflowTransition;
use App\Services\Ntb\ComplaintWorkflowService;
use Illuminate\Database\Seeder;

class NtbWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::setValue('max_attachment_size_kb', 10240, 'integer');
        SystemSetting::setValue('archive_retention_years', 7, 'integer');
        SystemSetting::setValue('duplicate_detection_days', 14, 'integer');
        SystemSetting::setValue('sms_webhook_secret', 'demo-sms-secret', 'string');

        $defaults = [
            ['submitted', 'under_validation', 'Submit for validation', 'Soumettre pour validation', null, false, 1],
            ['under_validation', 'assigned', 'Validate & assign', 'Valider et assigner', 'complaints.validate', false, 1],
            ['under_validation', 'rejected', 'Reject', 'Rejeter', 'complaints.validate', true, 2],
            ['under_validation', 'additional_info_requested', 'Request info', 'Demander des infos', 'complaints.validate', true, 3],
            ['additional_info_requested', 'under_validation', 'Return to validation', 'Retour validation', 'complaints.validate', false, 1],
            ['assigned', 'in_processing', 'Start processing', 'Démarrer le traitement', 'complaints.process', false, 1],
            ['in_processing', 'resolved', 'Resolve', 'Résoudre', 'complaints.process', false, 1],
            ['in_processing', 'escalated', 'Escalate', 'Escalader', 'complaints.escalate', true, 2],
            ['resolved', 'closed', 'Close case', 'Clôturer', 'complaints.close', false, 1],
            ['resolved', 'reopened', 'Reopen', 'Rouvrir', 'complaints.reopen', true, 2],
            ['closed', 'archived', 'Archive', 'Archiver', 'complaints.close', false, 1],
            ['closed', 'reopened', 'Reopen closed case', 'Rouvrir dossier clôturé', 'complaints.reopen', true, 2],
        ];

        foreach ($defaults as [$from, $to, $en, $fr, $perm, $comment, $order]) {
            WorkflowTransition::query()->updateOrCreate(
                ['from_status' => $from, 'to_status' => $to],
                [
                    'label_en' => $en,
                    'label_fr' => $fr,
                    'required_permission' => $perm,
                    'requires_comment' => $comment,
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }

        foreach (['VALIDATION' => 16, 'FIRST_RESPONSE' => 8, 'RESOLUTION' => 32] as $stage => $hours) {
            SlaConfiguration::query()->updateOrCreate(
                ['stage' => $stage, 'ntb_category_id' => null],
                ['target_hours' => $hours, 'alert_threshold_percent' => 80, 'is_active' => true],
            );
        }

        $templates = [
            ['complaint_created', 'New NTB case {case_code}', 'Nouveau dossier OTN {case_code}', 'Your report {case_code} has been received.', 'Votre signalement {case_code} a été reçu.', ['email', 'sms']],
            ['complaint_validated', 'Case {case_code} validated', 'Dossier {case_code} validé', 'Case {case_code} is now under validation.', 'Le dossier {case_code} est en validation.', ['email', 'sms']],
            ['complaint_assigned', 'Case {case_code} assigned', 'Dossier {case_code} assigné', 'Case {case_code} assigned to responsible institution.', 'Dossier {case_code} assigné à l\'institution compétente.', ['email']],
            ['complaint_status_changed', 'Case {case_code} update', 'Mise à jour dossier {case_code}', 'Status changed to {to_status}.', 'Statut changé en {to_status}.', ['email', 'sms']],
            ['complaint_closed', 'Case {case_code} closed', 'Dossier {case_code} clôturé', 'Your case {case_code} has been closed.', 'Votre dossier {case_code} a été clôturé.', ['email', 'sms']],
            ['complaint_reopened', 'Case {case_code} reopened', 'Dossier {case_code} rouvert', 'Case {case_code} has been reopened.', 'Le dossier {case_code} a été rouvert.', ['email']],
            ['auto_escalation', 'Case {case_code} escalated', 'Dossier {case_code} escaladé', 'Case {case_code} was automatically escalated due to SLA overrun.', 'Escalade automatique du dossier {case_code} pour dépassement SLA.', ['email']],
            ['sla_breach', 'SLA alert for {case_code}', 'Alerte SLA pour {case_code}', 'SLA breach detected on case {case_code}.', 'Dépassement SLA détecté pour {case_code}.', ['email']],
        ];

        foreach ($templates as [$event, $subEn, $subFr, $bodyEn, $bodyFr, $channels]) {
            NotificationTemplate::query()->updateOrCreate(
                ['event_type' => $event],
                [
                    'subject_en' => $subEn,
                    'subject_fr' => $subFr,
                    'body_en' => $bodyEn,
                    'body_fr' => $bodyFr,
                    'channels' => $channels,
                    'is_active' => true,
                ],
            );
        }

        app(ComplaintWorkflowService::class)->flushTransitionCache();
    }
}
