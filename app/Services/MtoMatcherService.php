<?php

namespace App\Services;

use App\Models\DetectedChange;
use App\Models\MtoProfile;
use App\Models\MtoAlert;
use Illuminate\Support\Facades\Log;

class MtoMatcherService
{
    /**
     * Match a detected change to all MTOs who should be alerted.
     * Checks MTO alert preferences against change attributes.
     */
    public function matchChangeToMtos(DetectedChange $change): int
    {
        try {
            $source = $change->regulatorySource;
            $matchCount = 0;

            // Get all active MTOs
            $mtos = MtoProfile::where('is_active', true)->get();

            foreach ($mtos as $mto) {
                // Check if MTO is subscribed to this source
                if (!$this->isSourceEnabledForMto($mto, $source->source_type)) {
                    continue;
                }

                // Check if MTO's alert criteria match this change
                if ($this->matchesAlertCriteria($mto, $change)) {
                    // Create MtoAlert record linking the change to this MTO
                    $alert = MtoAlert::updateOrCreate(
                        [
                            'mto_id' => $mto->id,
                            'detected_change_id' => $change->id,
                        ],
                        [
                            'alert_status' => 'pending',
                            'created_at' => now(),
                        ]
                    );

                    $matchCount++;
                    Log::info('MTO matched to detected change', [
                        'mto_id' => $mto->id,
                        'change_id' => $change->id,
                        'source_type' => $source->source_type,
                    ]);
                }
            }

            return $matchCount;

        } catch (\Exception $e) {
            Log::error('MTO matching error', [
                'change_id' => $change->id,
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Check if an MTO is subscribed to alerts from a given regulatory source.
     */
    private function isSourceEnabledForMto(MtoProfile $mto, string $sourceType): bool
    {
        // Get MTO's enabled sources from alert preferences
        $enabledSources = json_decode($mto->alert_sources ?? '[]', true);

        // If empty array, assume all sources enabled (default behavior)
        if (empty($enabledSources)) {
            return true;
        }

        return in_array($sourceType, $enabledSources);
    }

    /**
     * Check if a detected change matches the MTO's alert criteria.
     * Criteria include: severity level, change types, and source types.
     */
    private function matchesAlertCriteria(MtoProfile $mto, DetectedChange $change): bool
    {
        // Check severity threshold - only alert if change meets minimum severity
        $minimumSeverity = $mto->minimum_alert_severity ?? 'medium';
        if (!$this->meetsSeverityThreshold($change->severity, $minimumSeverity)) {
            return false;
        }

        // Check change type preferences
        $allowedChangeTypes = json_decode($mto->alert_change_types ?? '[]', true);
        if (!empty($allowedChangeTypes) && !in_array($change->change_type, $allowedChangeTypes)) {
            return false;
        }

        // Check source type preferences
        $allowedSources = json_decode($mto->alert_sources ?? '[]', true);
        if (!empty($allowedSources) && !in_array($change->regulatorySource->source_type, $allowedSources)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a severity level meets or exceeds the threshold.
     * Severity hierarchy: critical > high > medium > low
     */
    private function meetsSeverityThreshold(string $changeSeverity, string $minimumSeverity): bool
    {
        $severityLevels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        $changeLevel = $severityLevels[$changeSeverity] ?? 2;
        $minimumLevel = $severityLevels[$minimumSeverity] ?? 2;

        return $changeLevel >= $minimumLevel;
    }

    /**
     * Batch match multiple detected changes to MTOs.
     */
    public function matchChangesToMtos(array $changeIds): int
    {
        $totalMatches = 0;

        $changes = DetectedChange::whereIn('id', $changeIds)
            ->where('qa_status', 'approved')
            ->get();

        foreach ($changes as $change) {
            $totalMatches += $this->matchChangeToMtos($change);
        }

        Log::info('Batch MTO matching completed', [
            'changes_processed' => $changes->count(),
            'total_matches' => $totalMatches,
        ]);

        return $totalMatches;
    }

    /**
     * Get all unmatched approved changes that need MTO matching.
     */
    public function getUnmatchedChanges(): \Illuminate\Database\Eloquent\Collection
    {
        return DetectedChange::where('qa_status', 'approved')
            ->doesntHave('mtoAlerts')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Process all unmatched changes in batch.
     */
    public function processUnmatchedChanges(): int
    {
        $changes = $this->getUnmatchedChanges();
        $changeIds = $changes->pluck('id')->toArray();

        if (empty($changeIds)) {
            return 0;
        }

        return $this->matchChangesToMtos($changeIds);
    }

    /**
     * Get MTOs affected by a specific detected change.
     */
    public function getMtosForChange(DetectedChange $change): \Illuminate\Database\Eloquent\Collection
    {
        return MtoProfile::whereHas('alerts', function ($query) use ($change) {
            $query->where('detected_change_id', $change->id);
        })->get();
    }
}
