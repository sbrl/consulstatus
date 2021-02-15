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
			$item = $this->fetch_single($service_name);
			$result[] = $item;
		}
		return $result;
	}
	
	public function fetch_single($service) {
		if(is_array($service)) $service = (object) $service;
		$service_name = is_string($service) ? $service : $service->name;
		
		$status = $this->query_service_status($service_name);
		
		$status_class = "ok";
		if($status === null) {
			$status_class = "unknown";
			$status = (object) [ "ok" => 0, "failed" => 0 ];
		}
		elseif($status->failed > 0)
			$status_class = $status->ok > 0 ? "degraded" : "failed";
		
		return (object) [
			"name" => $service_name,
			"description" => is_object($service) && is_string($service->description)
				? $service->description : "",
			"status" => $status_class,
			"count_ok" => $status->ok,
			"count_failed" => $status->failed
		];
	}
	
	protected function query_service_status($service_name) {
		$url = "$this->base_url/v1/health/service/".rawurlencode($service_name);
		
		$response_obj = json_decode(file_get_contents($url));
		if($response_obj == null) return null;
		
		$result = (object) [
			"ok" => 0, "failed" => 0,
			"node_ok" => 0, "node_failed" => 0
		];
		foreach($response_obj as $instance) {
			foreach($instance->Checks as $check) {
				if($check->CheckID === "serfHealth") {
					if($check->Status !== "passing")
						$result->node_failed++;
					else
						$result->node_ok++;
					continue;
				}
				
				if($check->Status !== "passing")
					$result->failed++;
				else
					$result->ok++;
			}
		}
		
		// If the service hasn't failed but the node has, then add the node check failure to the service check failure count
		if($result->failed == 0 && $result->node_failed > 0)
			$result->failed += $result->node_failed;
		
		return $result;
	}
}
