<?php

namespace Database\Factories;

use App\Models\AttendanceRequest;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<\App\Models\AttendanceRequest> */
class AttendanceRequestFactory extends Factory
{
    protected $model = AttendanceRequest::class;

    public function definition(): array
    {
        // 申請対象の勤怠を生成
        $attendance = Attendance::factory()->create();

        $type = \defined(AttendanceRequest::class.'::TYPE_ATTENDANCE_CORRECTION')
            ? AttendanceRequest::TYPE_ATTENDANCE_CORRECTION
            : 'adjust';

        $pending = \defined(AttendanceRequest::class.'::STATUS_PENDING')
            ? AttendanceRequest::STATUS_PENDING
            : 'pending';

        $wd = $attendance->work_date instanceof \DateTimeInterface
            ? $attendance->work_date->format('Y-m-d')
            : (string) $attendance->work_date;

        return [
            'attendance_id' => $attendance->id,
            'user_id'       => $attendance->user_id,
            'type'          => $type,
            'status'        => $pending,
            'target_date'   => $wd,
            'reason'        => $this->faker->sentence(),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ];
    }

    /** 状態: 承認待ち */
    public function pending(): static
    {
        $pending = \defined(AttendanceRequest::class.'::STATUS_PENDING')
            ? AttendanceRequest::STATUS_PENDING : 'pending';

        return $this->state(fn () => ['status' => $pending]);
    }

    /** 状態: 承認済み */
    public function approved(): static
    {
        $approved = \defined(AttendanceRequest::class.'::STATUS_APPROVED')
            ? AttendanceRequest::STATUS_APPROVED : 'approved';

        return $this->state(fn () => ['status' => $approved]);
    }

    /** 状態: 却下 */
    public function rejected(): static
    {
        $rejected = \defined(AttendanceRequest::class.'::STATUS_REJECTED')
            ? AttendanceRequest::STATUS_REJECTED : 'rejected';

        return $this->state(fn () => ['status' => $rejected]);
    }
}
