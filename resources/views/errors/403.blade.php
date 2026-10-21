@include('errors.layout', [
    'code' => "403",
    'title' => "Access denied",
    'description' => "You do not have permission to view this page in the current workspace.",
    'accent' => "amber",
])
