{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', '勤怠管理アプリ')</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
  @yield('css')
</head>

<body>
  <header class="site-header">
    <div class="header-inner">
      {{-- ロゴ（未ログインは /、ログイン済は権限別のトップ） --}}
      <a class="brand" href="{{ auth()->check()
        ? (auth()->user()->can('manage') ? route('admin.attendances.index') : route('attendance.index'))
        : url('/') }}">
        <h1 class="header-title">
          <img src="{{ asset('products/logo.svg') }}" alt="COACHTECH">
        </h1>
      </a>

      {{-- ナビゲーション --}}
    @auth
      @php
        $user = auth()->user();  // ← これを最初に追加！
        $onVerifyRoute = request()->routeIs('verification.*');

        // 今日の勤怠を取得
        $today = \Carbon\Carbon::today()->toDateString();
        $todayAttendance = \App\Models\Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->latest('id')
            ->first();
        $hasClockOut = $todayAttendance && $todayAttendance->clock_out_at;
      @endphp

      <nav class="header-nav">
          @if(!($onVerifyRoute && $user && method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()))
            {{-- 通常メニューのみ表示 --}}
            @if($hasClockOut)
              {{-- 退勤後 --}}
              <a href="{{ route('attendance.today') }}">今日の出勤一覧</a>
            @else
              {{-- 出勤前/勤務中 --}}
              <a href="{{ route('attendance.register') }}">勤怠</a>
              <a href="{{ route('attendance.index') }}">勤怠一覧</a>
            @endif

            @can('manage')
              <a href="{{ route('admin.users.index') }}">スタッフ一覧</a>
              <a href="{{ route('admin.requests.index') }}">申請</a>
            @else
              <a href="{{ route('requests.index') }}">申請</a>
            @endcan

            {{-- ログアウトボタン --}}
            <form method="POST" action="{{ route('logout') }}" class="logout-form">
              @csrf
              <button type="submit" class="linklike">ログアウト</button>
            </form>
          @endif
        </nav>
      @endauth
    </div>
  </header>

  <main class="wrap">
    <div class="card">
      @yield('content')
      @if (session('success'))
      <div class="alert alert-success" style="margin:12px 0; padding:10px; background:#e6ffed; border:1px solid #b7f5c4;">
        {{ session('success') }}
      </div>
      @endif
    </div>
  </main>

  @yield('script')
  @yield('js')
</body>
</html>
