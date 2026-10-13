<?php

namespace App\Services\Ntb;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ComplaintSubmissionService
{
    public function __construct(
        private readonly ComplaintWorkflowService $workflow,
        private readonly DuplicateDetectionService $duplicates,
        private readonly SystemSettingsService $settings,
    ) {}

    /** @return list<string> */
    public function allowedCountryNames(): array
    {
        return collect(config('ntb.countries', []))
            ->flatMap(fn (array $country) => array_filter([
                $country['name_en'] ?? null,
                $country['name_fr'] ?? null,
            ]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function validatePayload(array $input, bool $partial = false): array
    {
        $allowedCountries = $this->allowedCountryNames();
        $allowedCurrencies = config('ntb.currencies', []);
        $maxKb = $this->settings->maxUploadKb();

        $rules = [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => [$partial ? 'nullable' : 'required', 'string'],
            'occurrence_date' => [$partial ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'country_affected' => [$partial ? 'nullable' : 'required', 'string', 'max:100', Rule::in($allowedCountries)],
            'ntb_category_id' => [$partial ? 'nullable' : 'required', 'exists:ntb_categories,id'],
            'country_origin' => [$partial ? 'nullable' : 'required', 'string', 'max:100', Rule::in($allowedCountries)],
            'country_destination' => [$partial ? 'nullable' : 'required', 'string', 'max:100', Rule::in($allowedCountries)],
            'corridor_id' => [$partial ? 'nullable' : 'required', 'exists:corridors,id'],
            'location_id' => [$partial ? 'nullable' : 'required', 'exists:locations,id'],
            'product_description' => ['nullable', 'string', 'max:5000'],
            'product_country_origin' => ['nullable', 'string', 'max:100', Rule::in($allowedCountries)],
            'product_value_currency' => ['nullable', 'string', 'max:3', Rule::in($allowedCurrencies)],
            'product_value_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];

        $data = validator($input, $rules)->validate();

        if (! empty($data['product_value_amount']) && empty($data['product_value_currency'])) {
            throw ValidationException::withMessages([
                'product_value_currency' => ['Select a currency when entering a product value.'],
            ]);
        }

        if (! empty($data['product_value_currency']) && empty($data['product_value_amount'])) {
            throw ValidationException::withMessages([
                'product_value_amount' => ['Enter the product value amount.'],
            ]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data, string $channel, bool $confirmDuplicate = false, ?Request $attachmentRequest = null): Complaint
    {
        abort_unless($user->can('complaints.submit'), 403, 'Your account is not permitted to submit complaints.');

        if (! in_array($user->status, ['active', 'password_reset_required'], true)) {
            abort(403, 'Your account is not active.');
        }

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            $title = Str::limit(preg_replace('/\s+/', ' ', strip_tags($data['description'])) ?: 'NTB report', 255, '');
        }

        $duplicateCheck = $this->duplicates->check($data);
        if ($duplicateCheck['is_duplicate'] && ! $confirmDuplicate) {
            throw ValidationException::withMessages([
                'duplicate_warning' => ['A similar complaint was recently submitted.'],
            ]);
        }

        $complaint = $this->workflow->create([
            'title' => $title,
            'description' => $data['description'],
            'occurrence_date' => $data['occurrence_date'],
            'country_affected' => $data['country_affected'],
            'ntb_category_id' => $data['ntb_category_id'],
            'country_origin' => $data['country_origin'],
            'country_destination' => $data['country_destination'],
            'product_description' => $data['product_description'] ?? null,
            'product_country_origin' => $data['product_country_origin'] ?? null,
            'product_value_currency' => $data['product_value_currency'] ?? null,
            'product_value_amount' => $data['product_value_amount'] ?? null,
            'corridor_id' => $data['corridor_id'],
            'location_id' => $data['location_id'],
            'reporter_name' => $user->displayName(),
            'reporter_email' => $user->email,
            'reporter_phone' => $user->phone_number,
            'gender' => $user->gender,
            'notify_reporter' => true,
            'submission_channel' => $channel,
            'priority' => 'MED',
        ], $user->id);

        $files = $this->collectAttachmentFiles($attachmentRequest);
        if ($files !== []) {
            $maxKb = $this->settings->maxUploadKb();
            if (count($files) > 5) {
                throw ValidationException::withMessages([
                    'attachments' => ['You can attach a maximum of 5 files.'],
                ]);
            }
            foreach ($files as $file) {
                if ($file->getSize() > $maxKb * 1024) {
                    continue;
                }
                $path = $file->store("complaints/{$complaint->id}", 'public');
                $complaint->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]);
            }
        }

        return $complaint->fresh(['category', 'status', 'corridor', 'location', 'attachments']);
    }

    public function validateAttachments(Request $request): void
    {
        $maxKb = $this->settings->maxUploadKb();
        $files = $this->collectAttachmentFiles($request);

        if (count($files) > 5) {
            throw ValidationException::withMessages([
                'attachments' => ['You can attach a maximum of 5 files.'],
            ]);
        }

        foreach ($files as $index => $file) {
            validator(
                ['file' => $file],
                [
                    'file' => ['file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:'.$maxKb],
                ],
                [],
                ["file" => "attachments.{$index}"],
            )->validate();
        }
    }

    /**
     * @return list<\Illuminate\Http\UploadedFile>
     */
    private function collectAttachmentFiles(?Request $attachmentRequest): array
    {
        if ($attachmentRequest === null) {
            return [];
        }

        $files = $attachmentRequest->file('attachments');
        if ($files === null) {
            foreach ($attachmentRequest->allFiles() as $key => $value) {
                if (str_starts_with((string) $key, 'attachments')) {
                    $files = $value;
                    break;
                }
            }
        }

        if ($files === null) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }
}
