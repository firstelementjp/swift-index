<?php
/**
 * Swift Index API Handler
 *
 * This file contains the core function responsible for communicating with the
 * Google Indexing API. It handles fetching and caching OAuth 2.0 access tokens
 * and sending URL notification requests.
 *
 * @package Swift_Index
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FirstElement\SwiftIndex\Google\Auth\Credentials\ServiceAccountCredentials;
use FirstElement\SwiftIndex\Google\Auth\HttpHandler\HttpHandlerFactory;
use FirstElement\SwiftIndex\Google\Auth\OAuth2Exception; // For error handling

// Transient (cache) key for the access token
define('SWIFT_INDEX_ACCESS_TOKEN_TRANSIENT_KEY', 'swift_index_auth_token_data');

/**
 * Sends a notification to the Google Indexing API for a given URL and type.
 *
 * Handles fetching an OAuth 2.0 access token (with caching via Transients API)
 * using a service account JSON key, and then makes a POST request to the
 * Indexing API endpoint. Logs the outcome of the notification attempt.
 *
 * @since 1.0.0
 *
 * @param string $url  The URL to notify.
 * @param string $type The type of notification ('URL_UPDATED' or 'URL_DELETED').
 * @return bool|WP_Error True on successful API acceptance (HTTP 2xx), WP_Error on failure.
 */
function swift_index_send_notification( $url, $type ) {
	$service_account_json_string = get_option('swift_index_service_account_json');
	$post_id = url_to_postid($url);

	if ( empty( $service_account_json_string ) ) {
		swift_index_record_log($post_id, $url, $type, 'CONFIG_ERROR', 'Service account JSON not configured.');
		return new WP_Error('config_error', 'Service account JSON not configured.');
	}

	$access_token = null;

	// 1. Try to get a cached access token
	$cached_token_data = get_transient(SWIFT_INDEX_ACCESS_TOKEN_TRANSIENT_KEY);

	if (false !== $cached_token_data && isset($cached_token_data['access_token'])) {
		$access_token = $cached_token_data['access_token'];
	} else {
		// 2. If no valid cached token, fetch a new one
		$credentialsArray = json_decode($service_account_json_string, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			swift_index_record_log($post_id, $url, $type, 'JSON_ERROR', 'Invalid Service account JSON: ' . json_last_error_msg());
			return new WP_Error('json_error', 'Invalid Service account JSON: ' . json_last_error_msg());
		}

		$scopes = ['https://www.googleapis.com/auth/indexing'];

		try {
			$credentials = new ServiceAccountCredentials($scopes, $credentialsArray);
			$httpHandler = HttpHandlerFactory::build();

			$tokenArray = $credentials->fetchAuthToken($httpHandler);

			if (isset($tokenArray['access_token'])) {
				$access_token = $tokenArray['access_token'];
				$expires_in = isset($tokenArray['expires_in']) ? (int)$tokenArray['expires_in'] : 3599;
				$cache_duration = max(60, $expires_in - 300); // Cache for slightly less than actual expiry

				$token_data_to_cache = [
					'access_token' => $access_token,
				];
				set_transient(SWIFT_INDEX_ACCESS_TOKEN_TRANSIENT_KEY, $token_data_to_cache, $cache_duration);

			} else {
				$errorMessage = 'Failed to fetch access token from Google.';
				if (isset($tokenArray['error_description'])) {
					$errorMessage .= ' Description: ' . $tokenArray['error_description'];
				} elseif (isset($tokenArray['error'])) {
					$errorMessage .= ' Error: ' . $tokenArray['error'];
				}
				swift_index_record_log($post_id, $url, $type, 'TOKEN_ERROR', $errorMessage);
				return new WP_Error('token_error', $errorMessage);
			}

		} catch (OAuth2Exception $e) {
			swift_index_record_log($post_id, $url, $type, 'AUTH_EXCEPTION', 'OAuth2Exception: ' . $e->getMessage());
			return new WP_Error('auth_exception', 'OAuth2 Exception: ' . $e->getMessage());
		} catch (Exception $e) {
			swift_index_record_log($post_id, $url, $type, 'GENERAL_EXCEPTION_AUTH', 'Exception during auth: ' . $e->getMessage());
			return new WP_Error('general_exception_auth', 'General Exception during auth: ' . $e->getMessage());
		}
	}

	// 3. Send API request using the access token
	if (!$access_token) {
		swift_index_record_log($post_id, $url, $type, 'TOKEN_UNAVAILABLE', 'Access token is unavailable for API request.');
		return new WP_Error('token_unavailable', 'Access token is unavailable for API request.');
	}

	$api_url = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
	$headers = [
		'Content-Type'  => 'application/json',
		'Authorization' => 'Bearer ' . $access_token,
	];
	$body_args = [
		'url'  => $url,
		'type' => $type,
	];
	$body = wp_json_encode($body_args);

	$response_args = [
		'method'    => 'POST',
		'headers'   => $headers,
		'body'      => $body,
		'timeout'   => 15,
		'sslverify' => true,
	];

	$api_response = wp_remote_post($api_url, $response_args);

	if (is_wp_error($api_response)) {
		$error_message = $api_response->get_error_message();
		swift_index_record_log($post_id, $url, $type, 'WP_REMOTE_ERROR', $error_message);
		return $api_response;
	} else {
		$status_code = wp_remote_retrieve_response_code($api_response);
		$response_body = wp_remote_retrieve_body($api_response);
		$response_data = json_decode($response_body, true);

		if ($status_code === 401) { // Unauthorized - token might be invalid
			delete_transient(SWIFT_INDEX_ACCESS_TOKEN_TRANSIENT_KEY); // Clear an invalid token
			swift_index_record_log($post_id, $url, $type, (string)$status_code, 'API Error: Unauthorized (Access token may be invalid/expired). Token cache cleared.');
			return new WP_Error('api_error_401', 'API Error: Unauthorized. Please try again.', ['status' => $status_code]);
		}

		if ($status_code >= 200 && $status_code < 300) {
			$log_message = 'Successfully published.';
			if (isset($response_data['urlNotificationMetadata']['latestUpdate']['url'])) {
				$log_message .= ' Metadata URL: ' . esc_url_raw($response_data['urlNotificationMetadata']['latestUpdate']['url']);
			}
			swift_index_record_log($post_id, $url, $type, (string)$status_code, $log_message);
			return true;
		} else {
			$error_message = 'API Error. Status: ' . $status_code;
			if (isset($response_data['error']['message'])) {
				$error_message .= ' Message: ' . $response_data['error']['message'];
			} elseif (!empty($response_body)) {
				$error_message .= ' Body: ' . wp_trim_words(wp_strip_all_tags($response_body), 50, '...');
			}
			swift_index_record_log($post_id, $url, $type, (string)$status_code, $error_message);
			return new WP_Error('api_error', $error_message, ['status' => $status_code, 'body' => $response_data]);
		}
	}
}
