@extends('layouts.app')
@section('title', '勤怠編集')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.edit.css') }}">
@endsection

@section('content')
  <div class="alert-success">
    <div class="alert-space">　</div>
    <h2 class="detail-title">勤怠編集</h2>
  </div>

  {{-- ▼バリデーション・フラッシュメッセージ --}}
  @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if (session('info'))    <div class="alert alert-info">{{ session('info') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('attendance.update', ['attendance' => $attendance->id]) }}">
  @csrf
  @method('PATCH')

    <input type="hidden" name="from" value="{{ request('from', 'attendance') }}">
    <input type="hidden" name="back" value="{{ request('m') }}">

    {{-- テーブル --}}
    <div class="detail-content">
      <div class="detail-row">
        <div class="label">名前</div>
        <div class="value">{{ optional($attendance->user)->name }}</div>
      </div>

      <div class="detail-row">
        <div class="label">日付</div>
        <div class="date-split">
          <span class="date-y">{{ $wd->copy()->locale('ja')->isoFormat('YYYY年') }}</span>
          <span class="date-md">{{ $wd->copy()->locale('ja')->isoFormat('M月D日') }}</span>
        </div>
      </div>

      {{-- 出勤・退勤 --}}
      <div class="detail-row">
        <div class="label">出勤・退勤</div>
        <div>
          <input class="time-input"
              type="time"
              name="clock_in"
              value="{{ old('clock_in', $attendance->clock_in_at?->format('H:i')) }}"
              placeholder=" ">

          <span class="time-separator">〜</span>

          <input class="time-input"
              type="time"
              name="clock_out"
              value="{{ old('clock_out', $attendance->clock_out_at?->format('H:i')) }}"
              placeholder=" ">

          @error('clock_in')  <div class="error">{{ $message }}</div> @enderror
          @error('clock_out') <div class="error">{{ $message }}</div> @enderror
        </div>
      </div>

      {{-- 休憩 --}}
      <div id="breaks-container">
        @foreach($breakInputs as $i => $break)
          <div class="detail-row break-row">
            <div class="label">{{ $i === 0 ? '休憩' : '休憩' . ($i+1) }}</div>
            <div>
              <input class="time-input break-start"
                  type="time"
                  name="breaks[{{ $i }}][start]"
                  value="{{ old("breaks.$i.start", $break['start'] ?? '') }}"
                  placeholder=" ">

              <span class="time-separator">〜</span>

              <input class="time-input break-end"
                  type="time"
                  name="breaks[{{ $i }}][end]"
                  value="{{ old("breaks.$i.end", $break['end'] ?? '') }}"
                  placeholder=" ">

              @error("breaks.$i.start") <div class="error">{{ $message }}</div> @enderror
              @error("breaks.$i.end")   <div class="error">{{ $message }}</div> @enderror
            </div>
          </div>
        @endforeach
      </div>

      {{-- 備考 --}}
      <div class="detail-row">
        <div class="label">備考</div>
        <div>
          <textarea class="note-area" name="note" placeholder="　">{{ old('note', $attendance->note) }}</textarea>
          @error('note') <div class="error">{{ $message }}</div> @enderror
        </div>
      </div>
    </div>

    <div class="actions">
      <button type="submit" class="btn primary">編集</button>
    </div>
    
  </form>
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('breaks-container');
    if (!container) return;

    // --:-- を非表示にする処理
    function applyPlaceholderStyle(input) {
        if (!input.value) input.style.color = 'transparent';
        input.addEventListener('input', () => {
            input.style.color = input.value ? 'black' : 'transparent';
        });
        input.addEventListener('blur', () => {
            if (!input.value) input.style.color = 'transparent';
        });
        input.addEventListener('focus', () => {
            input.style.color = 'black';
        });
    }

    // 新しい休憩行の追加
    function addBreakRow(index) {
        const row = document.createElement('div');
        row.classList.add('detail-row','break-row');
        row.innerHTML = `
          <div class="label">休憩${index + 1}</div>
          <div>
            <input class="time-input break-start" type="time" name="breaks[${index}][start]" step="any">
            <span class="time-separator">〜</span>
            <input class="time-input break-end" type="time" name="breaks[${index}][end]" step="any">
          </div>
        `;
        container.appendChild(row);

        row.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', checkLastRow);
            applyPlaceholderStyle(input);
        });
    }

    // 最後の行を監視して、入力があれば新しい行を追加
    function checkLastRow() {
        const rows = container.querySelectorAll('.break-row');
        const lastRow = rows[rows.length - 1];
        const start = lastRow.querySelector('.break-start').value;
        const end   = lastRow.querySelector('.break-end').value;
        if (start || end) {
            addBreakRow(rows.length);
        }
    }

    // 既存の休憩欄に適用
    container.querySelectorAll('.break-start, .break-end').forEach(input => {
        input.addEventListener('input', checkLastRow);
        applyPlaceholderStyle(input);
    });

    // 出勤・退勤欄にも適用
    document.querySelectorAll('input[name="clock_in"], input[name="clock_out"]').forEach(input => {
        applyPlaceholderStyle(input);
    });
});
</script>
@endsection
