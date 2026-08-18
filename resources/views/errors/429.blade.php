@include('errors.layout', [
    'title' => 'Slow down',
    'eyebrow' => 'Rate limit hit',
    'code' => '429',
    'headline' => 'Too many requests.',
    'body' =>
        "You're hitting our servers a little too fast. Take a breath, then try again in a minute. If this keeps happening, get in touch with support.",
])
