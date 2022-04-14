<?php
define('USER_ID', '1509464452105711622'); // @HanannieTracker
define('DATABASE_FILE', 'HanannieTracker.json');
define('GIT_ROOT', realpath(getenv('GIT_ROOT') ?: '/tmp/'));
define('GIT_COMMAND', getenv('GIT_COMMAND') ?: 'git');
define('TWITTER_TOKEN', getenv('TWITTER_TOKEN'));

if (!GIT_ROOT) {
    echo "Invalid GIT_ROOT\n";
    exit(1); 
}

$command_template = sprintf('cd %s && %s ', escapeshellarg(GIT_ROOT), escapeshellarg(GIT_COMMAND));
shell_exec(sprintf('%s pull', $command_template));

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
if (count($change_reason) > 0) {
   
    echo $command_template . PHP_EOL;
    shell_exec(sprintf('%s pull', $command_template));
    shell_exec(sprintf('%s add %s', $command_template, escapeshellarg(DATABASE_FILE)));

    $commit_message = '[BOT] Updated level for ' . implode(', ', array_keys($change_reason));
    $commit_message .= "\n\n";

    foreach($change_reason as $skill => $tweet) {
        $commit_message .= $skill . ': ' . $tweet . "\n";
    }

    shell_exec(sprintf('%s commit -m %s', $command_template, escapeshellarg($commit_message)));

    if (getenv('GIT_PUSH') === 'YES') {
        shell_exec(sprintf('%s push -u origin', $command_template, escapeshellarg($commit_message)));
    }

}
