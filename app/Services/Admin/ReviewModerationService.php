<?php

namespace App\Services\Admin;

use App\Models\CustomerFeedback;
use App\Models\ProductReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewModerationService
{
    /** @param array{moderation_status: string, rejection_reason?: ?string} $data */
    public function decideReview(ProductReview $review, array $data, int $actorId): void
    {
        DB::transaction(function () use ($review, $data, $actorId): void {
            $record = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $before = $this->reviewState($record);
            $approved = $data['moderation_status'] === 'approved';
            $record->forceFill([
                'moderation_status' => $data['moderation_status'],
                'visibility_status' => $approved && $record->moderation_status === 'approved' ? $record->visibility_status : 'hidden',
                'rejection_reason' => $approved ? null : (filled($data['rejection_reason'] ?? null) ? trim((string) $data['rejection_reason']) : null),
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
            ])->save();
            $this->log($record, 'product_review.moderated', $actorId, $before, $this->reviewState($record));
        });
    }

    public function setReviewVisibility(ProductReview $review, string $visibility, int $actorId): void
    {
        DB::transaction(function () use ($review, $visibility, $actorId): void {
            $record = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            if ($visibility === 'visible' && $record->moderation_status !== 'approved') {
                throw ValidationException::withMessages(['visibility_status' => __('admin.reviews.validation.approval_required')]);
            }
            $before = $this->reviewState($record);
            $record->forceFill(['visibility_status' => $visibility])->save();
            $this->log($record, 'product_review.visibility_changed', $actorId, $before, $this->reviewState($record));
        });
    }

    /** @param array{moderation_status: string, staff_note?: ?string} $data */
    public function decideFeedback(CustomerFeedback $feedback, array $data, int $actorId): void
    {
        DB::transaction(function () use ($feedback, $data, $actorId): void {
            $record = CustomerFeedback::query()->whereKey($feedback->id)->lockForUpdate()->firstOrFail();
            $before = $this->feedbackState($record);
            $approved = $data['moderation_status'] === 'approved';
            $attributes = [
                'moderation_status' => $data['moderation_status'],
                'visibility_status' => $approved && $record->moderation_status === 'approved' ? $record->visibility_status : 'hidden',
                'show_on_homepage' => $approved && $record->moderation_status === 'approved' ? $record->show_on_homepage : false,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
            ];
            if (array_key_exists('staff_note', $data)) {
                $attributes['staff_note'] = filled($data['staff_note']) ? trim((string) $data['staff_note']) : null;
            }
            $record->forceFill($attributes)->save();
            $this->log($record, 'customer_feedback.moderated', $actorId, $before, $this->feedbackState($record));
        });
    }

    public function setFeedbackVisibility(CustomerFeedback $feedback, string $visibility, bool $showOnHomepage, int $actorId): void
    {
        DB::transaction(function () use ($feedback, $visibility, $showOnHomepage, $actorId): void {
            $record = CustomerFeedback::query()->whereKey($feedback->id)->lockForUpdate()->firstOrFail();
            if ($visibility === 'visible' && $record->moderation_status !== 'approved') {
                throw ValidationException::withMessages(['visibility_status' => __('admin.feedback.validation.approval_required')]);
            }
            if ($showOnHomepage && ($record->moderation_status !== 'approved' || $visibility !== 'visible')) {
                throw ValidationException::withMessages(['show_on_homepage' => __('admin.feedback.validation.approval_and_visibility_required')]);
            }
            $before = $this->feedbackState($record);
            $record->forceFill(['visibility_status' => $visibility, 'show_on_homepage' => $showOnHomepage])->save();
            $this->log($record, 'customer_feedback.visibility_changed', $actorId, $before, $this->feedbackState($record));
        });
    }

    /** @return array<string, mixed> */
    private function reviewState(ProductReview $review): array
    {
        return [
            'moderation_status' => $review->moderation_status,
            'visibility_status' => $review->visibility_status,
            'reviewed_by' => $review->reviewed_by,
            'reviewed_at' => $review->reviewed_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function feedbackState(CustomerFeedback $feedback): array
    {
        return [
            'moderation_status' => $feedback->moderation_status,
            'visibility_status' => $feedback->visibility_status,
            'show_on_homepage' => $feedback->show_on_homepage,
            'reviewed_by' => $feedback->reviewed_by,
            'reviewed_at' => $feedback->reviewed_at?->toISOString(),
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function log(object $subject, string $event, int $actorId, array $before, array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'before' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
