@extends('errors::minimal')

@section('title', 'يلزم تسجيل الدخول')
@section('code', '401')
@section('message', 'سجّل دخولك ثم حاول مرة أخرى.')
@section('primary')
    <a class="primary" href="{{ url('/login') }}">تسجيل الدخول</a>
@endsection
