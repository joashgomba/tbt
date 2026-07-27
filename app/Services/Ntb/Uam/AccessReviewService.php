<?php

namespace App\Services\Ntb\Uam;

use App\Models\AccessReview;
use App\Models\AccessReviewDecision;
use App\Models\User;
use App\Services\Ntb\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessReviewService
{
    public function __construct(
        private readonly UserAccessScopeService $scope,
        private readonly UserLifecycleService $lifecycle,
        private readonly UamNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Request $request, array $data): AccessReview
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $review = AccessReview::query()->create([
            'title' => $data['title'],
            'institution_id' => $data['institution_id'] ?? null,
            'status' => 'in_progress',
            'due_at' => $data['due_at'] ?? now()->addWeeks(2),
            'reviewer_id' => $data['reviewer_id'] ?? $request->user()?->id,
        ]);

        $this->notifications->notifyAdmins('NTF-UAM-018', [
            'review_title' => $review->title,
            'due_at' => $review->due_at?->format('Y-m-d'),
        ]);

        $this->audit->log('uam.access_review.create', $review);

        return $review->load(['institution', 'reviewer']);
    }

    public function show(Request $request, AccessReview $review): array
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $userQuery = User::query()->with(['institution:id,institution_name', 'roles:id,name']);

        if ($review->institution_id) {
            $userQuery->where('institution_id', $review->institution_id);
        } else {
            $userQuery = $this->scope->scopedUsersQuery($request)->with(['institution:id,institution_name', 'roles:id,name']);
        }

        $subjects = $userQuery->where('status', 'active')->orderBy('full_name')->get();
        $decisions = $review->decisions()->with('subjectUser:id,name,full_name')->get()->keyBy('subject_user_id');

        return [
            'review' => $review->load(['institution', 'reviewer']),
            'subjects' => $subjects->map(function (User $user) use ($decisions) {
                $decision = $decisions->get($user->id);

                return [
                    'user' => $user,
                    'decision' => $decision,
                ];
            }),
            'summary' => [
                'total' => $subjects->count(),
                'certified' => $decisions->count(),
                'confirmed' => $decisions->where('decision', 'confirmed')->count(),
                'revoked' => $decisions->where('decision', 'access_revoked')->count(),
            ],
        ];
    }

    public function certify(Request $request, AccessReview $review, array $decisions, bool $complete = false): AccessReview
    {
        abort_unless($this->scope->canManageUsers($request), 403);
        abort_if($review->status === 'completed', 422, 'This access review is already completed.');

        DB::transaction(function () use ($request, $review, $decisions, $complete) {
            foreach ($decisions as $row) {
                $subject = User::query()->findOrFail($row['user_id']);
                abort_unless($this->scope->canManageUser($request, $subject), 403);

                AccessReviewDecision::query()->updateOrCreate(
                    [
                        'access_review_id' => $review->id,
                        'subject_user_id' => $subject->id,
                    ],
                    [
                        'decision' => $row['decision'],
                        'new_role_id' => $row['new_role_id'] ?? null,
                        'notes' => $row['notes'] ?? null,
                        'decided_by' => $request->user()?->id,
                        'decided_at' => now(),
                    ],
                );

                if ($row['decision'] === 'access_revoked') {
                    $this->lifecycle->suspend($request, $subject, 'Access revoked during review: '.($row['notes'] ?? ''));
                    $this->notifications->notifyUser($subject, 'NTF-UAM-017', [
                        'reason' => $row['notes'] ?? '',
                    ]);
                } elseif ($row['decision'] === 'role_changed' && ! empty($row['new_role_name'])) {
                    $this->lifecycle->assignRole(
                        $request,
                        $subject,
                        $row['new_role_name'],
                        'Access review role change: '.($row['notes'] ?? ''),
                    );
                    $this->notifications->notifyUser($subject, 'NTF-UAM-005', [
                        'new_role' => $row['new_role_name'],
                    ]);
                } elseif ($row['decision'] === 'confirmed') {
                    $this->notifications->notifyUser($subject, 'NTF-UAM-016');
                }
            }

            $certified = AccessReviewDecision::query()->where('access_review_id', $review->id)->count();
            $review->update([
                'summary' => [
                    'certified' => $certified,
                    'completed_by' => $request->user()?->id,
                ],
                'status' => $complete ? 'completed' : 'in_progress',
                'completed_at' => $complete ? now() : null,
            ]);
        });

        $this->audit->log('uam.access_review.certify', $review, null, ['decisions' => count($decisions)]);

        return $review->fresh(['institution', 'reviewer', 'decisions']);
    }
}
