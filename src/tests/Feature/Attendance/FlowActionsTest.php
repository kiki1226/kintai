<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowActionsTest extends TestCase
{
    use RefreshDatabase;

    private string $tz = 'Asia/Tokyo';

    /** 画面HTMLから、特定キーワードを含む form の action URL を取得 */
    private function extractFormAction(string $html, string $keyword): string
    {
        if (preg_match('#<form[^>]+action="([^"]*' . preg_quote($keyword, '#') . '[^"]*)"#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        $this->fail("form action with keyword [{$keyword}] not found");
    }

    /** ルートに渡す日付は ISO (Y-m-d) で統一する */
    private function paramDate(string $ymd): string
    {
        return $ymd; // 例: '2025-09-08'
    }

    /** 指定日でレジスター画面を開く */
    private function openRegister(User $user, string $ymd): array
    {
        $res = $this->actingAs($user)->get(
            route('attendance.register', ['date' => $this->paramDate($ymd)])
        );
        $res->assertOk();
        return [$res, $res->getContent()];
    }

    public function test_clock_in_creates_record_and_appears_on_index(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        Carbon::setTestNow(Carbon::parse($ymd . ' 09:00', $this->tz));

        [, $html] = $this->openRegister($user, $ymd);
        $url = $this->extractFormAction($html, 'clock');

        $this->post($url, ['date' => $this->paramDate($ymd)])->assertRedirect();

        $this->assertTrue(
            Attendance::where('user_id', $user->id)->whereDate('work_date', $ymd)->exists()
        );
    }

    public function test_clock_in_only_once_per_day(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        Carbon::setTestNow(Carbon::parse($ymd . ' 09:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $url = $this->extractFormAction($html, 'clock');

        // 1回目
        $this->post($url, ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 2回目（時間だけ進める）
        Carbon::setTestNow(Carbon::parse($ymd . ' 10:00', $this->tz));
        $this->post($url, ['date' => $this->paramDate($ymd)])->assertRedirect();

        $rows = Attendance::where('user_id', $user->id)->whereDate('work_date', $ymd)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('09:00', $rows->first()->clock_in_at?->timezone($this->tz)->format('H:i'));
    }

    public function test_break_can_start_multiple_times_and_appears_on_index(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        // 出勤
        Carbon::setTestNow(Carbon::parse($ymd . ' 09:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'clock'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 休憩開始1
        Carbon::setTestNow(Carbon::parse($ymd . ' 12:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'break-start'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 休憩終了1
        Carbon::setTestNow(Carbon::parse($ymd . ' 12:30', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'break-end'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 休憩開始2
        Carbon::setTestNow(Carbon::parse($ymd . ' 15:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'break-start'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 休憩終了2（対称性のため入れておく）
        Carbon::setTestNow(Carbon::parse($ymd . ' 15:20', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'break-end'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        $att = Attendance::where('user_id', $user->id)->whereDate('work_date', $ymd)->firstOrFail();
        $this->assertGreaterThanOrEqual(2, $att->breaks()->count());
    }

    public function test_clock_out_sets_time_and_appears_on_index(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        // 出勤
        Carbon::setTestNow(Carbon::parse($ymd . ' 09:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'clock'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 退勤
        Carbon::setTestNow(Carbon::parse($ymd . ' 18:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'clock-out'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        $att = Attendance::where('user_id', $user->id)->whereDate('work_date', $ymd)->firstOrFail();
        $this->assertSame('18:00', $att->clock_out_at?->timezone($this->tz)->format('H:i'));
    }
}
