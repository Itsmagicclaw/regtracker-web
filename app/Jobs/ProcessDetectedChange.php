<?php

namespace App\Jobs;

use App\Models\DetectedChange;
use App\Models\MtoActionProgress;
use App\Models\MtoAlert;
use App\Services\ActionBriefService;
use App\Services\MtoMatcherService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDetectedChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public DetectedChange $change) {}

    public function handle(
        ActionBriefService  $actionBriefService,
        MtoMatcherService   $mtoMatcherService,
        NotificationService $notificationService,
    ): void {
        Log::info("Processing detected change ID: {$this->change->id} — {$this->change->title}");

        try {
            // 1. Generate action items
            $actionItems = $actionBriefService->generate($this->change);
            foreach ($actionItems as $item) {
                $this->change->actionItems()->create($item);
            }

            // 2. Find affected MTOs
            $affectedMtos = $mtoMatcherService->findAffectedMtos($this->change);

            if ($affectedMtos->isEmpty()) {
                Log::info("No MTOs matched for change ID: {$this->change->id}");
                return;
            }

            // 3. Create alerts and notify
            foreach ($affectedMtos as $mto) {
                // Skip if already alerted
                $exists = MtoAlert::where('mto_id', $mto->id)
                    ->where('change_id', $this->change->id)
                    ->exists();

                if ($exists) continue;

                $alert = MtoAlert::create([
                    'mto_id'      => $mto->id,
                    'change_id'   => $this->change->id,
                    'alerted_at'  => now(),
                    'alerted_via' => 'email',
                ]);

                // Create pending action progress records for each action item
                foreach ($this->change->actionItems as $actionItem) {
                    MtoActionProgress::create([
                        'mto_alert_id'   => $alert->id,
                        'action_item_id' => $actionItem->id,
                        'status'         => 'pending',
                    ]);
                }

                // Send immediate email for critical and high severity
                if (in_array($this->change->severity, ['critical', 'high'])) {
                    if (in_array($mto->notification_preference, ['instant', 'both'])) {
                        $notificationService->sendCriticalAlert($mto, $this->change);
                    }
                }

                Log::info("Alert created for MTO: {$mto->mto_name} (change: {$this->change->id})");
            }

        } catch (\Throwable $e) {
            Log::error("Failed to process change ID: {$this->change->id} — {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessDetectedChange job permanently failed for change ID: {$this->change->id} — {$exception->getMessage()}");
    }
}
