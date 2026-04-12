<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MtoProfile;
use App\Models\DetectedChange;
use App\Models\MtoAlert;
use App\Mail\DailyDigest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendDailyDigest extends Command
{
    protected $signature = 'digest:send-daily';
    protected $description = 'Send daily digest emails to MTOs with daily notification preference';

    public function handle(): int
    {
        try {
            $this->info('Sending daily digests...');

            $mtosWithDailyPreference = MtoProfile::where('notification_preference', 'daily')
                ->where('is_active', true)
                ->get();

            $sentCount = 0;
            $errorCount = 0;

            foreach ($mtosWithDailyPreference as $mto) {
                try {
                    // Get changes from last 24 hours
                    $yesterday = Carbon::now()->subHours(24);

                    $newChanges = DetectedChange::whereHas('mtoAlerts', function ($query) use ($mto) {
                        $query->where('mto_id', $mto->id);
                    })
                    ->where('created_at', '>=', $yesterday)
                    ->where('qa_status', 'approved')
                    ->orderBy('severity', 'desc')
                    ->get();

                    if ($newChanges->isEmpty()) {
                        $this->line("  No changes for {$mto->company_name} in last 24 hours");
                        continue;
                    }

                    // Get primary user for MTO
                    $primaryUser = $mto->users()->first();
                    if (!$primaryUser) {
                        $this->warn("  No users found for {$mto->company_name}");
                        continue;
                    }

                    // Send daily digest email
                    Mail::to($primaryUser->email)->send(new DailyDigest($mto, $newChanges));

                    $sentCount++;
                    $this->line("  Sent digest to {$mto->company_name} ({$newChanges->count()} changes)");

                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Daily digest send failed', [
                        'mto_id' => $mto->id,
                        'message' => $e->getMessage(),
                    ]);
                    $this->error("  Failed to send digest to {$mto->company_name}: {$e->getMessage()}");
                }
            }

            $this->info("Daily digest send completed: {$sentCount} sent, {$errorCount} errors");
            return 0;

        } catch (\Exception $e) {
            Log::error('Daily digest command error', ['message' => $e->getMessage()]);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
