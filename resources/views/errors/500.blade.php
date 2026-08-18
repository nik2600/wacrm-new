@include('errors.layout', [
    'title' => 'Server error',
    'eyebrow' => 'Something broke',
    'code' => '500',
    'headline' => 'Our server hit a snag.',
    'body' =>
        "Sorry, something went wrong on our end. We've logged the issue and our team is on it. Try again in a moment, and if the problem persists, contact support.",
])
