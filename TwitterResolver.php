<?php
class TwitterResolver {
    const TOKEN_URL = 'https://api.twitter.com/oauth2/token';
    const RESOLVE_URL = 'https://api.twitter.com/1.1/users/show.json?screen_name=%s'; // URL to fetch user info from
    const FEED_URL = 'https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=%s&count=%d&trim_user=1';
    const USER_PATTERN = '/@([A-Za-z0-9_]+)/';
    const TOPIC_PATTERN = '/#([^\s]+)/';
    const LINK_PATTERN = '/http:\/\/\S+/';
    const FEED_CACHE_FILE = '%s/%s-cache.json';
    const NAME_CACHE_FILE = '%s/names.json';

    public $names;

    private $cache_directory;

    private $key;
    private $secret;
    private $token;

    public function __construct($key = null, $secret = null, $cache_directory = '.') {
        $this->key = $key;
        $this->secret = $secret;
        $this->cache_directory = $cache_directory;
    }

    private function fetchJSON($url, $headers, $fields = null) {
    	$req = curl_init();

    	curl_setopt($req, CURLOPT_URL, $url);
    	curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($req, CURLOPT_HTTPHEADER, $headers);

    	if ($fields) {
    		curl_setopt($req, CURLOPT_POST, true);
    		curl_setopt($req, CURLOPT_POSTFIELDS, $fields);
    	}

    	$data = curl_exec($req);
    	$status = curl_getinfo($req, CURLINFO_HTTP_CODE);
    	curl_close($req);

    	return $status == 200 ? json_decode($data) : null;
    }

    private function getBearerToken() {
        $headers = array(
            'Authorization: Basic ' . base64_encode(urlencode($this->key) . ':' . urlencode($this->secret)),
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        );

        $fields = 'grant_type=client_credentials';
        $data = $this->fetchJSON(self::TOKEN_URL, $headers, $fields);

        if ($data) {
        	$this->token = $data->access_token;
        }
    }

    public function fetchTweets($screen_name, $count = 10, $exclude_replies = false) {
        if (!$this->token) $this->getBearerToken();

        $cache_file = sprintf(self::FEED_CACHE_FILE, $this->cache_directory, $screen_name);
        $feed_url = sprintf(self::FEED_URL, $screen_name, $count * ($exclude_replies ? 10 : 1));

        $data = $this->fetchJSON($feed_url, array('Authorization: Bearer ' . $this->token));

        if ($data) {
	        if ($exclude_replies) {
	            $tweets = array();

	            for ($i = 0; $i < count($data) && count($tweets) < $count; $i++) {
	                $tweet = $data[$i];

	                if (!preg_match('/^@/', $tweet->text)) {
	                    $tweets[] = $tweet;
	                }
	            }

	            $data = $tweets;
	        }

	        $data = array_map(array($this, 'processTweet'), $data);
	        $result = json_encode($data);

	        file_put_contents($cache_file, $result);
        }
    }

    public function getTweets($screen_name) {
        $cache_file = sprintf(self::FEED_CACHE_FILE, $this->cache_directory, $screen_name);
        $result = json_decode(file_get_contents($cache_file));

        return $result;
    }

    private function processTweet($tweet) {
        $date = new DateTime($tweet->created_at);

        $body = $tweet->text;
        $body = preg_replace_callback(self::LINK_PATTERN, array($this, 'resolveLink'), $body);
        $body = preg_replace_callback(self::USER_PATTERN, array($this, 'resolveUser'), $body);
        $body = preg_replace_callback(self::TOPIC_PATTERN, array($this, 'resolveTopic'), $body);

        return array('time' => $date->format('c'), 'body' => $body);
    }

    private function resolveLink($matches) {
        $url = $matches[0];

        return sprintf('<a href="%s" class="link">%s</a>', $url, $url);
    }

    private function resolveUser($matches) {
        $user = $matches[1];
        $name = $user;
        $cache_file = sprintf(self::NAME_CACHE_FILE, $this->cache_directory);

        if (!$this->names) {
            $this->names = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : array();
        }

        if (!array_key_exists($user, $this->names)) {
            $info_url = sprintf(self::RESOLVE_URL, $user);

            $data = $this->fetchJSON($info_url, array('Authorization: Bearer ' . $this->token));

            if ($data) {
            	$name = $this->names[$user] = $data->name;

            	file_put_contents($cache_file, json_encode($this->names));
            }
        }

        return sprintf('<a href="http://twitter.com/%s" title="@%s" class="user">%s</a>', $user, $user, $name);
    }

    private function resolveTopic($matches) {
        $topic = $matches[1];

        return sprintf('<a href="http://twitter.com/#!/search?q=%%23%s" title="#%s" class="topic">#%s</a>', $topic, $topic, $topic);
    }
}
?>
