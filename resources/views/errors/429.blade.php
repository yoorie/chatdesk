@include('errors.layout', [
    'code' => "429",
    'title' => "Too many requests",
    'description' => "Please wait a moment before trying again.",
    'accent' => "amber",
])
