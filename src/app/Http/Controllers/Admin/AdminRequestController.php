<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Holiday;
use Illuminate\Support\Facades\Schema;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request as HttpRequest; 
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminRequestController extends Controller
{
    /** ダッシュボード（KPI + 直近5件） */
    public function dashboard()
    {
        $metrics = [
            'pending_requests'  => AttendanceRequest::where('status', 'pending')->count(),
            'total_users'       => User::count(),
            'total_attendances' => Attendance::count(),
            'holidays'          => Holiday::count(),
        ];

        $recentPending = AttendanceRequest::where('status', 'pending')
            ->latest('id')->take(5)->get();

        $userNames = User::whereIn('id', $recentPending->pluck('user_id')->filter()->all())
            ->pluck('name', 'id');

        return view('admin.index', compact('metrics', 'recentPending', 'userNames'));
    }

    /** 申請一覧（簡単フィルタ付き） */
    public function index(HttpRequest $http)
    {
        $tab = $http->query('tab', 'pending'); // デフォルトは pending

        $query = AttendanceRequest::with('user');

        if ($tab === 'approved') {
            $query->where('status', 'approved');
        } elseif ($tab === 'rejected') {
            $query->where('status', 'rejected');
        } else {
            $query->where('status', 'pending');
        }

        $requests = $query->latest('id')
            ->paginate(20)
            ->appends(['tab' => $tab]);

        $typeLabels = [
            'attendance_correction' => '勤怠修正',
            'leave'                 => '休暇',
            'overtime'              => '残業',
        ];

        return view('admin.requests.index', compact('requests', 'tab', 'typeLabels'));
    }

    /** 申請詳細 */
    public function show(AttendanceRequest $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        $request->load(['user', 'attendance.breaks']);
        $att = $request->attendance;

        $srcBreaks = collect(data_get($request->changes, 'breaks', []));
        if ($srcBreaks->isEmpty() && $att) {
            $srcBreaks = collect($att->breaks)->map(fn($b) => [
                'start_time' => $b->start_time,
                'end_time'   => $b->end_time,
            ]);
        }

        // 既存の整形は Collection で保持
        $breakPairs = $srcBreaks->map(function ($b) use ($tz) {
            $sRaw = data_get($b, 'start_time') ?? data_get($b, 'start');
            $eRaw = data_get($b, 'end_time')   ?? data_get($b, 'end');
            return [
                'start' => $sRaw ? \Carbon\Carbon::parse($sRaw, $tz)->format('H:i') : '',
                'end'   => $eRaw ? \Carbon\Carbon::parse($eRaw, $tz)->format('H:i') : '',
            ];
        });

        // ★ 予備の空欄を常に最後に1本追加
        $breakPairs = $breakPairs->push(['start' => '', 'end' => ''])->values()->all();

        return view('admin.requests.show', [
            'r' => $request,
            'a' => $att,
            'breakPairs' => $breakPairs,
        ]);
    }

    /** 承認 */
    public function approve(HttpRequest $http, AttendanceRequest $request)
    {
        // ミドルウェアで守っている想定だが念のため
        abort_unless($http->user()?->is_admin, 403);

        DB::transaction(function () use ($http, $request) {
            // 1) 反映先の勤怠
            $att = $request->attendance;

            // attendance_id が無い申請（target_dateのみ）の場合は作成
            if (!$att && $request->target_date) {
                $att = Attendance::firstOrCreate(
                    ['user_id' => $request->user_id, 'work_date' => $request->target_date],
                    []
                );
            }

            // 勤怠が特定できたら差分反映
            if ($att && is_array($request->changes)) {
                $tz = config('app.timezone');

                $allow = [
                    'work_date',
                    'clock_in_at', 'clock_out_at',
                    'break_started_at','break_ended_at',
                    'break2_started_at','break2_ended_at',
                ];

                foreach ($allow as $key) {
                    if (array_key_exists($key, $request->changes)) {
                        $val = $request->changes[$key];

                        if ($val === '' || $val === null) {
                            $att->{$key} = null;
                        } else {
                            if ($key === 'work_date') {
                                $att->{$key} = Carbon::parse($val, $tz)->startOfDay();
                            } else {
                                $att->{$key} = Carbon::parse($val, $tz);
                            }
                        }
                    }
                }

                if (method_exists($att, 'recalcTotals')) {
                    $att->recalcTotals();
                }
                $att->save();
            }

            // 2) 申請の状態更新
            $request->status = \App\Models\AttendanceRequest::STATUS_APPROVED ?? 'approved';
            // カラムがある時だけセット（SQLiteや古いDBでも安全）
            
            $table = $request->getTable(); // 例: 'requests'
            if (Schema::hasColumn($table, 'approved_at')) {
                $request->approved_at = now();
            }
            if (Schema::hasColumn($table, 'approved_by')) {
                $request->approved_by = auth()->id();
            }
            $request->save();
        });

        return back()->with('success', '承認しました。勤怠へ反映済みです。');
    }

    /** 却下 */
    public function reject(HttpRequest $http, AttendanceRequest $request)
    {
        abort_unless($http->user()?->is_admin, 403);

        $request->update([
            'status'      => 'rejected',
            'approved_at' => now(),
            'approved_by' => $http->user()->id,
        ]);

        return back()->with('success', '申請を却下しました。');
    }
}
