<?php

it('gives the register page a dark maroon decorative panel', function () {
    $this->get(route('register'))
        ->assertSuccessful()
        ->assertSee('bg-gradient-to-b from-[#3f1a19] via-maroon to-[#5c231f]', false);
});

it('gives the login page a light cream decorative panel', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('from-[#f7efe0] via-[#faf5ea] to-[#fdfaf3]', false);
});

it('renders the full page shell (doctype, site header) on the unified login page', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
    $content = $response->getContent();

    expect($content)->toContain('<!DOCTYPE html>');
    expect($content)->toContain('مساعدة');
});
