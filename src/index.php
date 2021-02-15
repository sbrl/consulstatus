<?php 

$perfdata = new stdClass();
$perfdata->start = microtime(true);

if(!file_exists("../vendor")) {
	http_response_code(501);
	header("content-type: text/plain");
	exit("Error: Composer dependencies haven't yet been installed (try executing 'composer install' in the repository root)");
}

// Phase 1: Autoloading
require("../vendor/autoload.php");
require("./lib/TomlConfig.php");
require("./lib/NightInk.php");
require("./lib/ConsulStatusFetcher.php");

$renderer = new \SBRL\NightInk();

$perfdata->autoload = microtime(true);

// Phase 2: Loading Settings
$settings = new \SBRL\TomlConfig("../data/settings.toml", "settings.default.toml");

$perfdata->settings_load = microtime(true);


// Phase 3: Action parsing
$action = $_GET["action"] ?? "index";

switch($action) {
	/*
	 * ██ ███    ██ ██████  ███████ ██   ██
	 * ██ ████   ██ ██   ██ ██       ██ ██
	 * ██ ██ ██  ██ ██   ██ █████     ███
	 * ██ ██  ██ ██ ██   ██ ██       ██ ██
	 * ██ ██   ████ ██████  ███████ ██   ██
	 */
	case "index":
		$status_fetcher = new ConsulStatusFetcher($settings->get("consul.base_url"));
		$status_groups = [];
		// Legacy support
		if($settings->has("consul.services")) {
			$status_groups[] = (object) [
				"name" => "default",
				"statuses" => $status_fetcher->fetch(
					$settings->get("consul.services")
				)
			];
		}
		if($settings->has("service_group")) {
			foreach($settings->get("service_group") as $service_group) {
				$group = (object)$service_group;
				$status_groups[] = (object) [
					"name" => $group->name,
					"statuses" => $status_fetcher->fetch($group->services)
				];
			}
		}
		
		$format = $_GET["format"] ?? "html";
		
		switch ($format) {
			case "json":
				header("content-type: application/json");
				echo(json_encode($statuses));
				break;
			
			case "html":
			default:
				echo($renderer->render_file("./templates/status.html", [
					"title" => $settings->get("title"),
					"status_groups" => $status_groups,
					// "statuses" => $statuses,
					"datetime" => date("c")
				]));
				break;
		}
		break;
}
