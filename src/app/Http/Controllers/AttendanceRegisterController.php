<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\AttendanceRequest;
use Carbon\Carbon;

class AttendanceRegisterController extends Controller
{
    /** 打刻画面表示 */
    public function create()
    {
        $user = auth()->user();
        $tz   = config('app.timezone', 'Asia/Tokyo');

        // ?date=（ISO/和式どちらでも）→ 必ず startOfDay() に揃える
        $param = request('date');
        if ($param && str_contains($param, '年')) {
            $date = \Carbon\Carbon::createFromFormat('Y年n月j日', $param, $tz)->startOfDay();
        } elseif ($param) {
            $date = \Carbon\Carbon::parse($param, $tz)->startOfDay();
        } else {
            $date = \Carbon\Carbon::now($tz)->startOfDay();
        }

        // ここが肝：検索キーも DATETIME(00:00:00) で統一
        $attendance = \App\Models\Attendance::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date], // ← Carbon(… 00:00:00)
            [] // 余計なカラムは入れない
        );
        $attendance->load('breaks');

        $hasClockIn  = !empty($attendance->clock_in_at);
        $hasClockOut = !empty($attendance->clock_out_at);
        $onBreak     = $attendance->breaks()->whereNull('end_time')->exists();

        $canClockIn    = !$hasClockIn;
        $canClockOut   =  $hasClockIn && !$hasClockOut && !$onBreak;
        $canBreakStart =  $hasClockIn && !$hasClockOut && !$onBreak;
        $canBreakEnd   =  $hasClockIn && !$hasClockOut &&  $onBreak;

        $state = !$hasClockIn ? 'before_clock_in'
                : ($hasClockOut ? 'after_clock_out'
                : ($onBreak ? 'on_break' : 'after_clock_in'));

        return view('attendance.register', [
            'attendance'     => $attendance,
            'canClockIn'     => $canClockIn,
            'canClockOut'    => $canClockOut,
            'canBreakStart'  => $canBreakStart,
            'canBreakEnd'    => $canBreakEnd,
            'state'          => $state,
            'day'            => $date->toDateString(), // Blade向けは 'YYYY-MM-DD'
        ]);
    }



    // 出勤
    public function clockIn(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        $attendance = Attendance::where('user_id', Auth::id())
            ->whereDate('work_date', Carbon::now($tz)->toDateString())
            ->firstOrFail();

        if (!$attendance->clock_in_at) {
            $attendance->update(['clock_in_at' => Carbon::now($tz)]);
        }

        return back()->with('success', '出勤を記録しました');
    }

    // 退勤（★ 開いている休憩があれば minutes を確定してから退勤）
    public function clockOut(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        $attendance = Attendance::where('user_id', Auth::id())
            ->whereDate('work_date', Carbon::now($tz)->toDateString())
            ->firstOrFail();

        // 開きっぱなしの休憩を自動クローズ
        $open = $attendance->breaks()->whereNull('end_time')->latest('start_time')->first();
        if ($open) {
            $end    = Carbon::now($tz);
            $start  = Carbon::parse($open->start_time, $tz);
            $mins   = max(0, $start->diffInMinutes($end));
            $open->update([
                'end_time' => $end,
                'minutes'  => $mins,
            ]);
        }

        $attendance->update(['clock_out_at' => Carbon::now($tz)]);
        return back()->with('success', '退勤を記録しました');
    }

    // 休憩開始（minutes 初期化だけしておくと安心）
    public function breakStart(Attendance $attendance)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        // 既に休憩中なら二重開始しない
        if ($attendance->breaks()->whereNull('end_time')->exists()) {
            return back()->with('info', 'すでに休憩中です。');
        }

        $attendance->breaks()->create([
            'start_time' => Carbon::now($tz),
            'minutes'    => 0,
        ]);

        return back()->with('success', '休憩を開始しました');
    }

    // 休憩終了（★ minutes を確定）
    public function breakEnd(Attendance $attendance)
    {
        $tz  = config('app.timezone', 'Asia/Tokyo');
        $br  = $attendance->breaks()->whereNull('end_time')->latest('start_time')->first();

        if ($br) {
            $end   = Carbon::now($tz);
            $start = Carbon::parse($br->start_time, $tz);
            $mins  = max(0, $start->diffInMinutes($end));
            $br->update([
                'end_time' => $end,
                'minutes'  => $mins,
            ]);
        }

        return back()->with('success', '休憩を終了しました');
    }
}
