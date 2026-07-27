<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NtbUamNotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'NTF-UAM-001' => [
                'subject_en' => 'Welcome to the NTB Portal',
                'subject_fr' => 'Bienvenue sur le Portail OTN',
                'body_en' => 'Hello {user_name}, your account ({username}) has been created. Status: {status}.',
                'body_fr' => 'Bonjour {user_name}, votre compte ({username}) a été créé. Statut : {status}.',
            ],
            'NTF-UAM-002' => [
                'subject_en' => 'Verification required',
                'subject_fr' => 'Vérification requise',
                'body_en' => 'Your verification code is {otp_code}. It expires shortly. Method: {method}.',
                'body_fr' => 'Votre code de vérification est {otp_code}. Il expire sous peu. Méthode : {method}.',
            ],
            'NTF-UAM-003' => [
                'subject_en' => 'Account approved',
                'subject_fr' => 'Compte approuvé',
                'body_en' => 'Hello {user_name}, your NTB Portal account is now active.',
                'body_fr' => 'Bonjour {user_name}, votre compte Portail OTN est maintenant actif.',
            ],
            'NTF-UAM-005' => [
                'subject_en' => 'Role assignment updated',
                'subject_fr' => 'Rôle mis à jour',
                'body_en' => 'Hello {user_name}, your role has been changed to {new_role}.',
                'body_fr' => 'Bonjour {user_name}, votre rôle a été modifié en {new_role}.',
            ],
            'NTF-UAM-007' => [
                'subject_en' => 'Password changed',
                'subject_fr' => 'Mot de passe modifié',
                'body_en' => 'Hello {user_name}, your password was changed by an administrator.',
                'body_fr' => 'Bonjour {user_name}, votre mot de passe a été modifié par un administrateur.',
            ],
            'NTF-UAM-009' => [
                'subject_en' => 'Account locked',
                'subject_fr' => 'Compte verrouillé',
                'body_en' => 'Hello {user_name}, your account was locked after failed sign-in attempts.',
                'body_fr' => 'Bonjour {user_name}, votre compte a été verrouillé après des tentatives de connexion échouées.',
            ],
            'NTF-UAM-010' => [
                'subject_en' => 'Account suspended',
                'subject_fr' => 'Compte suspendu',
                'body_en' => 'Hello {user_name}, your account has been suspended. Reason: {reason}.',
                'body_fr' => 'Bonjour {user_name}, votre compte a été suspendu. Motif : {reason}.',
            ],
            'NTF-UAM-011' => [
                'subject_en' => 'Account reactivated',
                'subject_fr' => 'Compte réactivé',
                'body_en' => 'Hello {user_name}, your account access has been restored.',
                'body_fr' => 'Bonjour {user_name}, l\'accès à votre compte a été rétabli.',
            ],
            'NTF-UAM-012' => [
                'subject_en' => 'MFA enrolled',
                'subject_fr' => 'MFA activée',
                'body_en' => 'Hello {user_name}, multi-factor authentication is now active on your account.',
                'body_fr' => 'Bonjour {user_name}, l\'authentification multifacteur est active sur votre compte.',
            ],
            'NTF-UAM-013' => [
                'subject_en' => 'MFA reset',
                'subject_fr' => 'Réinitialisation MFA',
                'body_en' => 'Hello {user_name}, MFA was reset. Please enrol again before your next sign-in.',
                'body_fr' => 'Bonjour {user_name}, la MFA a été réinitialisée. Veuillez vous réinscrire avant votre prochaine connexion.',
            ],
            'NTF-UAM-014' => [
                'subject_en' => 'Delegation created',
                'subject_fr' => 'Délégation créée',
                'body_en' => 'A delegation was created involving {delegator_name}{delegate_name}.',
                'body_fr' => 'Une délégation impliquant {delegator_name}{delegate_name} a été créée.',
            ],
            'NTF-UAM-016' => [
                'subject_en' => 'Access confirmed',
                'subject_fr' => 'Accès confirmé',
                'body_en' => 'Hello {user_name}, your access was confirmed during a periodic review.',
                'body_fr' => 'Bonjour {user_name}, votre accès a été confirmé lors d\'une revue périodique.',
            ],
            'NTF-UAM-017' => [
                'subject_en' => 'Privileged access revoked',
                'subject_fr' => 'Accès privilégié révoqué',
                'body_en' => 'Hello {user_name}, your access was revoked or suspended. Reason: {reason}.',
                'body_fr' => 'Bonjour {user_name}, votre accès a été révoqué ou suspendu. Motif : {reason}.',
            ],
            'NTF-UAM-018' => [
                'subject_en' => 'Access review due',
                'subject_fr' => 'Revue d\'accès à effectuer',
                'body_en' => 'Access review "{review_title}" is due by {due_at}.',
                'body_fr' => 'La revue d\'accès « {review_title} » doit être effectuée avant le {due_at}.',
            ],
            'NTF-UAM-020' => [
                'subject_en' => 'Institution suspended',
                'subject_fr' => 'Institution suspendue',
                'body_en' => 'Institution {institution_name} has been suspended. Reason: {reason}.',
                'body_fr' => 'L\'institution {institution_name} a été suspendue. Motif : {reason}.',
            ],
            'NTF-UAM-044' => [
                'subject_en' => 'Privileged access removed',
                'subject_fr' => 'Accès privilégié retiré',
                'body_en' => 'Privileged access was removed for {user_name}. Reason: {reason}.',
                'body_fr' => 'L\'accès privilégié a été retiré pour {user_name}. Motif : {reason}.',
            ],
        ];

        foreach ($templates as $eventType => $payload) {
            NotificationTemplate::query()->updateOrCreate(
                ['event_type' => $eventType],
                [
                    ...$payload,
                    'channels' => ['email', 'portal'],
                    'is_active' => true,
                ],
            );
        }
    }
}
