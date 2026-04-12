<?php

namespace App\Services;

use App\Models\MtoAlert;
use App\Models\MtoProfile;
use App\Models\DetectedChange;
use App\Mail\CriticalAlert;
use App\Mail\DailyDigest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send notifications to MTOs for a detected change based on their preferences.
     */
    public function notifyMtosForChange(DetectedChange $change): int
    {
        try {
            $notificationCount = 0;

            // Get all MtoAlerts for this change
            $alerts = MtoAlert::where('detected_change_id', $change->id)->get();

            foreach ($alerts as $alert) {
                // Skip if already notified
                if ($alert->notified_at !== null) {
                    continue;
                }

                $mto = $alert->mto;

                // Check notification preference
                if ($mto->notification_preference === 'disabled') {
                    continue;
                }

                // For critical severity changes, send immediate notification
                if ($change->severity === 'critical') {
                    if ($mto->notification_preference === 'immediate' || $mto->notification_preference === 'daily') {
                        $this->sendCriticalAlert($mto, $change, $alert);
                        $notificationCount++;
                    }
                }
                // For high severity, send if preference allows
                else if ($change->severity === 'high') {
                    if ($mto->notification_preference === 'immediate') {
                        $this->sendCriticalAlert($mto, $change, $alert);
                        $notificationCount++;
                    }
                }
                // For medium severity, batch in daily digest
                else if ($change->severity === 'medium') {
                    if ($mto->notification_preference === 'immediate') {
                        $this->sendCriticalAlert($mto, $change, $alert);
                        $notificationCount++;
                    }
                }
            }

            return $notificationCount;

        } catch (\Exception $e) {
            Log::error('MTO notification error', [
                'change_id' => $change->id,
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Send critical alert email to MTO.
     */
    private function sendCriticalAlert(MtoProfile $mto, DetectedChange $change, MtoAlert $alert): void
    {
        try {
            $primaryUser = $mto->users()->first();
            if (!$primaryUser || !$primaryUser->email) {
                Log::warning('No primary user with email for MTO', ['mto_id' => $mto->id]);
                return;
            }

            Mail::to($primaryUser->email)->send(new CriticalAlert($mto, $change));

            // Mark alert as notified
            $alert->update([
                'notified_at' => now(),
                'notification_method' => 'email',
            ]);

            Log::info('Critical alert sent to MTO', [
                'mto_id' => $mto->id,
                'change_id' => $change->id,
                'email' => $primaryUser->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send critical alert', [
                'mto_id' => $mto->id,
                'change_id' => $change->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send webhook notification to MTO if configured.
     */
    public function sendWebhookNotification(MtoProfile $mto, DetectedChange $change): bool
    {
        try {
            if (empty($mto->webhook_url)) {
                return false;
            }

            $payload = [
                'event' => 'regulatory_change_detected',
                'change_id' => $change->id,
                'severity' => $change->severity,
                'change_type' => $change->change_type,
                'source_type' => $change->regulatorySource->source_type,
                'summary' => $change->summary,
                'timestamp' => now()->toIso8601String(),
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->post($mto->webhook_url, $payload);

            if ($response->successful()) {
                Log::info('Webhook notification sent', [
                    'mto_id' => $mto->id,
                    'webhook_url' => $mto->webhook_url,
                    'status' => $response->status(),
                ]);
                return true;
            } else {
                Log::warning('Webhook notification failed', [
                    'mto_id' => $mto->id,
                    'webhook_url' => $mto->webhook_url,
                    'status' => $response->status(),
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Webhook notification error', [
                'mto_id' => $mto->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send daily digest email to MTO.
     */
    public function sendDailyDigest(MtoProfile $mto): bool
    {
        try {
            $primaryUser = $mto->users()->first();
            if (!$primaryUser || !$primaryUser->email) {
                Log::warning('No primary user with email for daily digest', ['mto_id' => $mto->id]);
                return false;
            }

            // Get changes from last 24 hours
            $yesterday = now()->subDays(1);
            $changes = DetectedChange::whereHas('mtoAlerts', function ($query) use ($mto) {
                $query->where('mto_id', $mto->id);
            })
            ->where('qa_status', 'approved')
            ->where('created_at', '>=', $yesterday)
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

            if ($changes->isEmpty()) {
                Log::info('No changes for daily digest', ['mto_id' => $mto->id]);
                return false;
            }

            Mail::to($primaryUser->email)->send(new DailyDigest($mto, $changes));

            Log::info('Daily digest sent', [
                'mto_id' => $mto->id,
                'email' => $primaryUser->email,
                'change_count' => $changes->count(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send daily digest', [
                'mto_id' => $mto->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send bulk daily digests to all MTOs with daily preference.
     */
    public function sendAllDailyDigests(): int
    {
        try {
            $mtos = MtoProfile::where('is_active', true)
                ->where('notification_preference', 'daily')
                ->get();

            $sent = 0;
            foreach ($mtos as $mto) {
                if ($this->sendDailyDigest($mto)) {
                    $sent++;
                }
            }

            Log::info('Daily digest batch completed', [
                'mtos_processed' => $mtos->count(),
                'digests_sent' => $sent,
            ]);

            return $sent;

        } catch (\Exception $e) {
            Log::error('Daily digest batch error', [
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Send SMS notification to MTO if phone number configured.
     */
    public function sendSmsNotification(MtoProfile $mto, DetectedChange $change): bool
    {
        try {
            if (empty($mto->phone_number)) {
                return false;
            }

            $message = sprintf(
                "RegTracker Alert: %s - %s change detected in %s. Severity: %s. Check dashboard for details.",
                $change->regulatorySource->name,
                ucfirst($change->change_type),
                $change->regulatorySource->source_type,
                strtoupper($change->severity)
            );

            // SMS integration would go here
            // For now, just log the intention
            Log::info('SMS notification queued', [
                'mto_id' => $mto->id,
                'phone' => substr($mto->phone_number, -4), // Log last 4 digits only
                'message' => $message,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('SMS notification error', [
                'mto_id' => $mto->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get notification statistics for an MTO.
     */
    public function getNotificationStats(MtoProfile $mto): array
    {
        try {
            $thirtyDaysAgo = now()->subDays(30);

            $stats = [
                'total_alerts' => MtoAlert::where('mto_id', $mto->id)
                    ->count(),
                'notified_alerts' => MtoAlert::where('mto_id', $mto->id)
                    ->whereNotNull('notified_at')
                    ->count(),
                'pending_alerts' => MtoAlert::where('mto_id', $mto->id)
                    ->whereNull('notified_at')
                    ->count(),
                'critical_last_30_days' => MtoAlert::where('mto_id', $mto->id)
                    ->whereHas('detectedChange', function ($query) {
                        $query->where('severity', 'critical');
                    })
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
                'high_last_30_days' => MtoAlert::where('mto_id', $mto->id)
                    ->whereHas('detectedChange', function ($query) {
                        $query->where('severity', 'high');
                    })
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->count(),
            ];

            return $stats;

        } catch (\Exception $e) {
            Log::error('Notification stats error', [
                'mto_id' => $mto->id,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
