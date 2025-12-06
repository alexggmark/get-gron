<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = [
        'url',
        'cms_type',
        'status',
        'failed_step',
        'lighthouse_performance',
        'lighthouse_accessibility',
        'lighthouse_seo',
        'cta_score',
        'cta_details',
        'form_friction_score',
        'form_details',
        'trust_signals',
        'mobile_issues',
        'readability_score',
        'image_issues',
        'schema_detected',
        'screenshot_path',
    ];

    protected function casts(): array
    {
        return [
            'cta_details' => 'array',
            'form_details' => 'array',
            'trust_signals' => 'array',
            'mobile_issues' => 'array',
            'image_issues' => 'array',
            'schema_detected' => 'array',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getOverallScoreAttribute(): ?int
    {
        $scores = array_filter([
            $this->lighthouse_performance,
            $this->lighthouse_accessibility,
            $this->lighthouse_seo,
            $this->cta_score,
            $this->form_friction_score,
            $this->readability_score,
        ], fn ($score) => $score !== null);

        if (empty($scores)) {
            return null;
        }

        return (int) round(array_sum($scores) / count($scores));
    }

    public function getLighthouseAverageAttribute(): ?int
    {
        $scores = array_filter([
            $this->lighthouse_performance,
            $this->lighthouse_accessibility,
            $this->lighthouse_seo,
        ], fn ($score) => $score !== null);

        if (empty($scores)) {
            return null;
        }

        return (int) round(array_sum($scores) / count($scores));
    }

    public function getCtaCountAttribute(): int
    {
        return count($this->cta_details ?? []);
    }

    public function getFormCountAttribute(): int
    {
        return count($this->form_details ?? []);
    }

    public function getTrustSignalCountAttribute(): int
    {
        return count($this->trust_signals ?? []);
    }

    public function getImageIssueCountAttribute(): int
    {
        return count($this->image_issues ?? []);
    }

    public function getMobileIssueCountAttribute(): int
    {
        return count($this->mobile_issues ?? []);
    }

    public function getScreenshotUrlAttribute(): ?string
    {
        if (!$this->screenshot_path) {
            return null;
        }

        return asset('storage/' . $this->screenshot_path);
    }
}
