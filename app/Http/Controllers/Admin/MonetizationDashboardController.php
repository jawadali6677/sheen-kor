<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Reports\MonetizationReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonetizationDashboardController extends Controller
{
    public function __construct(private MonetizationReport $report) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission(Permission::ManageMonetization), 403);

        $period = $request->string('period')->toString();

        return view('admin.monetization.dashboard', [
            'report' => $this->report->build($period),
        ]);
    }
}
