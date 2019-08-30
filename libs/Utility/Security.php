<?php
// extract from cakephp lib/Cake/Utility/Security.php
class Security {
	protected $hmacSalt;

	protected $key;

	protected $defaultsKey = array();

	public function __construct($key, $hmacSalt, $options = array()) {
		$this->key = $key;
		$this->hmacSalt = $hmacSalt;
		$this->setOptions($options);
	}

	public function setOptions($options) {
		foreach ($options as $key => $value) {
			if(in_array($key, $this->defaultsKey)) {
				if(is_array($this->{$key})) {
					$this->{$key} = array_merge($this->{$key}, $value);
				}
				else {
					$this->{$key} = $value;
				}
			}
		}
	}

	public function encrypt($plain, $key = null, $hmacSalt = null) {
		if($key === null) {
			$key = $this->key;
		}
		if(strlen($key) < 32) {
			return false;
		}
		if ($hmacSalt === null) {
			$hmacSalt = $this->hmacSalt;
		}

		// Generate the encryption and hmac key.
		$key = substr(hash('sha256', $key . $hmacSalt), 0, 32);

		$algorithm = MCRYPT_RIJNDAEL_128;
		$mode = MCRYPT_MODE_CBC;

		$ivSize = mcrypt_get_iv_size($algorithm, $mode);
		$iv = mcrypt_create_iv($ivSize, MCRYPT_DEV_URANDOM);
		$ciphertext = $iv . mcrypt_encrypt($algorithm, $key, $plain, $mode, $iv);
		$hmac = hash_hmac('sha256', $ciphertext, $key);
		return $hmac . $ciphertext;
	}

	public function decrypt($cipher, $key = null, $hmacSalt = null) {
		if($key === null) {
			$key = $this->key;
		}
		if(strlen($key) < 32) {
			return false;
		}
		if (empty($cipher)) {
			return false;
		}
		if ($hmacSalt === null) {
			$hmacSalt = $this->hmacSalt;
		}

		// Generate the encryption and hmac key.
		$key = substr(hash('sha256', $key . $hmacSalt), 0, 32);

		// Split out hmac for comparison
		$macSize = 64;
		$hmac = substr($cipher, 0, $macSize);
		$cipher = substr($cipher, $macSize);

		$compareHmac = hash_hmac('sha256', $cipher, $key);
		if ($hmac !== $compareHmac) {
			return false;
		}

		$algorithm = MCRYPT_RIJNDAEL_128;
		$mode = MCRYPT_MODE_CBC;
		$ivSize = mcrypt_get_iv_size($algorithm, $mode);

		$iv = substr($cipher, 0, $ivSize);
		$cipher = substr($cipher, $ivSize);
		$plain = mcrypt_decrypt($algorithm, $key, $cipher, $mode, $iv);
		return rtrim($plain, "\0");
	}
}