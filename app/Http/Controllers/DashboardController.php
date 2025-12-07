<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeWebsite;
use App\Models\Scan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'selectedScan' => null,
        ]);
    }

    public function show(Request $request, Scan $scan): Response
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        return Inertia::render('Dashboard', [
            'selectedScan' => $this->formatScan($scan),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $scan = Scan::create([
            'user_id' => $request->user()->id,
            'url' => $validated['url'],
            'status' => 'pending',
        ]);

        AnalyzeWebsite::dispatch($scan);

        return redirect()->route('dashboard.scan', $scan);
    }

    public function scanStatus(Request $request, Scan $scan): array
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        // Auto-mark stale scans as failed (e.g., if worker was killed)
        if ($scan->isStale()) {
            $scan->update([
                'status' => 'failed',
                'failed_step' => 'timeout',
            ]);
            $scan->refresh();
        }

        return $this->formatScan($scan);
    }

    public function destroy(Request $request, Scan $scan): RedirectResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(403);
        }

        $scan->delete();

        return redirect()->route('dashboard');
    }

    private function formatScan(Scan $scan): array
    {
        return [
            'id' => $scan->id,
            'url' => $scan->url,
            'cms_type' => $scan->cms_type,
            'status' => $scan->status,
            'current_step' => $scan->current_step,
            'failed_step' => $scan->failed_step,
            'lighthouse_performance' => $scan->lighthouse_performance,
            'lighthouse_accessibility' => $scan->lighthouse_accessibility,
            'lighthouse_seo' => $scan->lighthouse_seo,
            'lighthouse_average' => $scan->lighthouse_average,
            'cta_score' => $scan->cta_score,
            'cta_details' => $scan->cta_details,
            'cta_count' => $scan->cta_count,
            'form_friction_score' => $scan->form_friction_score,
            'form_details' => $scan->form_details,
            'form_count' => $scan->form_count,
            'trust_signals' => $scan->trust_signals,
            'trust_signal_count' => $scan->trust_signal_count,
            'mobile_issues' => $scan->mobile_issues,
            'mobile_issue_count' => $scan->mobile_issue_count,
            'readability_score' => $scan->readability_score,
            'image_issues' => $scan->image_issues,
            'image_issue_count' => $scan->image_issue_count,
            'schema_detected' => $scan->schema_detected,
            'screenshot_url' => $scan->screenshot_url,
            'overall_score' => $scan->overall_score,
            'created_at' => $scan->created_at->toIso8601String(),
            'updated_at' => $scan->updated_at->toIso8601String(),
        ];
    }
}
