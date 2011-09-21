<?php
	/**
	 * PHP client library for accessing Topspin's Offers API
	 * 
	 * Usage: Documentation coming soon.
	 *
	 * @version 1.0
	 * @author StageBloc
	 * @link https://docs.topspin.net/tiki-index.php?page=Offers+API
	 */
	 
	class Topspin_curl
	{
		private $api_key;
		private $api_username;
		private $artist_id;
		private static $currency = array('USD' => '$', 'GBP' => '&#163;', 'CAD' => '$');
		private $per_page;
		private $return_raw = false; // Let's you return the raw API response if true, or a stdObject if false (default)

		const BASE_URL = 'https://app.topspin.net/api/v1/';

		public function __construct($api_key, $api_username, $artist_id = null)
		{
			$this->api_key = $api_key;
			$this->api_username = $api_username;
			if($artist_id)
			{
				$this->artist_id = $artist_id;
			}
		}

		public function artistId($value)
		{
			$this->artist_id = $value;
		}

		/**
		 * Takes a currency code and returns the appropriate currency symbol
		 *
		 * Currency Codes & Symbols - http://www.xe.com/symbols.php
		 * Unicode Converter - http://rishida.net/tools/conversion/
		 * 
		 * @param <string> $currencyCode
		 * @return <string> $currencySymbol
		 */
		public function getCurrencySymbol($currencyCode)
		{
			if(!empty(self::$currency[$currencyCode]))
			{
				return self::$currency[$currencyCode];
			}
		}

		/**
		 *
		 * @param <type> $offer_type Return offers of the given type. Valid types are: buy_button, email_for_media, bundle_widget (multi-track streaming player in the app) or single_track_player_widget
		 * @param <type> $product_type Return offers for the given product type. Valid types: image, video, track, album, package, other_media, merchandise
		 * @param <type> $campaign_name Partial match of campaign name. 
		 */
		public function getOffers($page = 1, $offer_type = 'all', $product_type = 'all', $name = false)
		{
			$url = self::BASE_URL . 'offers';
			$post_args = array();

			$post_args['page'] = $page;
			if($offer_type !== 'all') $post_args['offer_type'] = $offer_type;
			if($product_type !== 'all') $post_args['product_type'] = $product_type;
			if(!empty($name)) $post_args['name'] = $name;

			return $this->process($url, $post_args);
		}
		
		public function getArtists() 
		{
			$ch = curl_init();
			
			curl_setopt($ch, CURLOPT_URL,'http://app.topspin.net/api/v1/artist');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERPWD, $this->api_username . ':' . $this->api_key);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			$response = curl_exec($ch);
			curl_close($ch);
			//	return $this->process();
		}

		public function perPage($value)
		{
			$this->per_page = $value;
		}

		private function process($url, $post_args = null)
		{
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERPWD, $this->api_username . ':' . $this->api_key);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                        // WARNING: this can be used in development env. Turn off in production as this may be a security risk
                        // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

			$post_args = is_array($post_args) ? $post_args : array();
			if($this->per_page) $post_args['per_page'] = $this->per_page;
			if($this->artist_id) $post_args['artist_id'] = $this->artist_id;
			
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_args);

			$response = curl_exec($ch);
			$responseInfo = curl_getinfo($ch);
			$responseError = curl_error($ch);

			curl_close($ch);

			// Check for errors
			if($responseError)
			{
				$tsError = 'cURL Error: ' . $responseError;
				// Return error as JSON response
				$return = '{"error_detail":"' . $tsError . '","request_url":"' . $url . '"}';
			}
			else
			{
				// Analyze errors.
				if($responseInfo['http_code'] != '200')
				{
					switch($responseInfo['http_code'])
					{
						case '401' :
							$tsError = '401 (Unauthorized request)';
						break;

						case '404' :
							$tsError = '404 (Target not found)';
						break;

						case '500' :
							$tsError = '500 (Internal server error)';
						break;

						default :
							$tsError = $responseInfo['http_code'] . ' (Unknown error result)';
						break;

					}
					// Return error as JSON response
					$return = '{"error_detail":"' . $tsError . '","request_url":"' . $url . '"}';
				}
				else
				{
					$return = $response;
				}
			}

			if($this->return_raw === false)
			{
				$return = json_decode($return);
			}
			return $return;
		}
	}
?>
