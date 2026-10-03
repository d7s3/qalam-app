@extends('errors::minimal')

@section('title', 'لا تملك صلاحية فتح هذه الصفحة')
@section('code', '403')

{{-- The reason, when the refusal gave one in words a reader can use. --}}
@if (filled($exception->getMessage()) && ! in_array($exception->getMessage(), ['Forbidden', 'This action is unauthorized.'], true))
    @section('message', $exception->getMessage())
@else
    @section('message', 'إن كنت تحتاجها فتواصل مع إدارة المجمع.')
@endif
