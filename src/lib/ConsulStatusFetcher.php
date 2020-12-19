<?php

/**
 * Class that fetches status information from a Consul instance.
 */
class ConsulStatusFetcher
{
	/**
	 * The base URL to which queries are made.
	 * @var string
	 */
	private $base_url;
	
	function __construct(string $base_url) {
		$this->base_url = $base_url;
	}
	
	/**
	 * Fetches the statuses of the given service names.
 	 * @param	string[]	$service_names	An array of strings of the service names to check
	 * @return	stdClass[]	An array of status objects.
	 */
	public function fetch(array $service_names) {
		$result = [];
		foreach($service_names as $service_name) {
			$status = $this->query_service_status($service_name);
			$status_class = "ok";
			if($status->failed > 0)
				$status_class = $status->ok > 0 ? "degraded" : "failed";
			
			$result[] = (object) [
				"name" => $service_name,
				"status" => $status_class,
				"count_ok" => $status->ok,
				"count_failed" => $status->failed
			];
		}
		return $result;
	}
	
	protected function query_service_status($service_name) {
		$url = "$this->base_url/v1/health/service/".rawurlencode($service_name);
		
		$response_obj = json_decode(file_get_contents($url));
		
		$result = (object) [ "ok" => 0, "failed" => 0 ];
		foreach($response_obj as $instance) {
			foreach($instance->Checks as $check) {
				if($check->Status !== "passing")
					$result->failed++;
				else
					$result->ok++;
			}
		}
		return $result;
	}
}
