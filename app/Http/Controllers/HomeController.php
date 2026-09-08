<?php

namespace App\Http\Controllers;

use App\Mcp\ToolRegistry;
use App\Models\McpAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A status page: what this server exposes, how to connect, and (for signed-in
 * users) the most recent audit entries. It never shows business data.
 */
final class HomeController extends Controller
{
    public function __invoke(Request $request, ToolRegistry $registry): View
    {
        return view('home', [
            'serverUrl' => url('/mcp'),
            'tools' => $registry->all(),
            'writesEnabled' => (bool) config('mcp.writes_enabled'),
            'scopes' => config('mcp.scopes'),
            'recentAudit' => $request->user()
                ? McpAuditLog::query()->latest('id')->limit(15)->get()
                : collect(),
        ]);
    }
}
