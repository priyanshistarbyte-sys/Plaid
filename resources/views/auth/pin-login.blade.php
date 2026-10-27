@extends('layouts.guest')
@section('page-title', 'Log in')

@push('styles')
<style>
    .pin-dots { display: flex; justify-content: center; gap: 14px; margin: 4px 0 8px; }
    .pin-dot {
        width: 14px; height: 14px; border-radius: 50%;
        border: 2px solid var(--border); background: transparent; transition: all .15s;
    }
    .pin-dot.filled { background: var(--purple); border-color: var(--purple); }
    .pin-dots.shake { animation: shake .35s; }
    .pin-dots.shake .pin-dot { border-color: var(--red); }
    @keyframes shake {
        25% { transform: translateX(-6px); } 50% { transform: translateX(6px); } 75% { transform: translateX(-3px); }
    }
    .pin-error { text-align: center; min-height: 18px; margin-bottom: 10px; }

    .pin-pad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px; }
    .pin-key {
        height: 54px; border-radius: 10px; cursor: pointer;
        background: var(--input-bg); border: 1px solid var(--border); color: var(--text-strong);
        font-size: 20px; font-weight: 600; font-family: inherit; transition: all .12s;
    }
    .pin-key:hover { border-color: var(--purple); }
    .pin-key:active { background: var(--purple); color: #fff; transform: scale(.97); }
    .pin-key.muted { color: var(--text-muted); font-size: 18px; }
    .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
</style>
@endpush

@section('content')
<div class="auth-header">
    <div class="auth-logo"><i class="bi bi-bank2"></i></div>
    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-subtitle">Enter your PIN to sign in</p>
</div>

<form method="POST" action="{{ url('/login') }}" id="pinForm" autocomplete="off">
    @csrf
    <input type="hidden" name="pin" id="pin">

    <div class="pin-dots @error('pin') shake @enderror" id="pinDots" aria-hidden="true">
        @for ($i = 0; $i < 6; $i++)<span class="pin-dot"></span>@endfor
    </div>

    <div class="pin-error" role="alert">
        @error('pin')<span class="error-message">{{ $message }}</span>@enderror
    </div>

    <div class="pin-pad">
        @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
            <button type="button" class="pin-key" data-digit="{{ $digit }}">{{ $digit }}</button>
        @endforeach
        <button type="button" class="pin-key muted" data-action="clear" title="Clear" aria-label="Clear"><i class="bi bi-x-lg"></i></button>
        <button type="button" class="pin-key" data-digit="0">0</button>
        <button type="button" class="pin-key muted" data-action="back" title="Delete" aria-label="Delete last digit"><i class="bi bi-backspace"></i></button>
    </div>

    <button type="submit" class="btn-primary" id="pinSubmit" disabled>
        <i class="bi bi-unlock"></i> Unlock
    </button>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        const MIN = 4, MAX = 6;
        const form = document.getElementById('pinForm');
        const input = document.getElementById('pin');
        const dots = document.querySelectorAll('.pin-dot');
        const submit = document.getElementById('pinSubmit');
        let pin = '';
        let submitting = false;

        function render() {
            dots.forEach((dot, i) => dot.classList.toggle('filled', i < pin.length));
            submit.disabled = submitting || pin.length < MIN;
            input.value = pin;
        }

        // Send the form exactly once. A second POST (Enter or Unlock after the
        // 6-digit auto-submit) would carry a token the first login already rotated.
        function send() {
            if (submitting || pin.length < MIN) return;
            submitting = true;
            render();
            submit.innerHTML = '<i class="bi bi-hourglass-split"></i> Signing in…';
            form.submit();
        }

        function press(digit) {
            if (submitting || pin.length >= MAX) return;
            document.getElementById('pinDots').classList.remove('shake');
            pin += digit;
            render();
            if (pin.length === MAX) send();
        }

        document.querySelector('.pin-pad').addEventListener('click', function (e) {
            const key = e.target.closest('.pin-key');
            if (!key || submitting) return;
            if (key.dataset.digit !== undefined) press(key.dataset.digit);
            else if (key.dataset.action === 'back') { pin = pin.slice(0, -1); render(); }
            else { pin = ''; render(); }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            send();
        });

        // Typing on a keyboard works too.
        document.addEventListener('keydown', function (e) {
            if (submitting) { e.preventDefault(); return; }
            if (/^\d$/.test(e.key)) press(e.key);
            else if (e.key === 'Backspace') { pin = pin.slice(0, -1); render(); }
            else if (e.key === 'Escape') { pin = ''; render(); }
            else if (e.key === 'Enter') { e.preventDefault(); send(); }
        });

        // Coming back with the browser's Back button restores this page from cache
        // with a stale token — reload it so the next submit has a fresh one.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) window.location.reload();
        });

        render();
    })();
</script>
@endpush
