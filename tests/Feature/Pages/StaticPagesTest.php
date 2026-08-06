<?php

/**
 * Roadmap task 2.1. Legacy source has no DB/business logic (help.md §2,
 * pages.md §5, §8 both confirm "No database interaction anywhere in these
 * files") — these tests verify presence of the actual legacy content, not
 * just a 200 status, since a blank 200 page would pass a status-only check
 * while silently losing the content ADR-0010 classifies "Must preserve".
 */
it('serves the privacy policy page with its real legacy content', function () {
    $response = $this->get('/privacy');

    $response->assertOk()
        ->assertSee('سياسة الخصوصية') // title, appears in <h1> and @section('title')
        ->assertSee('مقدمة')
        ->assertSee('أمن البيانات') // section 10 heading — proves the full document rendered, not just the intro
        ->assertSee('GDPR');
});

it('serves the about-us page with its real legacy content', function () {
    $response = $this->get('/about');

    $response->assertOk()
        ->assertSee('من نحن') // title
        ->assertSee('الرؤية')
        ->assertSee('شبكة الطريق إلى الله')
        ->assertSee('2005'); // founding-year detail from the historical narrative section
});

it('renders both pages with the correct Arabic/RTL document direction', function () {
    expect($this->get('/privacy')->getContent())->toContain('dir="rtl"');
    expect($this->get('/about')->getContent())->toContain('dir="rtl"');
});
