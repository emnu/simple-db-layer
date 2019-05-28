<?php

class Soap extends SoapClient {

	protected $login = null;

	protected $password = null;

	protected $defaultsKey = array(
		'login', 'password', 'wsdlPath'
	);

	protected $wsdlPath = '';

	public function __construct($wsdl, $options = array()) {
		$this->setOptions($options);
		$cleanedWSDL = $this->cacheWSDL($wsdl);
		parent::__construct($cleanedWSDL, $options);
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

	private function cacheWSDL($wsdl) {
		$pathinfo = pathinfo(preg_replace('#^http(s)?://#', '', preg_replace('/\?.*/', '', $wsdl)));
		// pr($pathinfo); die();
		$fileName = rtrim($this->wsdlPath, '\\/') . DIRECTORY_SEPARATOR . str_replace(array('\\',':','/'), array('_','_','_'), $pathinfo['dirname'] . '_' . $pathinfo['filename'] . '.wsdl');
		// pr($fileName); die();

		if (file_exists($fileName) && (time() - filemtime($fileName)) < 1800) { // 30 minutes
			return $fileName;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $wsdl);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$wsdlContent = trim(curl_exec($ch));
		curl_close($ch);
		// pr($wsdlContent); die();

		$wsdlContent = $this->fixSoap12Transport($wsdlContent);

		$file = fopen($fileName,"w");
		fwrite($file, $wsdlContent);
		fclose($file);

		return $fileName;
	}

	private function fixSoap12($wsdlContent) {

	}

	private function fixSoap12Transport($wsdlContent) {
		// @TODO: replace <soap12:binding style="document" transport="http://www.w3.org/2003/05/soap/bindings/HTTP/"/> to <soap12:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
		preg_match('/<soap12:binding.*(\/>|<\/soap12:binding>)/', $wsdlContent, $matches);

		if(isset($matches[0])) {
			$find = $matches[0];
			$replace = preg_replace('/transport\s*=\s*".*"/', 'transport="http://schemas.xmlsoap.org/soap/http"', $find);
			$wsdlContent = str_replace($find, $replace, $wsdlContent);
		}

		return $wsdlContent;
	}

	public function __doRequest( $request, $location, $action, $version, $one_way = 0 ) {
		$result = parent::__doRequest($request, $location, $action, $version, $one_way);

		$headers = $this->__getLastResponseHeaders();
		if (preg_match('#^Content-Type:.*multipart\/.*#mi', $headers) !== 0) {
			$result = str_replace("\r\n", "\n", $result);
			list(, $content) = preg_split("#\n\n#", $result);
			list($result, ) = preg_split("#\n--#", $content);
		}

		return $result;
	}

	public function setWssSecurity($gmdate, $login=null, $password=null) {
		$date = new DateTime($gmdate);
		$timestamp = $date->format('YmdHis');
		
		$hash = sha1(pack('H*', mt_rand()) . pack('a*', $timestamp) . pack('a*', $this->password));
		$packedHash = pack('H*', $hash);

		$encodedNonce = base64_encode($packedHash);	
		
		$xml = '
		<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
				<wsse:UsernameToken>
					<wsse:Username>'.(empty($login)?$this->login:$login).'</wsse:Username>
					<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.(empty($password)?$this->password:$password).'</wsse:Password>
					<wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'.$encodedNonce.'</wsse:Nonce>
					<wsu:Created>'.$gmdate.'</wsu:Created>
				</wsse:UsernameToken>
		</wsse:Security>';

        $authvalues = new SoapVar($xml,XSD_ANYXML);
        $header = new SoapHeader("http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd", "Security", $authvalues,true);

        $this->__setSoapHeaders($header);
	}

	public function setWssTimestamp($created, $expires) {
		$xml = '
		<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
			<wsu:Timestamp>
				<wsu:Created>'.$created.'</wsu:Created>
				<wsu:Expires>'.$expires.'</wsu:Expires>
			</wsu:Timestamp>
		</wsse:Security>';

        $authvalues = new SoapVar($xml,XSD_ANYXML);
        $header = new SoapHeader("http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd", "Security", $authvalues,true);

        $this->__setSoapHeaders($header);
	}
}
