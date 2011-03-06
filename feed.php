<?
class TwitterResolver {
	const RESOLVE_URL = 'http://api.twitter.com/1/users/show.json?screen_name=%s'; // URL to fetch user info from
	const FEED_URL = 'http://api.twitter.com/1/statuses/user_timeline.json?screen_name=%s&count=%d&trim_user=1';
	const USER_PATTERN = '/@(\w+)/';
	const LINK_PATTERN = '/http:\/\/\S+/';
	const FEED_CACHE_FILE = '%s-cache.json';
	const NAME_CACHE_FILE = 'names.json';

	public $ttl = 300;
	public $count = 10;
	public $debug = false;
	
	private $screen_name;
	
	public function __construct($screen_name) {
		$this->screen_name = $screen_name;
	}
	
	public function getTweets() {
		// Check last modified
		$cache_file = sprintf(self::FEED_CACHE_FILE, $this->screen_name);
		$now = time();
		$last_modified = file_exists($cache_file) ? filemtime($cache_file) : 0;
		$expired = $now - $last_modified >= $this->ttl;
		
		if ($expired) {
			$feed_url = sprintf(self::FEED_URL, $this->screen_name, $this->count);

			if ($debug) printf("Fetching tweets from: %s\n", $feed_url);
			
			$tweets = json_decode(file_get_contents($feed_url));
			$data = array_map(array($this, 'processTweet'), $tweets);
			$result = json_encode($data);
			
			file_put_contents($cache_file, $result);
		} else {
			$result = file_get_contents($cache_file);
		}
		
		return $result;
	}
	
	private function processTweet($tweet) {
		if ($debug) printf("\tProcessing tweet: %s\n", $tweet->text);
		
		$date = new DateTime($tweet->created_at);
		
		$body = $tweet->text;
		$body = preg_replace_callback(self::LINK_PATTERN, array($this, 'resolveLink'), $body);
		$body = preg_replace_callback(self::USER_PATTERN, array($this, 'resolveUser'), $body);

		return array('time' => $date->format('c'), 'body' => $body);
	}
	
	private function resolveLink($matches) {
		$url = $matches[0];
		
		return sprintf('<a href="%s">%s</a>', $url, $url);
	}
	
	private function resolveUser($matches) {
		$user = $matches[1];
		
		if (!$this->names) {
			$this->names = file_exists(self::NAME_CACHE_FILE) ? json_decode(file_get_contents(self::NAME_CACHE_FILE), true) : array();
		}
		
		$name = $this->names[$user];
		
		if (!$name) {
			$info_url = sprintf(self::RESOLVE_URL, $user);

			if ($debug) printf("\t\tResolving user from: %s\n", $info_url);

			$info = json_decode(file_get_contents($info_url));
			$this->names[$user] = $info->name;
			
			file_put_contents(self::NAME_CACHE_FILE, json_encode($this->names));
		}
		
		return sprintf('<a href="http://twitter.com/%s" title="@%s">%s</a>', $user, $user, $name);
	}
}

$resolver = new TwitterResolver($_GET['screen_name']);
exit($resolver->getTweets());
?>
