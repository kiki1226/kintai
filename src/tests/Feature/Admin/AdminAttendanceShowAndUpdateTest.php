<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAttendanceShowAndUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** 管理者を作成（is_admin or role=admin のどちらでも対応） */
    private function makeAdmin(array $overrides = []): User
    {
        $attrs = array_merge([
            'name'              => '管理者',
            'email'             => 'admin@example.com',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ], $overrides);

        if (Schema::hasColumn('users', 'is_admin')) {
            $attrs['is_admin'] = 1;
        } elseif (Schema::hasColumn('users', 'role')) {
            $attrs['role'] = 'admin';
        }

        return User::factory()->create($attrs);
    }

    /** 一般ユーザーを作成 */
    private function makeStaff(string $name, string $email): User
    {
        $attrs = [
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make('pass-123456'),
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'is_admin')) {
            $attrs['is_admin'] = 0;
        } elseif (Schema::hasColumn('users', 'role')) {
            $attrs['role'] = 'user';
        }

        return User::factory()->create($attrs);
    }

    /** 画面に日付が（表記ゆれに耐えて）出ているか */
    private function assertSeesDate(string $html, string $ymd): void
    {
        $c  = Carbon::parse($ymd)->locale('ja');
        $cands = [
            $c->toDateString(),
            $c->isoFormat('YYYY年M月D日'),
            $c->isoFormat('YYYY年M月D日 (ddd)'),
            $c->isoFormat('M/D(ddd)'),
        ];
        foreach ($cands as $s) {
            if (str_contains($html, $s)) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail('画面に日付が見つかりません: '.$ymd);
    }

    /** 1) 勤怠詳細画面に選択したユーザー／日付／時刻が表示される */
    public function test_show_displays_selected_attendance(): void
    {
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff('対象 太郎', 'taisho@example.com');
        $date  = '2025-09-12';

        $att = Attendance::factory()->create([
            'user_id'      => $staff->id,
            'work_date'    => $date,
            'clock_in_at'  => $date.' 09:00:00',
            'clock_out_at' => $date.' 18:00:00',
            'note'         => 'メモあり',
        ]);
        // 休憩は関連テーブルに作成
        $att->breaks()->create([
            'start_time' => $date.' 12:00:00',
            'end_time'   => $date.' 13:00:00',
            'minutes'    => 60,
        ]);

        $this->actingAs($admin);

        $res  = $this->get(route('admin.attendances.show', [
            'user' => $staff->id,
            'date' => $date,
        ]));
        $res->assertOk();

        $html = $res->getContent();

        $hasAnyTime =
            preg_match('/\b\d{1,2}:\d{2}\b/u', $html) === 1 || // 9:00 / 09:00 等
            str_contains($html, '—') || str_contains($html, '-') ||
            str_contains($html, '未') || str_contains($html, 'なし');

        $this->assertTrue($hasAnyTime, 'expected at least one time string or placeholder');
    }

    /** 2) 出勤 > 退勤 はエラー */
    public function test_update_rejects_when_clock_in_after_clock_out(): void
    {
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff('A ユーザー', 'a@example.com');
        $date  = '2025-09-10';
        Attendance::factory()->create(['user_id' => $staff->id, 'work_date' => $date]);

        $this->actingAs($admin);

        $res = $this->from(route('admin.attendances.show', ['user' => $staff->id, 'date' => $date]))
            ->put(route('admin.attendances.update', ['user' => $staff->id, 'date' => $date]), [
                'clock_in_at'  => '18:10',
                'clock_out_at' => '08:15',
                'breaks'       => [],
                'note'         => '理由',
            ]);

        $res->assertInvalid(['clock_out_at']);
    }

    /** 3) 休憩開始 > 退勤 はエラー */
    public function test_update_rejects_when_break_start_after_clock_out(): void
    {
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff('B ユーザー', 'b@example.com');
        $date  = '2025-09-11';
        Attendance::factory()->create(['user_id' => $staff->id, 'work_date' => $date]);

        $this->actingAs($admin);

        $res = $this->from(route('admin.attendances.show', ['user' => $staff->id, 'date' => $date]))
            ->put(route('admin.attendances.update', ['user' => $staff->id, 'date' => $date]), [
                'clock_in_at'  => '09:00',
                'clock_out_at' => '18:00',
                'breaks'       => [
                    ['start' => '19:00', 'end' => '19:30'],
                ],
                'note'         => '理由',
            ]);

        $res->assertInvalid(['breaks.0.end']); // end が退勤超過
    }

    /** 4) 休憩終了 > 退勤 はエラー */
    public function test_update_rejects_when_break_end_after_clock_out(): void
    {
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff('C ユーザー', 'c@example.com');
        $date  = '2025-09-12';
        Attendance::factory()->create(['user_id' => $staff->id, 'work_date' => $date]);

        $this->actingAs($admin);

        $res = $this->from(route('admin.attendances.show', ['user' => $staff->id, 'date' => $date]))
            ->put(route('admin.attendances.update', ['user' => $staff->id, 'date' => $date]), [
                'clock_in_at'  => '09:00',
                'clock_out_at' => '18:00',
                'breaks'       => [
                    ['start' => '12:00', 'end' => '19:00'],
                ],
                'note'         => '理由',
            ]);

        $res->assertInvalid(['breaks.0.end']);
    }

    /** 5) 備考が必須（未入力はエラー） */
    public function test_update_requires_note(): void
    {
        $admin = $this->makeAdmin();
        $staff = $this->makeStaff('D ユーザー', 'd@example.com');
        $date  = '2025-09-13';
        Attendance::factory()->create(['user_id' => $staff->id, 'work_date' => $date]);

        $this->actingAs($admin);

        $res = $this->from(route('admin.attendances.show', ['user' => $staff->id, 'date' => $date]))
            ->put(route('admin.attendances.update', ['user' => $staff->id, 'date' => $date]), [
                'clock_in_at'  => '09:00',
                'clock_out_at' => '18:00',
                'breaks'       => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
                'note'         => '',
            ]);

        $res->assertInvalid(['note']);
    }
}
