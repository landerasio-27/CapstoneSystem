{{--
    Forgot password, step one: the account's email.

    What is written here is the address the code is mailed to — so it is the
    account's own address that has to be given, not another one the office may
    read. The step only asks for it: nothing is changed until a code has been
    mailed to that address and brought back (see PasswordResetController).
--}}
@extends('auth.layout')

@section('page-title', 'Forgot password — ISU Supply Account')
@section('skip-target', '#email')
@section('title', 'Forgot your password?')
@section('subtitle', 'Write the email of your account and we will mail a six-digit code to it.')

@section('form')
    <form class="login-form" method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <label class="field-label" for="email">Email Address</label>
        <div class="field">
            <svg class="ficon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="m22 6-10 7L2 6"/></svg>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="username" inputmode="email" spellcheck="false"
                   placeholder="Enter your university email"
                   aria-describedby="email-error" @error('email') aria-invalid="true" @enderror>
        </div>
        @error('email')
            <p class="field-err" id="email-error">{{ $message }}</p>
        @else
            <p class="field-err" id="email-error" hidden></p>
        @enderror

        <button type="submit" class="btn-login">Send the code</button>
    </form>

    <p class="forgot-note">
        The code goes to the email on the account itself — the same address you sign in with.
        <a href="{{ route('login') }}">Back to sign in</a>
    </p>
@endsection
