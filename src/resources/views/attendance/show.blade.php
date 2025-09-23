{{-- resources/views/attendance/show.blade.php --}}
@extends('layouts.app')
@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.show.css') }}">
@endsection

@section('content')
<div class="detail-card">
  <div class="alert-success">
    <div class="alert-space">　</div>
    <h2 class="detail-title">
      勤怠詳細{!! $statusLabel ? '（'.$statusLabel.'）' : '' !!}
    </h2>
  </div>

  <div class="detail-content">
    {{-- 名前 --}}
    <div class="detail-row">
      <div class="label">名前</div>
      <div class="value">
        {{ $attendance->user?->name ?? auth()->user()->name ?? '—' }}
      </div>
    </div>

    {{-- 日付 --}}
    <div class="detail-row">
      <div class="label">日付</div>
      <div class="date-split">
        <span class="date-y">{{ $dateY }}</span>
        <span class="date-md">{{ $dateMD }}</span>
      </div>
    </div>

    {{-- 出勤・退勤 --}}
    <div class="detail-row">
      <div class="label">出勤・退勤</div>
      <div class="break-time">
        <span class="date-syu">{{ $clockIn }}</span>
        <span class="date-kara">〜</span>
        <span class="date-tai">{{ $clockOut }}</span>
      </div>
    </div>

    {{-- 休憩（複数行） --}}
    @forelse ($breaks as $i => $br)
      <div class="detail-row">
        <div class="label">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</div>
        <div class="break-time">
          <span class="date-syu">{{ $br['start'] }}</span>
          <span class="date-kara">〜</span>
          <span class="date-tai">{{ $br['end'] }}</span>
        </div>
      </div>
    @empty
      <div class="detail-row">
        <div class="label">休憩</div>
        <div class="break-time">休憩はありません</div>
      </div>
    @endforelse

    {{-- 備考 --}}
    <div class="detail-row">
      <div class="label">備考</div>
      <span class="date-biko">{{ $attendance->note }}</span>
    </div>
  </div>

  {{-- フッター（申請状態） --}}
  <div class="detail-actions">
    @if ($status === 'pending')
      <span class="btn">＊承認済みのため修正できません</span>
    @elseif ($status === 'approved')
      <div class="btn">＊承認済みのため修正できません</div>
    @elseif ($status === 'rejected')
      <div class="btn">＊却下済みのため修正できません</div>
    @else
      {{-- 未申請なら何も出さない（画面遷移的には通常来ない想定） --}}
    @endif
  </div>
</div>
@endsection
