<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Reports\AnalyticsReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsReport $report) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission(Permission::ViewAnalytics), 403);

        $period = $request->string('period')->toString();

        return view('analytics.index', [
            'report' => $this->report->build($period),
        ]);
    }
}
