@extends('errors::minimal')

@section('title', 'انتهت صلاحية الصفحة')
@section('code', '419')
@section('message', 'بقيت الصفحة مفتوحة مدة طويلة. أعد تحميلها ثم حاول مرة أخرى.')
@section('primary')
    <a class="primary" href="javascript:location.reload()">إعادة تحميل الصفحة</a>
@endsection
