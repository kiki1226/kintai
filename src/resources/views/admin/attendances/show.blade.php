@extends('layouts.admin')
<link rel="stylesheet" href="{{ asset('css/admin.attendance.show.css') }}?v={{ filemtime(public_path('css/admin.attendance.show.css')) }}">

@section('title','勤怠詳細（管理者）')

@section('content')
<div class="container show">
  <div class="alert-success">
    <div class="alert-space">　</div>
    <h1 class="page-title">勤怠詳細</h1>
  </div>
    @if ($errors->any())
      <div class="alert alert-danger" style="margin-bottom:16px">
        <ul style="margin:0;padding-left:18px">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

   <div class="card show-card">
    <form method="POST" action="{{ route('admin.attendances.update', ['user'=>$user->id,'date'=>$target->toDateString()]) }}">
      @csrf
      @method('PUT')

      {{-- 名前 --}}
      <div class="row">
        <h6>名前</h6>
        <div class="chip-name">{{ $user->name }}</div>
      </div>

      {{-- 日付 --}}
      <div class="row">
        <h6>日付</h6>
        <div class="chip-year">
          <div class="year">{{ $target->format('Y年') }}</div>
          <div class="day">{{ $target->format('n月j日') }}</div>
        </div>
      </div>

      {{-- 出勤・退勤 --}}
      <div class="row">
        <h6>出勤・退勤</h6>
        <div class="field">
          <div class="pair">
            <input class="time-input"
                type="{{ old('clock_in_at', optional($attendance)->clock_in_at) ? 'time' : 'text' }}"
                name="clock_in_at"
                value="{{ old('clock_in_at', optional($attendance?->clock_in_at)->format('H:i')) }}"
                placeholder="--:--"
                onfocus="this.type='time'; this.select(); this.style.color='black';"
                onblur="if(!this.value) this.type='text';">

            <span class="time-separator">〜</span>

            <input class="time-input"
                type="{{ old('clock_out_at', optional($attendance)->clock_out_at) ? 'time' : 'text' }}"
                name="clock_out_at"
                value="{{ old('clock_out_at', optional($attendance?->clock_out_at)->format('H:i')) }}"
                placeholder="--:--"
                onfocus="this.type='time'; this.select(); this.style.color='black';"
                onblur="if(!this.value) this.type='text';">
          </div>
          <div class="errors">
            @error('clock_in_at')  <p class="error">{{ $message }}</p> @enderror
            @error('clock_out_at') <p class="error">{{ $message }}</p> @enderror
          </div>
        </div>
      </div>

      {{-- 休憩 --}}
      <div id="breaks-container">
        @forelse($attendance->breaks as $i => $break)
          <div class="row break-row">
            <h6>{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</h6>
            <div class="field">
              <div class="pair">
                <input class="time-input break-start"
                      type="time"
                      name="breaks[{{ $i }}][start]"
                      value="{{ old("breaks.$i.start", optional($break->start_time)->format('H:i')) }}">
                <span class="time-separator">〜</span>
                <input class="time-input break-end"
                      type="time"
                      name="breaks[{{ $i }}][end]"
                      value="{{ old("breaks.$i.end", optional($break->end_time)->format('H:i')) }}">
              </div>
              <div class="errors">
                @error("breaks.$i.start") <p class="error">{{ $message }}</p> @enderror
                @error("breaks.$i.end")   <p class="error">{{ $message }}</p> @enderror
              </div>
            </div>
          </div>
        @empty
          <div class="row break-row">
            <h6>休憩</h6>
            <div class="field">
              <div class="pair">
                <input class="time-input break-start" type="time" name="breaks[0][start]">
                <span class="time-separator">〜</span>
                <input class="time-input break-end" type="time" name="breaks[0][end]">
              </div>
            </div>
          </div>
        @endforelse
      </div>

      {{-- 備考 --}}
      <div class="row">
        <h6>備考</h6>
        <div class="field">
          <textarea name="note" class="note">{{ old('note', $attendance->note ?? '') }}</textarea>
          @error('note') <p class="error">{{ $message }}</p> @enderror
        </div>
      </div>

    </div>
    <div class="actions">
      <button class="btn-primary" type="submit">修正</button>
    </div>
  </form>
</div>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('breaks-container');
  if (!container) return;

  // ====== 休憩行の追加 ======
  function addBreakRow(index) {
    const label = index === 0 ? '休憩' : `休憩${index+1}`;
    const row = document.createElement('div');
    row.classList.add('row', 'break-row');
    row.innerHTML = `
      <h6>${label}</h6>
      <div class="field">
        <div class="pair">
          <input class="time-input break-start" type="text" name="breaks[${index}][start]" placeholder="--:--">
          <span class="time-separator">〜</span>
          <input class="time-input break-end"   type="text" name="breaks[${index}][end]"   placeholder="--:--">
        </div>
      </div>
    `;
    container.appendChild(row);

    row.querySelectorAll('input').forEach(input => {
      enableSmartInput(input);
      input.addEventListener('input', checkLastRow);
    });
  }

  function checkLastRow() {
    const rows = container.querySelectorAll('.break-row');
    const last = rows[rows.length - 1];
    const start = last.querySelector('.break-start').value;
    const end   = last.querySelector('.break-end').value;
    if (start || end) addBreakRow(rows.length);
  }

  // ====== 入力UX改善 ======
  function enableSmartInput(input) {
    if (!input.value) { input.type = 'text'; input.placeholder = ''; input.style.color = 'transparent'; }
    input.addEventListener('focus', () => { input.type = 'time'; input.style.color = 'black'; setTimeout(() => input.select(), 10); });
    input.addEventListener('blur',  () => { if (!input.value) { input.type = 'text'; input.placeholder = ''; input.style.color = 'transparent'; } });
  }

  // 既存の休憩入力に適用
  container.querySelectorAll('.break-start, .break-end').forEach(input => {
    enableSmartInput(input);
    input.addEventListener('input', checkLastRow);
  });

  // 出勤・退勤にも適用
  document.querySelectorAll('input[name="clock_in_at"], input[name="clock_out_at"]').forEach(enableSmartInput);

  // ★ 初期表示で「末尾に必ず1行の空欄」を用意
  (function ensureTailBlankRow() {
    const rows = container.querySelectorAll('.break-row');
    if (rows.length === 0) {           // 休憩が1件もない場合
      addBreakRow(0);                   // 1行分の空欄を追加
      return;
    }
    const last  = rows[rows.length - 1];
    const start = last.querySelector('.break-start').value;
    const end   = last.querySelector('.break-end').value;
    if (start || end) {                 // 既存最終行に値が入っているなら
      addBreakRow(rows.length);         // もう1行 空欄を追加
    }
  })();
});
</script>
@endsection
