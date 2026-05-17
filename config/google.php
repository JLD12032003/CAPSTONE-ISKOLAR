<?php
// Google OAuth configuration
// Replace the placeholders with values from your Google Cloud Console.
return [
    'client_id'     => '277255380888-nvu490t4l1b7oj4ec1hluj834690hvtu.apps.googleusercontent.com',
    'client_secret' => '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',
    // make sure this matches the redirect URI you configure in Google API settings
    'redirect_uri'  => 'http://localhost/Midterm%20exam/index.php?action=googleCallback',
    // optional: scope list (default covers email and profile)
    // using raw URLs here so the file can be loaded even if the library isn't installed yet
    'scopes'        => [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ],
];
