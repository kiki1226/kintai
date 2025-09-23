<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'note',
    ];

    protected $casts = [
        'work_date'    => 'date',
        'clock_in_at'  => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    

    protected static function booted()
    {
        static::saving(function (self $m) {
            if (empty($m->work_date)) {
                $tz = config('app.timezone', 'Asia/Tokyo');
                if ($m->clock_in_at) {
                    $m->work_date = $m->clock_in_at->timezone($tz)->toDateString();
                } elseif ($m->clock_out_at) {
                    $m->work_date = $m->clock_out_at->timezone($tz)->toDateString();
                } else {
                    // どちらも無い場合は「今」の日付でもOK
                    $m->work_date = \Carbon\Carbon::now($tz)->toDateString();
                }
            }
        });
    }

    /** リレーション：休憩 */
    public function breaks(): HasMany
    {
        return $this->hasMany(\App\Models\AttendanceBreak::class, 'attendance_id');
    }

    /** 分→HH:MM */
    protected function minutesToHm(int $min): string
    {
        $h = intdiv(max(0, $min), 60);
        $m = max(0, $min) % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    /** 休憩合計(分) */
    public function getBreakMinutesAttribute(): int
    {
        $collection = $this->relationLoaded('breaks') ? $this->breaks : $this->breaks()->get();

        return (int) $collection->sum(function ($b) {
            if (!is_null($b->minutes)) {
                return (int) $b->minutes;
            }
            if ($b->start_time && $b->end_time) {
                try { return $b->start_time->diffInMinutes($b->end_time); }
                catch (\Throwable $e) { return 0; }
            }
            return 0;
        });
    }

    /** 休憩合計(HH:MM) */
    public function getBreakHmAttribute(): string
    {
        return $this->minutesToHm($this->break_minutes);
    }

    /** 実働合計(HH:MM) */
    public function getTotalHmAttribute(): ?string
    {
        if (!$this->clock_in_at || !$this->clock_out_at) {
            return null; // 退勤前は空にする
        }
        $gross = $this->clock_in_at->diffInMinutes($this->clock_out_at);
        $net   = max(0, $gross - $this->break_minutes);
        return $this->minutesToHm($net);
    }

    /** 申請履歴 */
    public function requests(): HasMany
    {
        return $this->hasMany(\App\Models\AttendanceRequest::class, 'attendance_id');
    }
    
    /** 「承認待ち」か？ */
    public function getIsPendingAttribute(): bool
    {
        return $this->relationLoaded('requests')
            ? $this->requests->contains(fn($r) => $r->status === 'pending')
            : $this->requests()->where('status', 'pending')->exists();
    }

    /** 「編集あり」か？ ＝ 何かしら入力・変更がある状態 */
    public function getIsEditedAttribute(): bool
    {
        if (!is_null($this->clock_in_at) || !is_null($this->clock_out_at) || !blank($this->note)) {
            return true;
        }
        // 休憩が1件でもあれば編集あり
        return $this->relationLoaded('breaks') ? $this->breaks->isNotEmpty() : $this->breaks()->exists();
    }

    public function getIsSubmittedAttribute(): bool
    {
        $statuses = ['pending','approved','rejected']; // 取消も含めるなら 'canceled' も追加
        return $this->relationLoaded('requests')
            ? $this->requests->contains(fn($r) => in_array($r->status, $statuses, true))
            : $this->requests()->whereIn('status', $statuses)->exists();
    }
}
