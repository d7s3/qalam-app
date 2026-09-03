<?php

/**
 * Who this deployment belongs to.
 *
 * The application is handed to a different organisation each time it is
 * deployed, and everything that changes between one and the next is gathered
 * here: the name on the page, the mark in the corner, and the colours the
 * interface is built from.
 *
 * Every value falls back to the original academy's, so an existing deployment
 * that sets none of these keeps exactly the look it has today.
 */
return [

    /**
     * The organisation's name, shown beside the logo and in the page title.
     */
    'name' => env('BRAND_NAME', 'مجمع التاج القرآني'),

    /**
     * A short name for tight places — a narrow sidebar, a mobile header.
     * Falls back to the full name when the organisation has no short form.
     */
    'short_name' => env('BRAND_SHORT_NAME', env('BRAND_NAME', 'مجمع التاج القرآني')),

    /**
     * What the organisation calls itself in running prose — "المجمع", "الجمعية",
     * "المركز". The interface says "إدارة {entity}" and "عن {entity}" in a dozen
     * places, and a academy reading "عن الجمعية" is as wrong as the wrong name.
     */
    'entity' => env('BRAND_ENTITY', 'المجمع'),

    /**
     * The licence the organisation operates under, printed in the page footer.
     * Left blank, the footer simply drops the line.
     */
    'license' => env('BRAND_LICENSE', ''),

    /**
     * The line under the name: what this organisation does, in its own words.
     *
     * Every deployment describes itself differently — one teaches the sacred
     * sciences broadly, another only memorisation — so the sentence belongs
     * here beside the name rather than written into four templates.
     */
    'tagline' => env('BRAND_TAGLINE', 'منصة رقمية متكاملة للعلوم الشرعية وحلقاتها'),

    /**
     * Paths under public/, so a new organisation drops its files in and names
     * them here without a line of code changing.
     */
    'logo' => env('BRAND_LOGO', 'images/altag_logo.png'),
    'favicon' => env('BRAND_FAVICON', 'favicon.svg'),
    'apple_icon' => env('BRAND_APPLE_ICON', 'apple-touch-icon.png'),

    /**
     * Where the organisation sits, as printed on plan sheets handed to students.
     */
    'address' => env('BRAND_ADDRESS', 'جدة - حي الواحة - جامع الزبيدي - حلقات التاج القرآنية التابعة لجمعية خيركم لتعليم القرآن الكريم'),

    /**
     * How the organisation is reached, as shown on the public welcome page.
     * Any left blank simply drops its card rather than showing an empty one.
     */
    'contact' => [
        'social' => env('BRAND_SOCIAL', 'altag_jeddah@'),
        'phone' => env('BRAND_PHONE', '0508822794'),
        'location' => env('BRAND_LOCATION', 'جدة، حي الواحة، خلف هيئة المساحة الجيولوجية'),
    ],

    /**
     * The palette.
     *
     * These override the tokens the stylesheet declares, at runtime rather than
     * at build time — otherwise every organisation would need its own compiled
     * CSS, and changing a colour would mean running a build on the server.
     *
     * `primary`   the colour of the interface: buttons, links, active states
     * `dark`      a deeper shade, for hover and pressed states
     * `on_dark`   the accent as it appears on a dark background, where a deep
     *             colour would sink into the page; lighten it here
     * `deepest`   the darkest shade of the family, for headings and rails
     * `gold`      the warm metallic the mark is lettered in, used for hairlines,
     *             figures and eyebrow text over the deep shades
     */
    'colors' => [
        'primary' => env('BRAND_COLOR', '#7a2727'),
        'dark' => env('BRAND_COLOR_DARK', '#521f1e'),
        'on_dark' => env('BRAND_COLOR_ON_DARK', '#9d2e33'),
        'deepest' => env('BRAND_COLOR_DEEPEST', '#3f1a19'),
        'gold' => env('BRAND_COLOR_GOLD', '#c9a063'),
    ],

];
