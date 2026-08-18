@include('errors.layout', [
    'title' => 'Sign in required',
    'eyebrow' => 'Authentication needed',
    'code' => '401',
    'headline' => 'Please sign in to continue.',
    'body' =>
        'This page is for signed-in users only. Sign in with your' .
        brand_name() .
        "account, or create one if you don't have one yet.",
])
