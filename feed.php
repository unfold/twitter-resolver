<?
class TwitterResolver {
	const RESOLVE_URL = 'http://api.twitter.com/1/users/show.json?screen_name=%s'; // URL to fetch user info from
	const FEED_URL = 'http://api.twitter.com/1/statuses/user_timeline.json?screen_name=%s&count=%d&trim_user=1';
	const USER_PATTERN = '/@(\w+)/';
	const LINK_PATTERN = '/http:\/\/\S+/';
	const FEED_CACHE_FILE = '%s/%s-cache.json';
	const NAME_CACHE_FILE = '%s/names.json';
	
	public $names;
	
	private $cache_directory;
	
	public function __construct($cache_directory = '.') {
		$this->cache_directory = $cache_directory;
	}
	
	public function fetchTweets($screen_name, $count = 10) {
		$cache_file = sprintf(self::FEED_CACHE_FILE, $this->cache_directory, $screen_name);
		$feed_url = sprintf(self::FEED_URL, $screen_name, $count);
		
		$tweets = json_decode(file_get_contents($feed_url));
		$data = array_map(array($this, 'processTweet'), $tweets);
		$result = json_encode($data);
		
		file_put_contents($cache_file, $result);
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

		return array('time' => $date->format('c'), 'body' => $body);
	}
	
	private function resolveLink($matches) {
		$url = $matches[0];
		
		return sprintf('<a href="%s">%s</a>', $url, $url);
	}
	
	private function resolveUser($matches) {
		$user = $matches[1];
		$cache_file = sprintf(self::NAME_CACHE_FILE, $this->cache_directory);
		
		if (!$this->names) {
			$this->names = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : array();
		}
		
		$name = $this->names[$user];
		
		if (!$name) {
			$info_url = sprintf(self::RESOLVE_URL, $user);

			$info = json_decode(file_get_contents($info_url));
			$this->names[$user] = $info->name;
			
			file_put_contents($cache_file, json_encode($this->names));
		}
		
		return sprintf('<a href="http://twitter.com/%s" title="@%s">%s</a>', $user, $user, $name);
	}
}
?>
