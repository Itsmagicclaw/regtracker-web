<?php

namespace App\Services;

use App\Models\DetectedChange;
use App\Models\ActionBrief;
use App\Models\MtoProfile;
use Illuminate\Support\Facades\Log;

class ActionBriefService
{
    /**
     * Generate action briefs for detected changes that require MTO action.
     * Briefs are created only for approved changes that match MTO alert criteria.
     */
    public function generateBriefForChange(DetectedChange $change): ?ActionBrief
    {
        try {
            // Only create briefs for approved changes
            if ($change->qa_status !== 'approved') {
                return null;
            }

            $entry = json_decode($change->raw_data, true);
            $source = $change->regulatorySource;

            // Determine action type based on change type and source
            $actionType = $this->determineActionType($change->change_type, $source->source_type);

            // Generate action heading based on severity and source
            $actionHeading = $this->generateActionHeading($change, $entry);

            // Generate detailed action instructions
            $actionDetails = $this->generateActionDetails($change, $entry, $source->source_type);

            // Estimate compliance risk and remediation timeline
            $riskLevel = $this->assessRiskLevel($change);
            $remediationDays = $this->estimateRemediationTimeline($change, $source->source_type);

            // Create the action brief record
            $brief = ActionBrief::create([
                'detected_change_id' => $change->id,
                'action_type' => $actionType,
                'action_heading' => $actionHeading,
                'action_details' => $actionDetails,
                'risk_level' => $riskLevel,
                'remediation_days' => $remediationDays,
                'source_type' => $source->source_type,
                'source_name' => $source->name,
                'entry_identifier' => $change->entry_identifier,
                'summary' => $change->summary,
                'severity' => $change->severity,
                'generated_at' => now(),
            ]);

            Log::info('Action brief generated', [
                'brief_id' => $brief->id,
                'change_id' => $change->id,
                'action_type' => $actionType,
                'risk_level' => $riskLevel,
            ]);

            return $brief;

        } catch (\Exception $e) {
            Log::error('Action brief generation failed', [
                'change_id' => $change->id,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate action briefs in batch for multiple changes.
     */
    public function generateBriefsBatch(array $changeIds): int
    {
        $changes = DetectedChange::whereIn('id', $changeIds)
            ->where('qa_status', 'approved')
            ->where(function ($query) {
                $query->whereDoesntHave('actionBrief');
            })
            ->get();

        $created = 0;
        foreach ($changes as $change) {
            if ($this->generateBriefForChange($change)) {
                $created++;
            }
        }

        Log::info('Batch action brief generation completed', [
            'count' => $created,
        ]);

        return $created;
    }

    /**
     * Determine the action type based on change type and regulatory source.
     */
    private function determineActionType(string $changeType, string $sourceType): string
    {
        // Sanctions list additions require immediate action
        if ($changeType === 'added' && in_array($sourceType, ['ofac', 'uk_sanctions', 'un_sanctions', 'eu_sanctions', 'dfat', 'austrac'])) {
            return 'screening_update_required';
        }

        // Sanctions list removals require compliance log updates
        if ($changeType === 'removed' && in_array($sourceType, ['ofac', 'uk_sanctions', 'un_sanctions', 'eu_sanctions', 'dfat', 'austrac'])) {
            return 'sanctions_delisting';
        }

        // Modifications to sanctions entries require review
        if ($changeType === 'modified' && in_array($sourceType, ['ofac', 'uk_sanctions', 'un_sanctions', 'eu_sanctions', 'dfat', 'austrac'])) {
            return 'sanctions_modification';
        }

        // Regulatory/enforcement guidance changes
        if (in_array($sourceType, ['fca', 'fintrac', 'federal_register'])) {
            if ($changeType === 'added') {
                return 'policy_update';
            }
            if ($changeType === 'modified') {
                return 'guidance_update';
            }
        }

        return 'review_required';
    }

    /**
     * Generate a concise action heading.
     */
    private function generateActionHeading(DetectedChange $change, array $entry): string
    {
        $source = $change->regulatorySource;
        $name = $entry['name'] ?? $entry['title'] ?? $entry['firmName'] ?? 'Unknown Entity';

        switch ($change->change_type) {
            case 'added':
                if (in_array($source->source_type, ['ofac', 'uk_sanctions', 'un_sanctions', 'eu_sanctions', 'dfat', 'austrac'])) {
                    return "New sanctions entry added: {$name}";
                }
                return "New regulation published: {$name}";

            case 'removed':
                return "Sanctions entry removed: {$name}";

            case 'modified':
                return "Sanctions entry updated: {$name}";

            default:
                return "Regulatory change detected: {$name}";
        }
    }

    /**
     * Generate detailed action instructions for the MTO.
     */
    private function generateActionDetails(DetectedChange $change, array $entry, string $sourceType): string
    {
        $details = [];

        switch ($change->change_type) {
            case 'added':
                if (in_array($sourceType, ['ofac', 'uk_sanctions', 'un_sanctions', 'eu_sanctions', 'dfat', 'austrac'])) {
                    $details[] = "1. Update screening database with new sanctions entry immediately";
                    $details[] = "2. Re-screen all existing customers against updated list";
                    $details[] = "3. Block any matching transactions pending compliance review";
                    $details[] = "4. Document screening action in compliance log";
                    $details[] = "5. Notify compliance officer of changes made";
                } else {
                    $details[] = "1. Review new regulatory guidance in detail";
                    $details[] = "2. Assess impact on current operations";
                    $details[] = "3. Update internal policies as needed";
                    $details[] = "4. Brief team on new requirements";
                }
                break;

            case 'removed':
                $details[] = "1. Remove entity from active sanctions watchlist";
                $details[] = "2. Unblock any transactions previously flagged for this entity";
                $details[] = "3. Update compliance log with delisting details";
                $details[] = "4. Retain records for audit trail (minimum 7 years)";
                $details[] = "5. Notify affected customers of resolution";
                break;

            case 'modified':
                $details[] = "1. Review modification details for impact on screening";
                $details[] = "2. Update entity information in database";
                $details[] = "3. Re-assess any active cases or flags";
                $details[] = "4. Document changes in compliance log";
                break;
        }

        return implode("\n", $details);
    }

    /**
     * Assess compliance risk level based on severity and change type.
     */
    private function assessRiskLevel(DetectedChange $change): string
    {
        if ($change->severity === 'critical') {
            return 'critical';
        }

        if ($change->severity === 'high') {
            if ($change->change_type === 'removed') {
                return 'high'; // Delisting carries compliance risk if not handled properly
            }
            return 'high';
        }

        return 'medium';
    }

    /**
     * Estimate remediation timeline in days.
     */
    private function estimateRemediationTimeline(DetectedChange $change, string $sourceType): int
    {
        // Sanctions additions require immediate action (same day)
        if ($change->change_type === 'added' && in_array($sourceType, ['ofac', 'uk_sanctions', 'un_sanctions', 'eu_sanctions', 'dfat', 'austrac'])) {
            return 0; // Immediate
        }

        // Sanctions removals should be processed within 1 business day
        if ($change->change_type === 'removed') {
            return 1;
        }

        // Modifications within 2 business days
        if ($change->change_type === 'modified') {
            return 2;
        }

        // Regulatory guidance changes within 5 business days
        if (in_array($sourceType, ['fca', 'fintrac', 'federal_register'])) {
            return 5;
        }

        return 3; // Default: 3 business days
    }

    /**
     * Get action briefs for a specific MTO based on their alert preferences.
     */
    public function getActionBriefsForMto(MtoProfile $mto): \Illuminate\Database\Eloquent\Collection
    {
        return ActionBrief::whereHas('detectedChange.mtoAlerts', function ($query) use ($mto) {
            $query->where('mto_id', $mto->id);
        })
        ->where('created_at', '>=', now()->subDays(30))
        ->orderBy('severity', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();
    }

    /**
     * Mark action brief as acknowledged by MTO.
     */
    public function acknowledgeActionBrief(ActionBrief $brief): void
    {
        $brief->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);

        Log::info('Action brief acknowledged', [
            'brief_id' => $brief->id,
            'acknowledged_by' => auth()->id(),
        ]);
    }

    /**
     * Mark action brief as completed.
     */
    public function completeActionBrief(ActionBrief $brief, string $completionNotes = ''): void
    {
        $brief->update([
            'completed_at' => now(),
            'completed_by' => auth()->id(),
            'completion_notes' => $completionNotes,
        ]);

        Log::info('Action brief completed', [
            'brief_id' => $brief->id,
            'completed_by' => auth()->id(),
        ]);
    }
}
