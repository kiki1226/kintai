<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterScreenTest extends TestCase
{
    use RefreshDatabase;

    private string $tz = 'Asia/Tokyo';

    private function extractFormAction(string $html, string $keyword): string
    {
        if (preg_match('#<form[^>]+action="([^"]*' . preg_quote($keyword, '#') . '[^"]*)"#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        $this->fail("form action with keyword [{$keyword}] not found");
    }

    private function paramDate(string $ymd): string
    {
        return $ymd; // ISO で渡す
    }

    private function openRegister(User $user, string $ymd)
    {
        $res = $this->actingAs($user)->get(route('attendance.register', ['date' => $this->paramDate($ymd)]));
        $res->assertOk();
        return [$res, $res->getContent()];
    }

    public function test_state_is_before_clock_in(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        Carbon::setTestNow(Carbon::parse($ymd . ' 08:30', $this->tz));
        [$res] = $this->openRegister($user, $ymd);

        $this->assertMatchesRegularExpression('/data-state=[\'"]before_clock_in[\'"]/', $res->getContent());
    }

    public function test_state_is_after_clock_in_when_clocked_in(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        Carbon::setTestNow(Carbon::parse($ymd . ' 09:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'clock'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        [$res] = $this->openRegister($user, $ymd);
        $this->assertMatchesRegularExpression('/data-state=[\'"]after_clock_in[\'"]/', $res->getContent());
    }

    public function test_state_is_on_break_when_break_started(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        // 出勤
        Carbon::setTestNow(Carbon::parse($ymd . ' 09:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'clock'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 休憩開始
        Carbon::setTestNow(Carbon::parse($ymd . ' 12:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'break-start'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        [$res] = $this->openRegister($user, $ymd);
        $this->assertMatchesRegularExpression('/data-state=[\'"]on_break[\'"]/', $res->getContent());
    }

    public function test_state_is_after_clock_out_when_clocked_out(): void
    {
        $user = User::factory()->create();
        $ymd  = '2025-09-08';

        // 出勤
        Carbon::setTestNow(Carbon::parse($ymd . ' 09:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'clock'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 休憩開始→終了
        Carbon::setTestNow(Carbon::parse($ymd . ' 12:00', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'break-start'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        Carbon::setTestNow(Carbon::parse($ymd . ' 12:30', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'break-end'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        // 退勤
        Carbon::setTestNow(Carbon::parse($ymd . ' 18:30', $this->tz));
        [, $html] = $this->openRegister($user, $ymd);
        $this->post($this->extractFormAction($html, 'clock-out'), ['date' => $this->paramDate($ymd)])->assertRedirect();

        [$res] = $this->openRegister($user, $ymd);
        $this->assertMatchesRegularExpression('/data-state=[\'"]after_clock_out[\'"]/', $res->getContent());
        $res->assertSee('お疲れ様でした');
    }
}
