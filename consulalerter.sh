#!/usr/bin/env bash

# The storage directory to persist state in
storage_dir="${storage_dir:-/srv/consulalerter}";
# The Consul endpoint to query
consul_endpoint="http://[::1]:8500";
# The interval in second to check
interval="300"; # 300s = 5 minutes

mqtt_enabled="false";
mqtt_host="localhost";
mqtt_port="1883";
mqtt_topic="consul/checks/status_changes";
mqtt_user="";
mqtt_password="";

###############################################################################

# $1 - Command name to check for
check_command() {
	which $1 >/dev/null 2>&1; exit_code=$?
	if [[ "${exit_code}" -ne 0 ]]; then
		echo "Error: Couldn't locate $1. Make sure it's installed and in your path (on apt-based systems try 'sudo apt install $2')" >&2;
		exit 1;
	fi
}

check_command curl curl;
check_command jq jq;
check_command mosquitto_pub mosquitto-clients;


if [[ "${EUID}" -eq 0 ]]; then
	echo "Error: This script must not be run as root!" >&2;
	echo "It does not require root privileges, so running it as root unnecessarily is a security risk." >&2;
	exit 3;
fi

if [[ ! -d "${storage_dir}" ]]; then
	echo "Error: The storage dir at '${storage_dir}' doesn't exist." >&2;
	exit 1;
fi

if [[ -r "${storage_dir}/config.sh" ]]; then
	# shellcheck disable=SC1090
	source "${storage_dir}/config.sh";
fi

###############################################################################

mkdir -p "${storage_dir}/failed_checks";

###############################################################################

# Ref https://github.com/dylanaraps/pure-bash-bible#use-read-as-an-alternative-to-the-sleep-command
# Usage: snore 1
#        snore 0.2
snore() {
    read -rt "$1" <> <(:) || :
}

log_msg() {
	echo "[$(date --rfc-3339=seconds)] consulalerter[$$]: $*" >&2;
}

###############################################################################

# $1	The service name
# $2	The number of checks that have failed
# $3	The total number of checks
alert() {
	service_name="${1}";
	checks_failed="${2}";
	checks_total="${3}";
	
	if [[ "${mqtt_enabled}" == "true" ]]; then
		message="$(jq --null-input --arg service_name "${service_name}" --arg failed "${checks_failed}" --arg total "${checks_total}" '{ service_name: $service_name, checks: { total: $total, failed: $failed } }')";
		
		# Not good practice! Password should be preferably in files, or otherwise environment variables, but mosquitto_pub doesn't support that :-(
		mosquitto_pub -h "${mqtt_host}" -p "${mqtt_port}" -u "${mqtt_user}" -P "${mqtt_password}" -t "${mqtt_topic}" -m "${message}";
	fi
}

###############################################################################

while true; do
	
	# This is defined in the config file
	# shellcheck disable=SC2154
	while read -r service_name; do
		response="$(curl -sS "${consul_endpoint}/v1/health/service/${service_name}")";
		checks_total="$(echo "${response}" | jq '.[].Checks[] | .Status' | wc -l)";
		failed_checks_count="$(echo "${response}" | jq '.[].Checks[] | select(.Status != "passing") | .Status' | wc -l)";
		log_msg "Checked service $service_name: total ${checks_total} checks, ${failed_checks_count} failed";
		
		filepath_failed="${storage_dir}/failed_checks/${service_name}";
		
		if [[ ! -f "${filepath_failed}" ]]; then
			echo "-1" >"${filepath_failed}";
		fi
		
		failed_checks_count_prev="$(cat "${filepath_failed}")";
		
		if [[ "${failed_checks_count}" -ne "${failed_checks_count_prev}" ]]; then
			alert "${service_name}" "${failed_checks_count}" "${checks_total}";
		fi
		
		echo "${failed_checks_count}" >"${filepath_failed}";
	done < <(curl -sS "${consul_endpoint}/v1/agent/services" | jq --raw-output 'keys | .[]');
	
	snore "${interval}";
done
