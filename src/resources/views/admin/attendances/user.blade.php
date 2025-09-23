@extends('layouts.admin')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin.attendance.user.css') }}?v={{ filemtime(public_path('css/admin.attendance.user.css')) }}">
@endsection
@section('title', $user->name.' さんの勤怠（管理）')

@section('content')
<div class="container">
  <div class="alert-success">
    <div class="alert-space">　</div>
    <h1 class="page-title">{{ $user->name }} さんの勤怠（{{ $month->format('Y年n月') }}）</h1>
  </div>
  
  {{-- 月ナビ --}}
  <div class="att-index__monthnav">
    {{-- 前月 --}}
    <a class="btn-ghost" href="{{ route('admin.attendances.user', ['user'=>$user->id, 'month'=>$prevMonth]) }}">
      <img src="{{ asset('products/image1.png') }}" alt="←">
      <span>前月</span>
    </a>

    {{-- 月見出し --}}
    <div class="att-index__month">
      <img src="{{ asset('products/calendar.png') }}" alt="カレンダー">
      {{ $month->isoFormat('YYYY年M月') }}
    </div>

    {{-- 翌月 --}}
    <a class="btn-ghost" href="{{ route('admin.attendances.user', ['user'=>$user->id, 'month'=>$nextMonth]) }}">
      <span>翌月</span>
      <img src="{{ asset('products/image2.png') }}" alt="→" class="btn-ghost-img">
    </a>
  </div>

  {{-- 表 --}}
  <div class="card" style="padding:0;">
    <table class="month-table">
      <thead>
        <tr>
          <th>日付</th>
          <th>出勤</th>
          <th>退勤</th>
          <th>休憩</th>
          <th>合計</th>
          <th class="text-right">詳細</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $r)
          <tr>
            <td>{{ $r['label'] }}</td>
            <td>{{ $r['start'] }}</td>
            <td>{{ $r['end'] }}</td>
            <td>{{ $r['break'] }}</td>
            <td>{{ $r['total'] }}</td>
            <td class="text-right">
              <a class="btn btn-sm btn-outline"
                 href="{{ route('admin.attendances.show', ['user'=>$user->id, 'date'=>$r['date']]) }}">詳細</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="actions">
    <a class="btn btn-dark"
       href="{{ route('admin.attendances.user', ['user'=>$user->id, 'month'=>$month->format('Y-m'), 'export'=>'csv']) }}">
      CSV出力
    </a>
  </div>
</div>
@endsection
@section('script')
<script>
  const m = document.querySelector('.monthform input[type="month"]');
  if (m) {
    m.addEventListener('change', () => {
      // 可能なら requestSubmit()、だめなら submit()
      if (m.form?.requestSubmit) m.form.requestSubmit();
      else m.form?.submit();
    });
  }
</script>
@endsection

