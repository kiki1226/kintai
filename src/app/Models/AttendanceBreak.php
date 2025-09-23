<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceBreak extends Model
{
    use HasFactory;

    // ★ 休憩の正しいテーブル名
    protected $table = 'attendance_breaks';

    // ★ create() で埋めるカラム
    protected $fillable = ['attendance_id', 'start_time', 'end_time', 'minutes'];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
        'minutes'    => 'integer',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    // ★ minutes 自動計算（任意だが推奨）
    protected static function booted()
    {
        static::saving(function (self $m) {
            if ($m->start_time && $m->end_time) {
                // end > start のときだけ差分、そうでなければ 0
                $m->minutes = $m->end_time->greaterThan($m->start_time)
                    ? $m->start_time->diffInMinutes($m->end_time)
                    : 0;
            } else {
                $m->minutes = 0;
            }
        });
    }
}
