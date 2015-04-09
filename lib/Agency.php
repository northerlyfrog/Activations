<?php
/**
 *
 *
 */
class Agency {

		private $id = 0;
		private $name = '';
		private $parser_id = 0;
		private $parser_args = '';
		private $location = [
			'city' => '',
			'state' => '',
			'zip' => 0,
			'iso_code' => '',
			'lat' => 0,
			'lon' => 0
			];
		private $account_state = '';
		private $trial_expiration = '';
		private $subscription_id = 0;
		private $alerts = [];

		// Constructor
		function __construct() {

			// TODO
		}

		// Getters
		function get_id(){

			return $this->id;
		}

		function get_name(){

			return $this->name;
		}

		function get_parser_id(){

			return $this->parser_id;
		}

		function get_parser_args(){

			return $this->parser_args;
		}

		function get_city(){

			return $this->location['city'];
		}

		function get_state(){

			return $this->location['state'];
		}

		function get_zip(){

			return $this->location['zip'];
		}

		function get_iso_code(){

			return $this->location['iso_code'];
		}

		function get_lat(){
			
			return $this->location['lat'];
		}

		function get_lon(){

			return $this->location['lon'];
		}

		function get_account_state(){

			return $this->account_state;
		}

		function get_trial_expiration(){

			return $this->trial_expiration;
		}

		function get_subscription_id(){

			return $this->subscription_id;
		}

		function get_alerts(){

			return $this->alerts;
		}






}




?>
