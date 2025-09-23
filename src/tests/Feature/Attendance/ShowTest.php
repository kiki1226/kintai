<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private string $tz = 'Asia/Tokyo';

    public function test_detail_page_shows_name_date_and_times_correctly(): void
    {
        $user = User::factory()->create(['name' => '山田花子']);

        $day  = Carbon::parse('2025-09-08', $this->tz);
        $in   = Carbon::parse('2025-09-08 09:00', $this->tz);
        $out  = Carbon::parse('2025-09-08 18:15', $this->tz);
        $bIn  = Carbon::parse('2025-09-08 12:00', $this->tz);
        $bOut = Carbon::parse('2025-09-08 12:45', $this->tz);

        $attendance = Attendance::create([
            'user_id'      => $user->id,
            'work_date'    => $day->toDateString(),
            'clock_in_at'  => $in,
            'clock_out_at' => $out,
            'note'         => 'テスト用',
        ]);
        $attendance->breaks()->create([
            'start_time' => $bIn,
            'end_time'   => $bOut,
            'minutes'    => $bIn->diffInMinutes($bOut),
        ]);

        $this->actingAs($user);

        $res = $this->get(route('attendance.show', ['attendance' => $attendance->id]));

        $res->assertOk()
            ->assertSeeText('山田花子')
            ->assertSee((string)$day->year.'年')
            ->assertSee($day->format('n月j日'))
            ->assertSee($in->format('H:i'))
            ->assertSee($out->format('H:i'))
            ->assertSee($bIn->format('H:i'))
            ->assertSee($bOut->format('H:i'));
    }
}
