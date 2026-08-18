@include('errors.layout', [
    'title' => 'Page expired',
    'eyebrow' => 'Session timeout',
    'code' => '419',
    'headline' => 'This page has expired.',
    'body' =>
        'The CSRF token on the form you submitted has expired. Refresh the page to get a fresh token, then try again.',
])
