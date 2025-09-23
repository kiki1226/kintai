@extends('layouts.app')
@section('title','勤怠登録')

@section('css')
  {{-- 既存の attendance.css を流用（ボタン等の見た目） --}}
  <link rel="stylesheet" href="{{ asset('css/attendance.register.css') }}?v={{ filemtime(public_path('css/attendance.register.css')) }}">
@endsection

@section('content')
<div class="container">
  <!-- DEBUG STATE: {{ $state }} -->
  <div class="punch-wrap"> 
    <div class="today-card">
      <div class="state" data-state="{{ $state }}">
        @if ($state === 'before_clock_in')
          勤務外
        @elseif ($state === 'after_clock_in')
          出勤中
        @elseif ($state === 'on_break')
          休憩中
        @elseif ($state === 'after_clock_out')
          退勤済
        @endif
      </div>

      {{-- 日付・現在時刻 --}}
      <div class="clock-panel">
        <div class="clock-date">
          {{ now()->isoFormat('YYYY年M月D日(ddd)') }}
        </div>
        <div id="clockTime" class="clock-time">
          {{ now()->format('H:i') }}
        </div>

        {{-- ▼アクションボタン --}}
        <div class="punch-actions">
          @if ($state === 'before_clock_in')
            {{-- 出勤前 → 出勤ボタン --}}
            <form id="clock-in-form" method="POST" action="{{ route('attendance.clock_in') }}">
              @csrf
              <input type="hidden" name="date" value="{{ $day }}">
              <button type="submit" id="clock-in-btn" class="punch-btn punch-btn--main punch-btn--danger">
                出　勤
              </button>
            </form>

          @elseif ($state === 'after_clock_in')
            {{-- 出勤後 → 退勤・休憩入 --}}
            <form method="POST" action="{{ route('attendance.clock_out') }}" style="display:inline">
              @csrf
              <input type="hidden" name="date" value="{{ $day }}">
              <button type="submit" class="punch-btn punch-btn--sm punch-btn--danger">退　勤</button>
            </form>
            <form method="POST" action="{{ route('attendance.break_start', ['attendance' => $attendance->id]) }}">
              @csrf
              <button type="submit" class="punch-btn punch-btn--sm punch-btn--primary">休憩入</button>
            </form>

          @elseif ($state === 'on_break')
            {{-- 休憩中 → 休憩戻 --}}
            <form method="POST" action="{{ route('attendance.break_end', ['attendance' => $attendance->id]) }}">
              @csrf
              <button type="submit" class="punch-btn punch-btn--main punch-btn--primary">休憩戻</button>
            </form>

          @elseif ($state === 'after_clock_out')
            {{-- 退勤後 --}}
            <div class="end">お疲れ様でした。</div>
          @endif
        </div>

        {{-- 補助メッセージ --}}
        @if ($state === 'after_clock_in')
          <div class="hint">現在は勤務中です。必要に応じて「休憩入」または「退勤」を押してください。</div>
        @elseif ($state === 'on_break')
          <div class="hint">休憩中です。再開する場合は「休憩戻」を押してください。</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('script')
<script>
  // 画面上部の時刻を1秒ごとに更新（見た目用）
  const el = document.getElementById('clockTime');
  if (el) {
    setInterval(() => {
      const d = new Date();
      const hh = String(d.getHours()).padStart(2,'0');
      const mm = String(d.getMinutes()).padStart(2,'0');
      if (el.textContent !== `${hh}:${mm}`) el.textContent = `${hh}:${mm}`;
    }, 1000);
  }
</script>
@endsection
