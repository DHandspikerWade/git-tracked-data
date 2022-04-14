<?php
define('USER_ID', '1509464452105711622'); // @HanannieTracker
define('DATABASE_FILE', 'HanannieTracker.json');
define('GIT_ROOT', realpath(getenv('GIT_ROOT') ?: '/tmp/'));
define('TWITTER_TOKEN', getenv('TWITTER_TOKEN'));

if (!GIT_ROOT) {
    echo "Invalid GIT_ROOT\n";
    exit(1); 
}

$raw_data = '{}';
if (file_exists(GIT_ROOT . '/' . DATABASE_FILE)) {
    $raw_data = file_get_contents(GIT_ROOT .  '/' . DATABASE_FILE);
}

$current_skills = json_decode($raw_data, true, 100);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON is corrupt.\n";
    exit(1);
}

$parameters = http_build_query([
    'max_results' => 100,
    'exclude' => 'replies,retweets',
]);

// Stupid simple call. No need to screw around
echo sprintf('https://api.twitter.com/2/users/%s/tweets?%s', USER_ID, $parameters);
$raw_tweets = file_get_contents(sprintf('https://api.twitter.com/2/users/%s/tweets?%s', USER_ID, $parameters), false, stream_context_create([
    "http" => [
        "method" => "GET",
        "header" => sprintf("Authorization: Bearer %s\r\n", TWITTER_TOKEN),
    ]
]));

$tweets = [];
if ($raw_tweets) {
    $tweets = json_decode($raw_tweets, true);
}

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Invalid Tweet data \n";
    exit(2);
}

// Uncomment this for testing without API calls
// $tweets = json_decode(file_get_contents('sample_request.json'), true);

$change_reason = [];

foreach($tweets['data'] as $tweet) {
    if (isset($tweet['text'])) {
        $matches = [];
        if (preg_match('/Hanannie just got ([\w\s]+) level (\d{1,2}) on her PvP locked Hardcore Ironwoman/', $tweet['text'], $matches)) {
            $skill_key = strtolower($matches[1]);
            $last_level = 0;
            if (isset($current_skills[$skill_key])) {
                $last_level = $current_skills[$skill_key];
            }

            if ($last_level < (int) $matches[2]) {
                $current_skills[$skill_key] =  (int) $matches[2];

                $change_reason[$skill_key] = sprintf('https://twitter.com/%s/status/%s', USER_ID, $tweet['id']);
            }
        }
    }
}

ksort($current_skills);
file_put_contents(GIT_ROOT .  '/' . DATABASE_FILE, json_encode($current_skills, JSON_PRETTY_PRINT));

// TODO send data to git