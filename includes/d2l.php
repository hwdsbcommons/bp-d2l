<?php

class BP_D2L_Auth {
	/**
	 * Holds our custom class properties.
	 *
	 * These variables are stored in a protected array that is magically
	 * updated using PHP 5.2+ methods.
	 *
	 * @see BP_Feed::__construct() This is where $data is added
	 * @var array
	 */
	protected $data;

	protected $authContext;

	protected $hostSpec;

	protected $opContext;

	/**
	 * Magic method for checking the existence of a certain data variable.
	 *
	 * @param string $key
	 */
	public function __isset( $key ) { return isset( $this->data[$key] ); }

	/**
	 * Magic method for getting a certain data variable.
	 *
	 * @param string $key
	 */
	public function __get( $key ) { return isset( $this->data[$key] ) ? $this->data[$key] : null; }

	/**
	 * Constructor.
	 *
	 * @param $args Array
	 */
	public function __construct( $args = array() ) {

		// Setup data
		$this->data = wp_parse_args( $args, array(
			'appkey'  => '',
			'appid'   => '',
			'host'    => '',
			'port'    => 443,
			'scheme'  => 'https',
			'userid'  => '',
			'userkey' => ''
		) );


		//$this->validate();

		$this->includes();

		$this->authenticate();

		return $this;
	}

	protected function authenticate() {
		$authContextFactory = new D2LAppContextFactory();

		$this->authContext  = $authContextFactory->createSecurityContext( $this->appid, $this->appkey );
		$this->hostSpec     = new D2LHostSpec( $this->host, $this->port, $this->scheme );

		$this->opContext    = $this->authContext->createUserContextFromHostSpec( $this->hostSpec, $this->userid, $this->userkey	);

	}

	protected function includes() {
		if ( ! class_exists( 'D2LAppContextFactory' ) ) {
			require 'lib/D2LAppContextFactory.php';
		}
	}

	public function getOpContext() {
		return $this->opContext;
	}
}

class BP_D2L_API {
	/**
	 * Holds our custom class properties.
	 *
	 * These variables are stored in a protected array that is magically
	 * updated using PHP 5.2+ methods.
	 *
	 * @var array
	 */
	protected $data;

	protected $request;

	protected $opContext;

	/**
	 * Magic method for checking the existence of a certain data variable.
	 *
	 * @param string $key
	 */
	public function __isset( $key ) { return isset( $this->data[$key] ); }

	/**
	 * Magic method for getting a certain data variable.
	 *
	 * @param string $key
	 */
	public function __get( $key ) { return isset( $this->data[$key] ) ? $this->data[$key] : null; }

	/**
	 * Constructor.
	 */
	public function __construct( $args = array(), D2LUserContext $opContext ) {

		// Setup data
		$this->data = wp_parse_args( $args, array(
			// the path to the particular action - view "path" section at this URL:
			// http://docs.valence.desire2learn.com/basic/apicall.html#structure
			'action'   => '',

			// the d2l component to query
			// http://docs.valence.desire2learn.com/basic/conventions.html#term-d2lproduct
			'component' => 'lp',


			'method'    => 'GET',

			// the API version for the component - view "version" section at this URL:
			// http://docs.valence.desire2learn.com/basic/apicall.html#structure
			'version'   => '1.0',

			// only used with 'GET' and 'PUT' method call
			'input'     => false
		) );

		$this->opContext = $opContext;

		// if version 1.0 is used, try to use latest version of API based on component
		// latest versions are hardcoded to prevent pinging the API for it
		if ( $this->version == '1.0' ) {
			switch ( $this->component ) {
				case 'ep' :
					$this->version = '2.1';

					break;

				case 'le' :
				case 'lp' :
					$this->version = '1.3';

					break;

				case 'rp' :
					$this->version = '1.2';

					break;

				case 'LR' :
				case 'lti' :
				case 'ext' :
					$this->version = '1.1';

					break;
			}
		}



		// Setup request
		$this->request = "/d2l/api/{$this->component}/{$this->version}{$this->action}";

		return $this;
	}

	public static function init( $args = array(), D2LUserContext $opContext ) {
		return new self( $args, $opContext );

	}

	public function call_d2l() {
		$ch = curl_init();

		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CAINFO         => buddypress()->d2l->path . '/cacert.pem'
		);

		curl_setopt_array( $ch, $options );

		$numAttempts = 2;

		$ret = '';

		while( $numAttempts != 0 ) {
			$uri = $this->opContext->createAuthenticatedUri( $this->request, $this->method );

			curl_setopt( $ch, CURLOPT_URL, $uri );

			if ( $this->method != 'GET' ) {
				curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $this->method );
			}

			switch( $this->method ) {
				case 'POST':
				case 'PUT' :
					curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
						'Content-Type: application/json',
						'Content-Length: ' . strlen( $this->input )
					) );

					curl_setopt( $ch, CURLOPT_POSTFIELDS, $this->input );

					break;

				default :
					break;
			}

			$response     = curl_exec( $ch );
			$httpCode     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$contentType  = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
			$responseCode = $this->opContext->handleResult( $response, $httpCode, $contentType );

			if( $responseCode == D2LUserContext::RESULT_OKAY ) {
				if( strstr( $contentType, 'application/json' ) ) {
					$ret = json_decode( $response );
				} else {
					$ret = $response;
				}
				break;

			} elseif( $responseCode == D2LUserContext::RESULT_INVALID_TIMESTAMP ) {
				$numAttempts--;

				continue;

			} else {
				return $httpCode;
			}

			$numAttempts -= 1;
		}

		curl_close( $ch );

		return $ret;
	}
}