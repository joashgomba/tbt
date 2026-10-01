<?php

namespace App\Services\Ntb\Ussd;

use App\Models\Complaint;
use App\Models\NtbCategory;
use App\Models\User;
use App\Models\UssdSession;
use App\Models\UssdSessionEvent;
use App\Services\Ntb\ComplaintSubmissionService;
use App\Services\Ntb\NotificationService;
use Illuminate\Support\Str;

class UssdSessionService
{
    public function __construct(
        private readonly ComplaintSubmissionService $submissions,
        private readonly UssdCatalogService $catalog,
        private readonly UssdComplaintDraftBuilder $draftBuilder,
        private readonly UssdVerificationService $verify,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return array{session_active: bool, message: string, message_type: string, plain_message: string, complaint_code: ?string, state: string, session_id: string}
     */
    public function handle(string $sessionId, string $msisdn, string $text, ?string $serviceCode = null): array
    {
        $parts = array_values(array_filter(explode('*', $text), fn ($p) => $p !== ''));
        $latestInput = $parts === [] ? '' : (string) end($parts);

        $session = UssdSession::query()->where('session_id', $sessionId)->first();
        if ($session && $session->isExpired()) {
            $session->delete();
            $session = null;
        }

        if (! $session) {
            $locale = config('ussd.default_locale', 'fr');
            $session = UssdSession::query()->create([
                'session_id' => $sessionId,
                'msisdn' => $this->verify->normalizeMsisdn($msisdn) ?: $msisdn,
                'state' => 'MAIN_MENU',
                'payload' => ['locale' => $locale, 'menu_offset' => 0, 'draft' => [], 'failures' => 0],
                'expires_at' => now()->addMinutes((int) config('ntb.ussd_session_ttl_minutes', 5)),
            ]);

            return $this->recordAndReturn($session, '', $this->mainMenu($locale), true, 'MAIN_MENU');
        }

        $payload = $session->payload ?? ['locale' => 'fr', 'menu_offset' => 0, 'draft' => [], 'failures' => 0];
        $locale = $payload['locale'] ?? config('ussd.default_locale', 'fr');
        $state = $session->state;

        return match ($state) {
            'MAIN_MENU' => $this->onMainMenu($session, $payload, $locale, $latestInput),
            'SUBMIT_CATEGORY' => $this->onSubmitCategory($session, $payload, $locale, $latestInput),
            'SUBMIT_LOCATION' => $this->onSubmitLocation($session, $payload, $locale, $latestInput),
            'SUBMIT_LOCATION_PICK' => $this->onSubmitLocationPick($session, $payload, $locale, $latestInput),
            'SUBMIT_ISSUE' => $this->onSubmitIssue($session, $payload, $locale, $latestInput),
            'SUBMIT_DESCRIPTION' => $this->onSubmitDescription($session, $payload, $locale, $latestInput),
            'SUBMIT_TRADER' => $this->onSubmitTraderType($session, $payload, $locale, $latestInput),
            'SUBMIT_TRADER_VALUE' => $this->onSubmitTraderValue($session, $payload, $locale, $latestInput),
            'SUBMIT_CONFIRM' => $this->onSubmitConfirm($session, $payload, $locale, $latestInput),
            'TRACK_REFERENCE' => $this->onTrackReference($session, $payload, $locale, $latestInput),
            'TRACK_PIN' => $this->onTrackPin($session, $payload, $locale, $latestInput),
            'TRACK_OTP' => $this->onTrackOtp($session, $payload, $locale, $latestInput),
            'INFO_MENU' => $this->onInfoMenu($session, $payload, $locale, $latestInput),
            'INFO_VERIFY_PIN' => $this->onInfoVerifyPin($session, $payload, $locale, $latestInput),
            'INFO_MY_REFS' => $this->goMainMenu($session, $payload, $locale, $latestInput),
            default => $this->recordAndReturn($session, $latestInput, $this->end($locale, 'Session expired. Dial again.'), false, $state),
        };
    }

    private function onMainMenu(UssdSession $session, array $payload, string $locale, string $input): array
    {
        return match ($input) {
            '0' => $this->recordAndReturn($session, $input, $this->end($locale, $locale === 'fr' ? 'Au revoir.' : 'Goodbye.'), false, 'MAIN_MENU'),
            '1' => $this->startSubmit($session, $payload, $locale, $input),
            '2' => $this->startTrack($session, $payload, $locale, $input),
            '3' => $this->startInfo($session, $payload, $locale, $input),
            default => $this->recordAndReturn($session, $input, $this->con($locale, ($locale === 'fr' ? 'Option invalide.' : 'Invalid option.')."\n".$this->mainMenuText($locale)), true, 'MAIN_MENU'),
        };
    }

    private function startSubmit(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $payload['draft'] = [];
        $payload['menu_offset'] = 0;
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_CATEGORY']);

        return $this->recordAndReturn($session, $input, $this->categoryMenu($locale, 0), true, 'SUBMIT_CATEGORY');
    }

    private function onSubmitCategory(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if ($input === '0') {
            return $this->goMainMenu($session, $payload, $locale, $input);
        }

        $offset = (int) ($payload['menu_offset'] ?? 0);
        if ($input === '9' && $this->catalog->rootCategoriesHasMore($offset)) {
            $offset += 5;
            $payload['menu_offset'] = $offset;

            return $this->recordAndReturn($session, $input, $this->categoryMenu($locale, $offset), true, 'SUBMIT_CATEGORY', $offset);
        }

        $categories = $this->catalog->rootCategories($offset);
        $index = (int) $input;
        if ($index < 1 || $index > $categories->count()) {
            return $this->recordAndReturn($session, $input, $this->con($locale, ($locale === 'fr' ? 'Choix invalide.' : 'Invalid choice.')."\n".$this->categoryMenuText($locale, $offset)), true, 'SUBMIT_CATEGORY', $offset);
        }

        $payload['draft']['ntb_category_id'] = $categories[$index - 1]->id;
        $payload['draft']['category_code'] = $categories[$index - 1]->code;
        $payload['menu_offset'] = 0;
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_LOCATION']);

        return $this->recordAndReturn($session, $input, $this->locationTypeMenu($locale), true, 'SUBMIT_LOCATION');
    }

    private function onSubmitLocation(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if ($input === '0') {
            $session->update(['state' => 'SUBMIT_CATEGORY']);

            return $this->recordAndReturn($session, $input, $this->categoryMenu($locale, (int) ($payload['menu_offset'] ?? 0)), true, 'SUBMIT_CATEGORY');
        }

        $options = $this->catalog->locationTypeOptions($locale);
        $index = (int) $input;
        if ($index < 1 || $index > count($options)) {
            return $this->recordAndReturn($session, $input, $this->con($locale, ($locale === 'fr' ? 'Choix invalide.' : 'Invalid choice.')."\n".$this->locationTypeMenuText($locale)), true, 'SUBMIT_LOCATION');
        }

        $payload['draft']['location_type_key'] = $options[$index - 1]['key'];
        $payload['menu_offset'] = 0;

        $locations = $this->catalog->locationsForTypeKey($payload['draft']['location_type_key'], 0);
        if ($locations->count() > 1 || ($locations->count() === 1 && $this->catalog->locationsHasMore($payload['draft']['location_type_key'], 0))) {
            $session->update(['payload' => $payload, 'state' => 'SUBMIT_LOCATION_PICK']);

            return $this->recordAndReturn($session, $input, $this->locationPickMenu($locale, $payload['draft']['location_type_key'], 0), true, 'SUBMIT_LOCATION_PICK');
        }

        if ($locations->count() === 1) {
            $payload['draft']['location_id'] = $locations->first()->id;
            $payload['draft']['corridor_id'] = $locations->first()->corridor_id;
        }

        $session->update(['payload' => $payload, 'state' => 'SUBMIT_ISSUE']);

        return $this->recordAndReturn($session, $input, $this->issueMenu($session, $payload, $locale), true, 'SUBMIT_ISSUE');
    }

    private function onSubmitLocationPick(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if ($input === '0') {
            $session->update(['state' => 'SUBMIT_LOCATION']);

            return $this->recordAndReturn($session, $input, $this->locationTypeMenu($locale), true, 'SUBMIT_LOCATION');
        }

        $typeKey = $payload['draft']['location_type_key'] ?? 'other';
        $offset = (int) ($payload['menu_offset'] ?? 0);

        if ($input === '9' && $this->catalog->locationsHasMore($typeKey, $offset)) {
            $offset += 5;
            $payload['menu_offset'] = $offset;

            return $this->recordAndReturn($session, $input, $this->locationPickMenu($locale, $typeKey, $offset), true, 'SUBMIT_LOCATION_PICK', $offset);
        }

        $locations = $this->catalog->locationsForTypeKey($typeKey, $offset);
        $index = (int) $input;
        if ($index < 1 || $index > $locations->count()) {
            return $this->recordAndReturn($session, $input, $this->con($locale, ($locale === 'fr' ? 'Choix invalide.' : 'Invalid choice.')."\n".$this->locationPickMenuText($locale, $typeKey, $offset)), true, 'SUBMIT_LOCATION_PICK', $offset);
        }

        $loc = $locations[$index - 1];
        $payload['draft']['location_id'] = $loc->id;
        $payload['draft']['corridor_id'] = $loc->corridor_id;
        $payload['menu_offset'] = 0;
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_ISSUE']);

        return $this->recordAndReturn($session, $input, $this->issueMenu($session, $payload, $locale), true, 'SUBMIT_ISSUE');
    }

    private function onSubmitIssue(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if ($input === '0') {
            $session->update(['state' => 'SUBMIT_LOCATION']);

            return $this->recordAndReturn($session, $input, $this->locationTypeMenu($locale), true, 'SUBMIT_LOCATION');
        }

        $payload = $this->ensureIssueOptions($session, $payload, $locale);
        $issues = $payload['issue_options'] ?? [];
        $index = (int) $input;
        if ($index < 1 || $index > count($issues)) {
            return $this->recordAndReturn($session, $input, $this->con($locale, ($locale === 'fr' ? 'Choix invalide.' : 'Invalid choice.')."\n".$this->issueMenuText($session, $payload, $locale)), true, 'SUBMIT_ISSUE');
        }

        $label = $issues[$index - 1];
        $payload['draft']['issue_label'] = $label;
        $payload['draft']['issue_is_other'] = str_contains(strtolower($label), 'other') || str_contains(strtolower($label), 'autre');
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_DESCRIPTION']);

        $prompt = $payload['draft']['issue_is_other']
            ? ($locale === 'fr' ? 'Decrivez brievement le probleme:' : 'Briefly describe the issue:')
            : ($locale === 'fr' ? 'Description courte (ou 0 pour passer):' : 'Short description (or 0 to skip):');

        return $this->recordAndReturn($session, $input, $this->con($locale, $prompt), true, 'SUBMIT_DESCRIPTION');
    }

    private function onSubmitDescription(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if ($input === '0' && ($payload['draft']['issue_is_other'] ?? false)) {
            return $this->recordAndReturn($session, $input, $this->con($locale, $locale === 'fr' ? 'Description requise:' : 'Description required:'), true, 'SUBMIT_DESCRIPTION');
        }

        $draft = $payload['draft'] ?? [];
        if ($draft['issue_is_other'] ?? false) {
            if (trim($input) === '') {
                return $this->recordAndReturn($session, $input, $this->con($locale, $locale === 'fr' ? 'Description requise:' : 'Description required:'), true, 'SUBMIT_DESCRIPTION');
            }
            $draft['short_description'] = Str::limit(trim($input), (int) config('ussd.max_input_chars', 160), '');
        } elseif ($input !== '0') {
            $draft['short_description'] = Str::limit(trim($input), (int) config('ussd.max_input_chars', 160), '');
        }

        $payload['draft'] = $draft;
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_TRADER']);

        return $this->recordAndReturn($session, $input, $this->traderMenu($locale, $session->msisdn), true, 'SUBMIT_TRADER');
    }

    private function onSubmitTraderType(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if ($input === '0') {
            $session->update(['state' => 'SUBMIT_ISSUE']);

            return $this->recordAndReturn($session, $input, $this->issueMenu($session, $payload, $locale), true, 'SUBMIT_ISSUE');
        }

        return match ($input) {
            '1' => $this->traderValuePrompt($session, $payload, $locale, $input, 'licence', $locale === 'fr' ? 'Numero de patente/licence:' : 'Enter licence/patent number:'),
            '2' => $this->traderValuePrompt($session, $payload, $locale, $input, 'trader_id', $locale === 'fr' ? 'Identifiant commercant:' : 'Enter trader identifier:'),
            '3' => $this->traderMobileConfirm($session, $payload, $locale, $input),
            '4' => $this->traderAnonymous($session, $payload, $locale, $input),
            default => $this->recordAndReturn($session, $input, $this->con($locale, ($locale === 'fr' ? 'Choix invalide.' : 'Invalid choice.')."\n".$this->traderMenuText($locale, $session->msisdn)), true, 'SUBMIT_TRADER'),
        };
    }

    private function traderValuePrompt(UssdSession $session, array $payload, string $locale, string $input, string $type, string $prompt): array
    {
        $payload['draft']['trader_id_type'] = $type;
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_TRADER_VALUE']);

        return $this->recordAndReturn($session, $input, $this->con($locale, $prompt), true, 'SUBMIT_TRADER_VALUE');
    }

    private function traderMobileConfirm(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $payload['draft']['trader_id_type'] = 'mobile';
        $payload['draft']['trader_id_value'] = $session->msisdn;
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_CONFIRM']);

        return $this->recordAndReturn($session, $input, $this->confirmMenu($session, $payload, $locale), true, 'SUBMIT_CONFIRM');
    }

    private function traderAnonymous(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if (! config('ussd.anonymous_submission_enabled', false)) {
            return $this->recordAndReturn($session, $input, $this->con($locale, config('ussd.messages.anonymous_disabled.'.$locale)), true, 'SUBMIT_TRADER');
        }

        $payload['draft']['trader_id_type'] = 'anonymous';
        $payload['draft']['anonymous'] = true;
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_CONFIRM']);

        return $this->recordAndReturn($session, $input, $this->confirmMenu($session, $payload, $locale), true, 'SUBMIT_CONFIRM');
    }

    private function onSubmitTraderValue(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if (trim($input) === '') {
            return $this->recordAndReturn($session, $input, $this->con($locale, $locale === 'fr' ? 'Saisie requise:' : 'Input required:'), true, 'SUBMIT_TRADER_VALUE');
        }

        $payload['draft']['trader_id_value'] = Str::limit(trim($input), 80, '');
        $session->update(['payload' => $payload, 'state' => 'SUBMIT_CONFIRM']);

        return $this->recordAndReturn($session, $input, $this->confirmMenu($session, $payload, $locale), true, 'SUBMIT_CONFIRM');
    }

    private function onSubmitConfirm(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if ($input === '0') {
            return $this->recordAndReturn($session, $input, $this->end($locale, $locale === 'fr' ? 'Signalement annule.' : 'Report cancelled.'), false, 'SUBMIT_CONFIRM');
        }

        if ($input === '2') {
            $payload['menu_offset'] = 0;
            $session->update(['payload' => $payload, 'state' => 'SUBMIT_CATEGORY']);

            return $this->recordAndReturn($session, $input, $this->categoryMenu($locale, 0), true, 'SUBMIT_CATEGORY');
        }

        if ($input !== '1') {
            return $this->recordAndReturn($session, $input, $this->confirmMenu($session, $payload, $locale), true, 'SUBMIT_CONFIRM');
        }

        return $this->finalizeSubmit($session, $payload, $locale, $input);
    }

    private function finalizeSubmit(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $user = $this->resolveSubmitUser($session, $payload);
        if (! $user) {
            return $this->recordAndReturn($session, $input, $this->end($locale, config('ussd.messages.registration_required.'.$locale)), false, 'SUBMIT_CONFIRM');
        }

        $session->update(['user_id' => $user->id]);

        try {
            $data = $this->draftBuilder->toSubmissionPayload($payload['draft'] ?? [], $session->msisdn);
            $validated = $this->submissions->validatePayload($data);
            $complaint = $this->submissions->create($user, $validated, 'USSD', true);
            $complaint->update([
                'reporter_phone' => $session->msisdn,
                'reporter_name' => $user->displayName(),
            ]);

            $session->update(['complaint_id' => $complaint->id, 'state' => 'COMPLETE']);
            $this->notifications->dispatch($complaint->fresh(['status']), 'complaint_created', [
                'locale' => $locale,
                'phone' => $session->msisdn,
            ]);

            $msg = str_replace('{code}', $complaint->complaint_code, config('ussd.messages.submission_success.'.$locale));

            return $this->recordAndReturn($session, $input, $this->end($locale, $msg), false, 'COMPLETE', 0, $complaint->complaint_code);
        } catch (\Throwable) {
            return $this->recordAndReturn($session, $input, $this->end($locale, $locale === 'fr' ? 'Echec envoi. Reessayez ou utilisez le portail.' : 'Submit failed. Retry or use the portal.'), false, 'SUBMIT_CONFIRM');
        }
    }

    private function startTrack(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $payload['track'] = [];
        $session->update(['payload' => $payload, 'state' => 'TRACK_REFERENCE']);

        return $this->recordAndReturn($session, $input, $this->con($locale, $locale === 'fr' ? 'Reference de plainte:' : 'Enter complaint reference:'), true, 'TRACK_REFERENCE');
    }

    private function onTrackReference(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $code = strtoupper(trim($input));
        $complaint = Complaint::query()->with(['status', 'creator'])->where('complaint_code', $code)->first();

        if (! $complaint) {
            return $this->trackFailure($session, $payload, $locale, $input);
        }

        $payload['track']['complaint_id'] = $complaint->id;
        $payload['track']['code'] = $code;

        if ($this->verify->msisdnMatchesComplaint($session->msisdn, $complaint)) {
            return $this->trackSuccess($session, $payload, $locale, $input, $complaint);
        }

        $user = $this->verify->resolveUserByPhone($session->msisdn);
        if ($user && $user->ussd_pin_hash) {
            $session->update(['payload' => $payload, 'state' => 'TRACK_PIN', 'user_id' => $user->id]);

            return $this->recordAndReturn($session, $input, $this->con($locale, $locale === 'fr' ? 'Entrez votre PIN USSD (4 chiffres):' : 'Enter your 4-digit USSD PIN:'), true, 'TRACK_PIN');
        }

        $this->verify->issueTrackOtp($payload);
        $session->update(['payload' => $payload, 'state' => 'TRACK_OTP']);

        return $this->recordAndReturn($session, $input, $this->con($locale, $locale === 'fr' ? 'Entrez le code recu par SMS:' : 'Enter the code sent by SMS:'), true, 'TRACK_OTP');
    }

    private function onTrackPin(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $user = $session->user;
        if (! $this->verify->verifyUserPin($user, $input)) {
            return $this->trackFailure($session, $payload, $locale, $input);
        }

        $complaint = Complaint::query()->with('status')->find($payload['track']['complaint_id'] ?? 0);
        if (! $complaint) {
            return $this->trackFailure($session, $payload, $locale, $input);
        }

        return $this->trackSuccess($session, $payload, $locale, $input, $complaint);
    }

    private function onTrackOtp(UssdSession $session, array $payload, string $locale, string $input): array
    {
        if (! $this->verify->verifyOtp($payload, trim($input))) {
            return $this->trackFailure($session, $payload, $locale, $input);
        }

        $session->update(['payload' => $payload]);
        $complaint = Complaint::query()->with('status')->find($payload['track']['complaint_id'] ?? 0);
        if (! $complaint) {
            return $this->trackFailure($session, $payload, $locale, $input);
        }

        return $this->trackSuccess($session, $payload, $locale, $input, $complaint);
    }

    private function trackSuccess(UssdSession $session, array $payload, string $locale, string $input, Complaint $complaint): array
    {
        $statusCode = $complaint->status?->code ?? 'unknown';
        $statusText = config('ussd.status_messages.'.$statusCode.'.'.$locale)
            ?? config('ussd.status_messages.submitted.'.$locale)
            ?? $statusCode;

        $msg = ($locale === 'fr' ? 'Reference ' : 'Ref ').$complaint->complaint_code."\n".$statusText;

        return $this->recordAndReturn($session, $input, $this->end($locale, $msg), false, 'TRACK_REFERENCE', 0, $complaint->complaint_code);
    }

    private function trackFailure(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $payload['failures'] = (int) ($payload['failures'] ?? 0) + 1;
        $max = (int) config('ussd.track_max_failures', 3);

        if ($payload['failures'] >= $max) {
            return $this->recordAndReturn($session, $input, $this->end($locale, config('ussd.messages.track_locked.'.$locale)), false, 'TRACK_REFERENCE');
        }

        $session->update(['payload' => $payload, 'state' => 'TRACK_REFERENCE']);

        return $this->recordAndReturn($session, $input, $this->con($locale, config('ussd.messages.track_verify_fail.'.$locale)), true, 'TRACK_REFERENCE');
    }

    private function startInfo(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $session->update(['state' => 'INFO_MENU']);

        return $this->recordAndReturn($session, $input, $this->infoMenu($locale), true, 'INFO_MENU');
    }

    private function onInfoMenu(UssdSession $session, array $payload, string $locale, string $input): array
    {
        return match ($input) {
            '0' => $this->goMainMenu($session, $payload, $locale, $input),
            '1' => $this->infoMyReferences($session, $payload, $locale, $input),
            '2' => $this->infoSupport($session, $payload, $locale, $input),
            '3' => $this->infoHowTo($session, $payload, $locale, $input),
            default => $this->recordAndReturn($session, $input, $this->con($locale, ($locale === 'fr' ? 'Option invalide.' : 'Invalid option.')."\n".$this->infoMenuText($locale)), true, 'INFO_MENU'),
        };
    }

    private function infoMyReferences(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $user = $this->verify->resolveUserByPhone($session->msisdn) ?? $session->user;
        if (! $user) {
            return $this->recordAndReturn($session, $input, $this->end($locale, config('ussd.messages.registration_required.'.$locale)), false, 'INFO_MENU');
        }

        if ($user->ussd_pin_hash) {
            $session->update(['user_id' => $user->id, 'state' => 'INFO_VERIFY_PIN']);

            return $this->recordAndReturn($session, $input, $this->con($locale, $locale === 'fr' ? 'PIN USSD:' : 'USSD PIN:'), true, 'INFO_VERIFY_PIN');
        }

        return $this->renderMyReferences($session, $payload, $locale, $input, $user);
    }

    private function onInfoVerifyPin(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $user = $session->user ?? $this->verify->resolveUserByPhone($session->msisdn);
        if (! $this->verify->verifyUserPin($user, $input)) {
            return $this->recordAndReturn($session, $input, $this->end($locale, config('ussd.messages.track_verify_fail.'.$locale)), false, 'INFO_VERIFY_PIN');
        }

        return $this->renderMyReferences($session, $payload, $locale, $input, $user);
    }

    private function renderMyReferences(UssdSession $session, array $payload, string $locale, string $input, ?User $user): array
    {
        if (! $user) {
            return $this->recordAndReturn($session, $input, $this->end($locale, config('ussd.messages.registration_required.'.$locale)), false, 'INFO_MENU');
        }

        $limit = (int) config('ussd.my_references_limit', 5);
        $refs = Complaint::query()
            ->where('created_by', $user->id)
            ->latest()
            ->limit($limit)
            ->pluck('complaint_code')
            ->all();

        if ($refs === []) {
            $msg = $locale === 'fr' ? 'Aucune plainte trouvee.' : 'No complaints found.';
        } else {
            $msg = ($locale === 'fr' ? 'Vos references:' : 'Your references:')."\n".implode("\n", $refs);
        }

        return $this->recordAndReturn($session, $input, $this->end($locale, $msg), false, 'INFO_MY_REFS');
    }

    private function infoSupport(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $support = config('ussd.support.'.$locale, config('ussd.support.en'));
        $msg = ($support['contact'] ?? '')."\n".($support['phone'] ?? '')."\n".($support['hours'] ?? '');

        return $this->recordAndReturn($session, $input, $this->end($locale, $msg), false, 'INFO_MENU');
    }

    private function infoHowTo(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $msg = config('ussd.support.'.$locale.'.how_to_submit') ?? config('ussd.support.en.how_to_submit');

        return $this->recordAndReturn($session, $input, $this->end($locale, $msg), false, 'INFO_MENU');
    }

    private function goMainMenu(UssdSession $session, array $payload, string $locale, string $input): array
    {
        $payload['menu_offset'] = 0;
        $session->update(['payload' => $payload, 'state' => 'MAIN_MENU']);

        return $this->recordAndReturn($session, $input, $this->mainMenu($locale), true, 'MAIN_MENU');
    }

    private function resolveSubmitUser(UssdSession $session, array $payload): ?User
    {
        $draft = $payload['draft'] ?? [];

        if (! empty($draft['anonymous']) && config('ussd.anonymous_submission_enabled', false)) {
            return User::query()->where('username', 'ussd.anonymous')->where('status', 'active')->first()
                ?? $this->verify->resolveUserByPhone($session->msisdn);
        }

        return $this->verify->resolveUserByPhone($session->msisdn)
            ?? User::query()->where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'economic_operator'))->where('phone_number', 'like', '%'.substr($session->msisdn, -8))->first();
    }

    private function mainMenu(string $locale): string
    {
        return $this->con($locale, $this->mainMenuText($locale));
    }

    private function mainMenuText(string $locale): string
    {
        return config('ussd.messages.welcome.'.$locale) ?? config('ussd.messages.welcome.en');
    }

    private function categoryMenu(string $locale, int $offset): string
    {
        return $this->con($locale, $this->categoryMenuText($locale, $offset));
    }

    private function categoryMenuText(string $locale, int $offset): string
    {
        $lines = [$locale === 'fr' ? 'Categorie BNT:' : 'NTB category:', '0. '.($locale === 'fr' ? 'Retour' : 'Back')];
        foreach ($this->catalog->rootCategories($offset) as $i => $cat) {
            $lines[] = ($i + 1).'. '.$this->catalog->trimForUssd($cat->displayName($locale), 28);
        }
        if ($this->catalog->rootCategoriesHasMore($offset)) {
            $lines[] = '9. '.($locale === 'fr' ? 'Suite' : 'More');
        }

        return implode("\n", $lines);
    }

    private function locationTypeMenu(string $locale): string
    {
        return $this->con($locale, $this->locationTypeMenuText($locale));
    }

    private function locationTypeMenuText(string $locale): string
    {
        $lines = [$locale === 'fr' ? 'Lieu de la barriere:' : 'Where did it occur?', '0. '.($locale === 'fr' ? 'Retour' : 'Back')];
        foreach ($this->catalog->locationTypeOptions($locale) as $i => $opt) {
            $lines[] = ($i + 1).'. '.$this->catalog->trimForUssd($opt['label'], 26);
        }

        return implode("\n", $lines);
    }

    private function locationPickMenu(string $locale, string $typeKey, int $offset): string
    {
        return $this->con($locale, $this->locationPickMenuText($locale, $typeKey, $offset));
    }

    private function locationPickMenuText(string $locale, string $typeKey, int $offset): string
    {
        $lines = [$locale === 'fr' ? 'Precisez le lieu:' : 'Select location:', '0. '.($locale === 'fr' ? 'Retour' : 'Back')];
        foreach ($this->catalog->locationsForTypeKey($typeKey, $offset) as $i => $loc) {
            $name = $locale === 'fr' ? ($loc->name_fr ?: $loc->location_name) : ($loc->name_en ?: $loc->location_name);
            $lines[] = ($i + 1).'. '.$this->catalog->trimForUssd($name, 26);
        }
        if ($this->catalog->locationsHasMore($typeKey, $offset)) {
            $lines[] = '9. '.($locale === 'fr' ? 'Suite' : 'More');
        }

        return implode("\n", $lines);
    }

    private function issueMenu(UssdSession $session, array $payload, string $locale): string
    {
        $payload = $this->ensureIssueOptions($session, $payload, $locale);

        return $this->con($locale, $this->issueMenuText($session, $payload, $locale));
    }

    private function ensureIssueOptions(UssdSession $session, array $payload, string $locale): array
    {
        if (! empty($payload['issue_options'])) {
            return $payload;
        }

        $category = NtbCategory::query()->find($payload['draft']['ntb_category_id'] ?? 0);
        if ($category) {
            $payload['issue_options'] = $this->catalog->issuesForCategory($category, $locale);
            $session->update(['payload' => $payload]);
        }

        return $payload;
    }

    private function issueMenuText(UssdSession $session, array $payload, string $locale): string
    {
        $issues = $payload['issue_options'] ?? [];
        $lines = [$locale === 'fr' ? 'Probleme / sous-categorie:' : 'Issue / subcategory:', '0. '.($locale === 'fr' ? 'Retour' : 'Back')];
        foreach ($issues as $i => $issue) {
            $lines[] = ($i + 1).'. '.$this->catalog->trimForUssd($issue, 26);
        }

        return implode("\n", $lines);
    }

    private function traderMenu(string $locale, string $msisdn): string
    {
        return $this->con($locale, $this->traderMenuText($locale, $msisdn));
    }

    private function traderMenuText(string $locale, string $msisdn): string
    {
        $lines = [
            $locale === 'fr' ? 'Identification:' : 'Identify yourself:',
            '1. '.($locale === 'fr' ? 'Patente / licence' : 'Business licence'),
            '2. '.($locale === 'fr' ? 'ID commercant' : 'Trader ID'),
            '3. '.($locale === 'fr' ? 'Mobile ('.$msisdn.')' : 'Mobile ('.$msisdn.')'),
            '4. '.($locale === 'fr' ? 'Anonyme' : 'Anonymous'),
            '0. '.($locale === 'fr' ? 'Retour' : 'Back'),
        ];

        return implode("\n", $lines);
    }

    private function confirmMenu(UssdSession $session, array $payload, string $locale): string
    {
        return $this->con($locale, $this->confirmMenuText($payload, $locale));
    }

    /** @param  array<string, mixed>  $payload */
    private function confirmMenuText(array $payload, string $locale): string
    {
        $draft = $payload['draft'] ?? [];
        $cat = NtbCategory::query()->find($draft['ntb_category_id'] ?? 0);
        $catLabel = $cat ? $cat->displayName($locale) : '?';
        $issue = $draft['issue_label'] ?? '?';

        return implode("\n", [
            $locale === 'fr' ? 'Resume:' : 'Summary:',
            $this->catalog->trimForUssd($catLabel, 30),
            $this->catalog->trimForUssd((string) $issue, 30),
            '1. '.($locale === 'fr' ? 'Confirmer' : 'Confirm'),
            '2. '.($locale === 'fr' ? 'Modifier' : 'Modify'),
            '0. '.($locale === 'fr' ? 'Annuler' : 'Cancel'),
        ]);
    }

    private function infoMenu(string $locale): string
    {
        return $this->con($locale, $this->infoMenuText($locale));
    }

    private function infoMenuText(string $locale): string
    {
        return implode("\n", [
            $locale === 'fr' ? 'Infos pratiques:' : 'Practical information:',
            '1. '.($locale === 'fr' ? 'Mes references' : 'My references'),
            '2. '.($locale === 'fr' ? 'Assistance' : 'Support'),
            '3. '.($locale === 'fr' ? 'Comment deposer' : 'How to submit'),
            '0. '.($locale === 'fr' ? 'Menu principal' : 'Main menu'),
        ]);
    }

    private function con(string $locale, string $body): string
    {
        return 'CON '.$this->catalog->trimForUssd($body, (int) config('ussd.max_menu_response_chars', 182));
    }

    private function end(string $locale, string $body): string
    {
        return 'END '.$this->catalog->trimForUssd($body, (int) config('ussd.max_menu_response_chars', 182));
    }

    /** @return array{session_active: bool, message: string, message_type: string, plain_message: string, complaint_code: ?string, state: string, session_id: string} */
    private function recordAndReturn(
        UssdSession $session,
        string $userInput,
        string $message,
        bool $active,
        string $state,
        int $menuOffset = 0,
        ?string $complaintCode = null,
    ): array {
        $payload = $session->payload ?? [];
        $payload['menu_offset'] = $menuOffset;
        $session->update(['payload' => $payload, 'state' => $state]);

        UssdSessionEvent::query()->create([
            'ussd_session_id' => $session->id,
            'state' => $state,
            'user_input' => $userInput,
            'system_message' => $message,
            'session_closed' => ! $active,
        ]);

        $plain = preg_replace('/^(CON|END)\s+/i', '', $message) ?? $message;

        return [
            'session_active' => $active,
            'message' => $message,
            'message_type' => $active ? 'continue' : 'end',
            'plain_message' => $plain,
            'complaint_code' => $complaintCode,
            'state' => $state,
            'session_id' => $session->session_id,
        ];
    }
}
