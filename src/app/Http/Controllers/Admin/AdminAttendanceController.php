<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;  
use App\Http\Requests\Admin\AttendanceDayRequest; 
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    /** 指定日の全スタッフ勤怠一覧 */
    public function index(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'), $tz)->startOfDay()
            : now($tz)->startOfDay();

        // 休憩を eager load
        $attendances = Attendance::whereDate('work_date', $date->toDateString())
            ->with('breaks')   // ← 追加
            ->get()
            ->keyBy('user_id');

        $userIds = $attendances->keys();
        $users = User::whereIn('id', $userIds)->orderBy('name')->get();

        $rows = [];
        foreach ($users as $user) {
            $att = $attendances->get($user->id);

            $rows[] = [
                'id'    => $user->id,
                'name'  => $user->name,
                'start' => $att?->clock_in_at?->timezone($tz)->format('H:i') ?? '',
                'end'   => $att?->clock_out_at?->timezone($tz)->format('H:i') ?? '',
                'break' => $att?->break_hm ?? '',  // ← ここでアクセサが効く
                'total' => $att?->total_hm ?? '',
            ];
        }

        return view('admin.attendances.index', [
            'target'   => $date,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'rows'     => $rows,
        ]);
    }

    /** ユーザー別：指定月の一覧（雛形） */
    public function user(Request $request, User $user)
    {
        // ① 月処理（YYYY-MM、既定は今月）
        $ym        = $request->query('month', now()->format('Y-m'));
        $month     = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
        $start     = $month->copy()->startOfMonth();
        $end       = $month->copy()->endOfMonth();
        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');

        // ② 日付カラム（work_date / date）を自動判定
        $dateKey = Schema::hasColumn('attendances', 'work_date') ? 'work_date' : 'date';

        // ③ まとめて取得 → 日付で引けるようにマップ化
        $atts = Attendance::where('user_id', $user->id)
            ->whereDate($dateKey, '>=', $start->toDateString())
            ->whereDate($dateKey, '<=', $end->toDateString())
            ->get()
            ->keyBy(fn($a) => Carbon::parse($a->$dateKey)->toDateString());

        // ④ 1ヶ月分の行を生成
        $rows = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $att = $atts->get($d->toDateString());

            $rows[] = [
                'date'  => $d->toDateString(),
                'label' => $d->format('m/d') . '('. $this->wdayJa($d) .')',
                'start' => $att?->clock_in_at?->format('H:i') ?? '',
                'end'   => $att?->clock_out_at?->format('H:i') ?? '',
                'break' => $att?->break_hm ?? '',   // ✅ アクセサだけを使う
                'total' => $att?->total_hm ?? '',   // ✅ アクセサだけを使う
            ];
        }

        // ⑤ CSV 出力（任意）
        if ($request->query('export') === 'csv') {
            $filename = "attendance_{$user->id}_{$month->format('Y_m')}.csv";
            $headers  = ['Content-Type' => 'text/csv; charset=UTF-8'];
            $callback = function() use ($rows, $user, $month) {
                $out = fopen('php://output', 'w');
                // Excel で文字化けしないように BOM
                fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($out, ["{$user->name} さんの勤怠", $month->format('Y年n月')]);
                fputcsv($out, ['日付','出勤','退勤','休憩','合計']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['label'],$r['start'],$r['end'],$r['break'],$r['total']]);
                }
                fclose($out);
            };
            return response()->streamDownload($callback, $filename, $headers);
        }

        return view('admin.attendances.user', [
            'user'      => $user,
            'month'     => $month,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'rows'      => $rows,
        ]);
    }

    /* ---------- ヘルパー ---------- */

    private function wdayJa(Carbon $d): string
    {
        // 日月火水木金土
        $w = ['日','月','火','水','木','金','土'];
        return $w[$d->dayOfWeek];
    }

    private function safeDiffMins($s, $e): int
    {
        if (!$s || !$e) return 0;
        $ss = $s instanceof Carbon ? $s : Carbon::parse($s);
        $ee = $e instanceof Carbon ? $e : Carbon::parse($e);
        if ($ee->lessThanOrEqualTo($ss)) return 0;
        return $ee->diffInMinutes($ss);
    }

    private function hhmm(int $m): string
    {
        $m = max(0, $m);
        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    }

    private function calcBreakMins(?Attendance $att): int
    {
        if (!$att) return 0;

        $m = 0;
        $m += $this->safeDiffMins($att->break_started_at ?? null, $att->break_ended_at ?? null);

        if (Schema::hasColumn('attendances','break2_started_at') &&
            Schema::hasColumn('attendances','break2_ended_at')) {
            $m += $this->safeDiffMins($att->break2_started_at ?? null, $att->break2_ended_at ?? null);
        }

        // ★時刻が入っていない時は break_minutes を採用
        if ($m === 0 && isset($att->break_minutes)) {
            $m = (int) $att->break_minutes;
        }
        return $m;
    }

    /** ユーザー別：指定日の詳細 */
    public function showDay(User $user, string $date)
    {
        $tz     = config('app.timezone', 'Asia/Tokyo');
        $target = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();

        // その日の勤怠（無ければ空のモデル）
        $attendance = \App\Models\Attendance::with('breaks')
            ->firstOrNew([
                'user_id'   => $user->id,
                'work_date' => $target->toDateString(),
            ]);

        // フォームに流し込む休憩配列（H:i）
        $breakInputs = $attendance->breaks->map(function ($b) use ($tz) {
            return [
                'start' => optional($b->start_time)?->timezone($tz)?->format('H:i'),
                'end'   => optional($b->end_time)?->timezone($tz)?->format('H:i'),
            ];
        })->values()->all();

        // 1行も無ければ空行を1つ用意
        if (empty($breakInputs)) {
            $breakInputs[] = ['start' => '', 'end' => ''];
        }

        return view('admin.attendances.show', compact(
            'user', 'attendance', 'target', 'breakInputs'
        ));
    }

     /** 勤怠更新 */
    public function update(AttendanceFormRequest $request, Attendance $attendance)
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $data = $request->validated();

        // 勤務日（Y-m-d）を基準に HH:mm を結合
        $workDate = ($attendance->work_date instanceof Carbon
            ? $attendance->work_date
            : Carbon::parse($attendance->work_date, $tz)
        )->toDateString();

        \DB::transaction(function () use ($attendance, $data, $tz, $workDate) {

            // --- 出勤・退勤（★ キー名は _at で受け取る） ---
            $in  = !empty($data['clock_in_at'])
                ? Carbon::createFromFormat('Y-m-d H:i', $workDate.' '.$data['clock_in_at'], $tz)
                : null;

            $out = !empty($data['clock_out_at'])
                ? Carbon::createFromFormat('Y-m-d H:i', $workDate.' '.$data['clock_out_at'], $tz)
                : null;

            // 退勤が出勤より早い場合は翌日に補正
            if ($in && $out && $out->lt($in)) {
                $out->addDay();
            }

            $attendance->update([
                'clock_in_at'  => $in,
                'clock_out_at' => $out,
                'note'         => $data['note'] ?? null,
            ]);

            // --- 休憩：丸ごと入れ替え（空欄や片方欠けは無視） ---
            $sanitizedBreaks = collect($data['breaks'] ?? [])
                ->filter(fn($b) =>
                    (isset($b['start']) && trim($b['start']) !== '') ||
                    (isset($b['end'])   && trim($b['end'])   !== '')
                )
                ->map(function ($b) use ($tz, $workDate) {
                    $s = trim($b['start'] ?? '');
                    $e = trim($b['end']   ?? '');
                    if ($s === '' || $e === '') {
                        return null; // 片方欠けは捨てる（バリデで弾いてもOK）
                    }
                    $start = Carbon::createFromFormat('Y-m-d H:i', $workDate.' '.$s, $tz);
                    $end   = Carbon::createFromFormat('Y-m-d H:i', $workDate.' '.$e, $tz);
                    if ($end->lt($start)) $end->addDay();

                    return [
                        'start_time' => $start,
                        'end_time'   => $end,
                        'minutes'    => $start->diffInMinutes($end),
                    ];
                })
                ->filter()  // null除去
                ->values()
                ->all();

            $attendance->breaks()->delete();
            if (!empty($sanitizedBreaks)) {
                $attendance->breaks()->createMany($sanitizedBreaks);
            }
        });

        return redirect()->route('attendance.show', [
            'attendance' => $attendance->id,
            'from'       => $request->input('from', 'index'),
            'm'          => $request->input('back'),
        ])->with('success', '勤怠を更新し、申請中として保存しました。');
    }

    public function updateDay(AttendanceDayRequest $request, User $user, string $date)
    {
        $tz     = config('app.timezone', 'Asia/Tokyo');
        $target = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $target->toDateString()],
            []
        );

        $data   = $request->validated();        // ★ バリデ済み
        $breaks = $data['breaks'] ?? [];        // ★ これも使い回す

        $in  = $request->filled('clock_in_at')
            ? Carbon::createFromFormat('Y-m-d H:i', $date.' '.$request->input('clock_in_at'), $tz) : null;
        $out = $request->filled('clock_out_at')
            ? Carbon::createFromFormat('Y-m-d H:i', $date.' '.$request->input('clock_out_at'), $tz) : null;
        if ($in && $out && $out->lt($in)) $out->addDay();

        DB::transaction(function () use ($attendance, $in, $out, $date, $tz, $data, $breaks) {
            $attendance->update([
                'clock_in_at'  => $in,
                'clock_out_at' => $out,
                'note'         => $data['note'] ?? null,
            ]);

            $attendance->breaks()->delete();
            foreach ($breaks as $br) {
                $s = trim($br['start'] ?? ''); $e = trim($br['end'] ?? '');
                if ($s === '' && $e === '') continue;
                if ($s === '' || $e === '')  continue;

                $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$s, $tz);
                $end   = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$e, $tz);
                if ($end->lt($start)) $end->addDay();

                $attendance->breaks()->create([
                    'start_time' => $start,
                    'end_time'   => $end,
                    'minutes'    => $start->diffInMinutes($end),
                ]);
            }
        });

        return redirect()
            ->route('admin.attendances.index', ['date' => $target->toDateString()])
            ->with('success', '勤怠を更新しました。');
    }

    /** 分を HH:MM 表示に */
    private function mm(int $mins): string
    {
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return sprintf('%02d:%02d', $h, $m);
    }


    /** 最初に存在するカラム値を返す */
    private function col(Attendance $att, array $names)
    {
        foreach ($names as $n) {
            if (isset($att->$n) && $att->$n) return $att->$n;
        }
        return null;
    }

    /**
     * "HH:MM" 文字列や ISO文字列を Carbon に変換する
     */
    private function toCarbon(?string $time, Carbon $baseDate, string $tz): ?Carbon
    {
        if (!$time) return null;

        // HH:MM の形式なら日付と結合
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return Carbon::parse($baseDate->toDateString() . ' ' . $time, $tz);
        }

        // それ以外はそのままパース
        return Carbon::parse($time, $tz);
    }

}
