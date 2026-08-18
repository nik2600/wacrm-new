@include('errors.layout', [
    'title' => 'Access denied',
    'eyebrow' => 'Off-limits',
    'code' => '403',
    'headline' => 'You don\'t have access to this page.',
    'body' =>
        "Your role in this workspace doesn't include this surface. Ask the workspace owner to invite you with a higher role, or switch to a workspace where you do have access from the top-bar pill.",
])
