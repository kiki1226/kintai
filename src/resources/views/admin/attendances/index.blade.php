@extends('layouts.admin')
<link rel="stylesheet"
      href="{{ asset('css/admin.attendance.index.css') }}?v={{ filemtime(public_path('css/admin.attendance.index.css')) }}">

@section('title','勤怠一覧（管理者）')

@section('content')
<div class="container">
  <div class="alert-success">
    <div class="alert-space">　</div>
    {{-- ① タイトルも $target --}}
    <h1 class="page-title">{{ $target->format('Y年n月j日') }}の勤怠</h1>
  </div>

  <div class="att-index__monthnav">
    {{-- 前日 --}}
    <a class="btn-ghost" href="{{ route('admin.attendances.index', ['date' => $prevDate]) }}">
      <img src="{{ asset('products/image1.png') }}" alt="←">
      <span>前日</span>
    </a>

    {{-- 日見出し --}}
    <div class="att-index__month">
      <img src="{{ asset('products/calendar.png') }}" alt="カレンダー">
      {{ $target->isoFormat('YYYY年M月D日') }}
    </div>

    {{-- 翌日 --}}
    <a class="btn-ghost" href="{{ route('admin.attendances.index', ['date' => $nextDate]) }}">
      <span>翌日</span>
      <img src="{{ asset('products/image2.png') }}" alt="→" class="btn-ghost-img">
    </a>
  </div>

  {{-- ③ テーブル --}}
  <div class="card" style="padding:0;">
    <table class="table">
      <thead class="thead">
        <tr>
          <th class="text-right">名前</th>
          <th class="text-right">出勤</th>
          <th class="text-right">退勤</th>
          <th class="text-right">休憩</th>
          <th class="text-right">合計</th>
          <th class="text-right">詳細</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $r)
          <tr>
            <td class="text-right">{{ $r['name'] }}</td>
            <td class="text-right">{{ $r['start'] }}</td>
            <td class="text-right">{{ $r['end'] }}</td>
            <td class="text-right">{{ $r['break'] }}</td>
            <td class="text-right">{{ $r['total'] }}</td>
            {{-- 詳細ボタン --}}
            <td class="text-right">
              <a class="btn btn-sm btn-outline"
                href="{{ route('admin.attendances.show', ['user'=>$r['id'], 'date'=>$target->toDateString()]) }}">
                詳細
              </a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form  = document.getElementById('dateForm');
  const input = document.getElementById('nav-date');

  // flatpickr を使っている場合（見た目は 2025/09/03、送信値は 2025-09-03）
  if (window.flatpickr) {
    flatpickr(input, {
      locale: 'ja',
      dateFormat: 'Y-m-d',   // ← サーバへ送る値
      altInput: true,
      altFormat: 'Y/m/d',    // ← 画面に見せる値
      defaultDate: input.value,
      onChange: () => form.submit()
    });
  } else {
    // プレーン input の場合も change で自動送信
    input.addEventListener('change', () => form.submit());
  }
});
</script>
@endpush