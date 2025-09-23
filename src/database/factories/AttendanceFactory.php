<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $d = Carbon::parse('2025-09-08');

        return [
            'user_id'      => User::factory(),
            'work_date'    => $d->toDateString(),
            'clock_in_at'  => $d->copy()->setTime(9, 0),
            'clock_out_at' => $d->copy()->setTime(18, 0),
            'note'         => null,
        ];
    }

    public function forDate(string $date): static
    {
        return $this->state(fn(array $attrs) => ['work_date' => $date]);
    }

    // ★ 旧: times(...) → 新: withTimes(...)
    public function withTimes(?string $in, ?string $out, ?string $date = null): static
    {
        return $this->state(function (array $attrs) use ($in, $out, $date) {
            $base = Carbon::parse($date ?? $attrs['work_date']);
            return [
                'clock_in_at'  => $in  ? Carbon::parse($base->toDateString().' '.$in)  : null,
                'clock_out_at' => $out ? Carbon::parse($base->toDateString().' '.$out) : null,
            ];
        });
    }

    /** 休憩1本を同時に作るヘルパ */
    public function withBreak(string $start, string $end, ?string $date = null): static
    {
        return $this->afterCreating(function (Attendance $a) use ($start, $end, $date) {
            $base = Carbon::parse($date ?? $a->work_date);
            $s = Carbon::parse($base->toDateString().' '.$start);
            $e = Carbon::parse($base->toDateString().' '.$end);
            $a->breaks()->create([
                'start_time' => $s,
                'end_time'   => $e,
                'minutes'    => $s->diffInMinutes($e),
            ]);
        });
    }
}
