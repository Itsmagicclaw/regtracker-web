<?php

namespace App\Services;

use App\Models\DetectedChange;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class QAClassifierService
{
    /**
     * Classify a detected change as approved, rejected, or requires_review.
     * Uses heuristic rules and optional AI classification.
     */
    public function classifyChange(DetectedChange $change): void
    {
        try {
            $entry = json_decode($change->raw_data, true);

            // Apply heuristic rules first
            $classification = $this->applyHeuristicRules($change, $entry);

            if ($classification === 'requires_review') {
                // Could call external AI service here if configured
                // For now, keep as requires_review
                $change->update([
                    'qa_status' => 'requires_review',
                    'qa_notes' => 'Flagged for manual review due to ambiguous content',
                ]);
            } else {
                $change->update([
                    'qa_status' => $classification,
                    'qa_notes' => $this->generateQaNotes($change, $entry, $classification),
                ]);
            }

            Log::info('Change classified', [
                'change_id' => $change->id,
                'status' => $classification,
            ]);

        } catch (\Exception $e) {
            Log::error('QA classification error', [
                'change_id' => $change->id,
                'message' => $e->getMessage(),
            ]);

            // Default to requires_review on error
            $change->update(['qa_status' => 'requires_review']);
        }
    }

    /**
     * Apply heuristic rules to classify changes without external AI.
     */
    private function applyHeuristicRules(DetectedChange $change, array $entry): string
    {
        // Critical severity always approved (real sanctions changes)
        if ($change->severity === 'critical') {
            return 'approved';
        }

        // Check for suspicious patterns that should be reviewed
        if ($this->hasSuspiciousPatterns($entry)) {
            return 'requires_review';
        }

        // Check for valid data completeness
        if (!$this->hasValidDataQuality($entry)) {
            return 'rejected';
        }

        // High severity changes are approved (likely real changes)
        if ($change->severity === 'high') {
            return 'approved';
        }

        // Medium severity changes are approved if they have good data quality
        if ($change->severity === 'medium' && $this->hasGoodDataQuality($entry)) {
            return 'approved';
        }

        // Default: requires review
        return 'requires_review';
    }

    /**
     * Check for suspicious patterns that might indicate false positives.
     */
    private function hasSuspiciousPatterns(array $entry): bool
    {
        // Check for test/placeholder names
        $testPatterns = [
            'test',
            'demo',
            'example',
            'sample',
            'temp',
            'xxx',
            '***',
            'n/a',
            'tbd',
        ];

        $entryStr = json_encode($entry);
        foreach ($testPatterns as $pattern) {
            if (stripos($entryStr, $pattern) !== false) {
                return true;
            }
        }

        // Check for unusually short or obviously incomplete names
        $name = $entry['name'] ?? $entry['title'] ?? '';
        if (strlen($name) < 3) {
            return true;
        }

        // Check for entries that are only numbers or special characters
        if (preg_match('/^[0-9\s\-\.\,]+$/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Check if entry has minimum required data fields.
     */
    private function hasValidDataQuality(array $entry): bool
    {
        // Must have at least a name or title
        $hasIdentifier = !empty($entry['name']) || !empty($entry['title']) || !empty($entry['firmName']);

        if (!$hasIdentifier) {
            return false;
        }

        // Should not be too sparse (completely empty except identifier)
        $fieldCount = count(array_filter($entry, function($v) {
            return !empty($v) && !is_array($v);
        }));

        return $fieldCount >= 1; // At least identifier
    }

    /**
     * Check if entry has good data quality (more than minimum).
     */
    private function hasGoodDataQuality(array $entry): bool
    {
        $fieldCount = count(array_filter($entry, function($v) {
            return !empty($v) && !is_array($v);
        }));

        // Good quality if has 2+ substantive fields
        return $fieldCount >= 2;
    }

    /**
     * Generate QA notes describing the classification decision.
     */
    private function generateQaNotes(DetectedChange $change, array $entry, string $classification): string
    {
        $notes = [];

        switch ($classification) {
            case 'approved':
                $notes[] = "Severity: {$change->severity}";
                $notes[] = "Type: {$change->change_type}";
                $notes[] = "Data quality: Valid";
                break;

            case 'rejected':
                $notes[] = "Insufficient data quality";
                $notes[] = "Missing required fields";
                $notes[] = "May be incomplete or test data";
                break;

            case 'requires_review':
                $notes[] = "Ambiguous classification";
                $notes[] = "Recommend manual verification";
                $notes[] = "Severity: {$change->severity}";
                break;
        }

        return implode('. ', $notes) . '.';
    }

    /**
     * Batch classify multiple changes (useful for bulk processing).
     */
    public function classifyChanges(array $changeIds): void
    {
        $changes = DetectedChange::whereIn('id', $changeIds)
            ->where('qa_status', 'pending')
            ->get();

        foreach ($changes as $change) {
            $this->classifyChange($change);
        }

        Log::info('Batch classification completed', [
            'count' => $changes->count(),
        ]);
    }
}
