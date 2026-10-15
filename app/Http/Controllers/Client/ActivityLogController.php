<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->where('company_id', auth()->user()->company_id)
            ->latest()
            ->paginate(20);

        return view('client.activity_logs.index', compact('logs'));
    }
}
