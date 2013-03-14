<?php
/*********************************************************************/
/* CurrencyConverter class – for all your currency conversion needs! */
/* Developer: An Owomoyela [aowomoyela@gmail.com]                    */
/* Last Significant Revision: 2013-03-13                             */
/*********************************************************************/

class CurrencyConverter {
	
	/* Function to retrieve XML and parse it into a usable array. */
	/* Available for use outside of this project, as a static function. */
	public static function getConversionRates() {
		try {
			// Configuration.
			$conversion_api = 'http://toolserver.org/~kaldari/rates.xml';
			// Retrieve the XML using cURL.
			$cURL = curl_init();
			curl_setopt($cURL, CURLOPT_URL, $conversion_api);
			curl_setopt($cURL, CURLOPT_RETURNTRANSFER, 1);
			$conversion_xml = curl_exec($cURL);
			curl_close($cURL);
			// Parse the URL into a usable form.
			$conversion_elem = new SimpleXMLElement($conversion_xml);
			$conversion_array = array();
			foreach ($conversion_elem as $conversion) {
				$currency = (string)$conversion->currency;
				$rate = (string)$conversion->rate;
				$conversion_array[$currency] = $rate;
			}
			// Return the array.
			return $conversion_array;
		} catch (Exception $e) {
			// Log an error and return false.
			// We'll assume that we have a logging function which will handle the relevant fopen, fwrite, and fclose functions.
			$log = "Error executing CurrencyConverter::getConversionRates() : ".$e->getMessage()."\n";
			new WikimediaLogError($log);
			return false;
		}
	}
	
	
	/* Function to update values in our MySQL table, exchange_rates. */
	/* See SQL_CreateExchangeRates.sql for notes on table design and exchange rate deprecation. */
	public static function updateConversionRates() {
		try {
			// We should have a function or an object set up to create the database connection.
			// Ideally, this and any sensitive connection information should be stored outside any applicable webroot.
			// We'll assume that the WikimediaDatabaseConnection constructor takes a single argument: the database.
			$db = new WikimediaDatabaseConnection('utilities');
			// Let's grab the most recent conversion rates.
			$current_rates = CurrencyConverter::getConversionRates();
			// Update the database. Deprecate each of the old rates, and insert the new.
			$to_deprecate = array();
			$to_insert = array();
			foreach ($current_rates as $currency_code => $rate) {
				// We trust this information, but let's verify it, anyway.
				if ( !preg_match("/^[A-Z]{3}$/", $currency_code) || !is_numeric($rate) ) {
					$log = "Some data in CurrencyConverter::getConversionRates() is corrupted: ";
					$log.= "currency_code '".$currency_code."' and rate '".$rate."'";
					new WikimediaLogError($log);
					continue;
				}
				$to_deprecate[] = "'".$currency_code."'";
				$to_insert[] = "('$currency_code', '$rate', '1', now(), NULL)";
			}
			$deprecate_q = "update exchange_rates set current = 0, deprecated = now() where current = '1' && currency_code in ";
			$deprecate_q.= "(".implode(', ', $to_deprecate).")";
			$deprecate_r = mysqli_query($db->connection, $deprecate_q);
			if ($deprecate_r == false) {
				$log = "Values in exchange_rates could not be deprecated.";
				throw new Exception($log);
			}
			$insert_q = "insert into exchange_rates (currency_code, rate, current, retrieved, deprecated) values ";
			$insert_q.= implode(', ', $to_insert);
			$insert_r = mysqli_query($db->connection, $insert_q);
			if ($insert_r == false) {
				$log = "Table exchange_rates could not be updated.";
				throw new Exception($log);
			}
			return true;
		} catch (Exception $e) {
			// Log an error and return false.
			// We'll assume that we have a logging function which will handle the relevant fopen, fwrite, and fclose functions.
			$log = "Error executing CurrencyConverter::updateConversionRates() : ".$e->getMessage();
			new WikimediaLogError($log);
			return false;
		}
	}
	
	
	// Function to convert currencies.
	public static function convertToUSD($value) {
		try {
			// We should have a function or an object set up to create the database connection.
			// Ideally, this and any sensitive connection information should be stored outside any applicable webroot.
			// We'll assume that the WikimediaDatabaseConnection constructor takes a single argument: the database.
			$db = new WikimediaDatabaseConnection('utilities');
			// Data handling.
			if ( !is_array($value) ) {
				$value = array($value);
			}
			$return_array = array();
			foreach ($value as $v) {
				// Validate our data.
				if ( !preg_match('/^[A-Z]{3}[ ]{1}[0-9]*[\.]?[0-9]*$/', $v) ) {
					$log = "Value or values in an invalid format.";
					throw new Exception($log);
				}
				$parts = explode(' ', $v);
				$currency_code = $parts[0];
				$amount = $parts[1];
				// Perform the conversion.
				$conversion_q = "select ".$amount." * (select rate from exchange_rates ";
				$conversion_q.= "where current = '1' && currency_code = '".$currency_code."') as conversion";
				$conversion_r = mysqli_query($db->connection, $conversion_q);
				$conversion_a = mysqli_fetch_assoc($conversion_r);
				if ( $conversion_a['conversion'] != '' ) {
					// This would be a good place to round, if rounding were necessary.
					$return_array[] = 'USD '.$conversion_a['conversion'];
				} else {
					$return_array[] = '(Unsupported currency: '.$currency_code.')';
				}
			}
			// Return as either a string or an array, depending on the number of results.
			if ( count($return_array) == 1 ) {
				return $return_array[0];
			} else {
				return $return_array;
			}
		
		} catch(Exception $e) {
			// Log an error and return false.
			// We'll assume that we have a logging function which will handle the relevant fopen, fwrite, and fclose functions.
			$log = "Error executing CurrencyConverter::convert() : ".$e->getMessage();
			new WikimediaLogError($log);
			return false;
		}
	}
}
?>