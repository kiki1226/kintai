<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Http\Requests\AttendanceRequest;
use App\Http\Requests\AttendanceRequest as AttendanceFormRequest;
use Illuminate\Support\Facades\DB;
use App\Models\AttendanceBreak;

class AttendanceController extends Controller
{
    /** 勤怠一覧 */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tz   = config('app.timezone', 'Asia/Tokyo');

        $base = $request->query('m')
            ? Carbon::parse($request->query('m'))->startOfMonth()
            : Carbon::now($tz)->startOfMonth();

        // ★ requests も eager load して is_submitted を即判定できるように
        $attendances = Attendance::with(['breaks'])
            ->where('user_id', $user->id)
            ->orderBy('work_date', 'desc')
            ->get();

        $holidayDates = Holiday::pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        return view('attendance.index', [
            'attendances'  => $attendances,
            'base'         => $base,
            'prevMonth'    => $base->copy()->subMonth()->toDateString(),
            'nextMonth'    => $base->copy()->addMonth()->toDateString(),
            'holidayDates' => $holidayDates,
        ]);
    }

    /** 勤怠編集画面 */
    public function edit(Attendance $attendance)
    {
        // ★ 申請済みは編集させず詳細へ
        if ($attendance->is_submitted) {
            return redirect()
                ->route('attendance.show', [
                    'attendance' => $attendance->id,
                    'm'   => request('m'),
                    'from'=> request('from', 'index'),
                ])
                ->with('info', '申請済みのため修正できません。');
        }

        $wd = $attendance->work_date;

        $breakInputs = $attendance->breaks->map(function($b) {
            return [
                'start' => optional($b->start_time)->format('H:i'),
                'end'   => optional($b->end_time)->format('H:i'),
            ];
        })->toArray();

        if (empty($breakInputs)) {
            $breakInputs[] = ['start' => '', 'end' => ''];
        }

        return view('attendance.edit', compact('attendance', 'wd', 'breakInputs'));
    }

    /** 勤怠詳細画面 */
    public function show(Attendance $attendance)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        // 日付（見出し用）
        $d = $attendance->work_date instanceof \Carbon\CarbonInterface
            ? $attendance->work_date->copy()->locale('ja')
            : \Carbon\Carbon::parse($attendance->work_date)->locale('ja');
        $dateY  = $d->isoFormat('YYYY年');
        $dateMD = $d->isoFormat('M月D日 (ddd)');

        // 出退勤（H:i）
        $clockIn  = optional($attendance->clock_in_at)?->timezone($tz)?->format('H:i') ?? '';
        $clockOut = optional($attendance->clock_out_at)?->timezone($tz)?->format('H:i') ?? '';

        // 休憩（H:i 文字列の配列）
        $breaks = $attendance->breaks
            ->map(fn($b) => [
                'start' => optional($b->start_time)?->timezone($tz)?->format('H:i'),
                'end'   => optional($b->end_time)?->timezone($tz)?->format('H:i'),
            ])
            ->filter(fn($x) => $x['start'] && $x['end'])
            ->values()
            ->all();

        // 直近の申請ステータス
        $latestRequest = $attendance->requests()->latest('id')->first();
        $status  = $latestRequest?->status; // null|pending|approved|rejected
        $statusLabel = match ($status) {
            'pending'  => '申請中',
            'approved' => '承認済',
            'rejected' => '却下済',
            default    => null,
        };
        // 既存Blade互換のため
        $pending = ($status === 'pending');

        // 戻り導線
        $from = request('from', 'index');
        $back = request('m');

        return view('attendance.show', compact(
            'attendance',
            'dateY', 'dateMD', 'clockIn', 'clockOut', 'breaks',
            'status', 'statusLabel', 'pending',
            'from', 'back'
        ));
    }
    
    /** 勤怠更新処理 */
    public function update(AttendanceFormRequest $request, Attendance $attendance)
    {
        DB::transaction(function () use ($request, $attendance) {
            // 1) 勤怠本体を更新
            $data = $request->validated();

            $attendance->update([
                'clock_in_at'  => $data['clock_in']  ?? null,
                'clock_out_at' => $data['clock_out'] ?? null,
                'note'         => $data['note']      ?? null,
            ]);

            // 2) 休憩は再登録
            $attendance->breaks()->delete();
            foreach ($data['breaks'] ?? [] as $br) {
                if (!empty($br['start']) && !empty($br['end'])) {
                    $attendance->breaks()->create([
                        'start_time' => $br['start'],
                        'end_time'   => $br['end'],
                    ]);
                }
            }

            // 3) 申請（AttendanceRequest）を pending で用意
            //    既に pending があれば touch、無ければ作成
            $pending = $attendance->requests()->where('status', 'pending')->latest('id')->first();
            $reason  = $data['note'] ?? null;
            if ($pending) {
                // ★ 理由が来ていたら更新。空(null)なら従来の理由を維持して updated_at だけ更新
                if ($reason !== null) {
                    $pending->update(['reason' => $reason]); // updated_at も自動更新される
                } else {
                    $pending->touch(); // 時刻だけ更新
                }
            } else {
                $attendance->requests()->create([
                    'user_id' => \Auth::id(),
                    'status'  => 'pending',
                    'reason'  => $reason,   // 新規は理由を入れる
                ]);
            }
        });

        // 4) 詳細（申請中）へ
        return redirect()->route('attendance.show', [
            'attendance' => $attendance->id,
            'from'       => $request->input('from', 'index'),
            'm'          => $request->input('back'),
        ])->with('success', '勤怠を更新し、申請中として保存しました。');
    }
}
