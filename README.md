# consulstatus

> Public-facing consul-backed status page

A simple status page for your Consul services. Displays whether they are up or not with a simple status page. You can choose which services are displayed by specifying their names.

**DISCLAIMER:** This project is not associated with Hashicorp or the Consul project in any way. It just uses the API of your consul instance. Hashicorp do not endorse (or even know about, probably) this project. Hashicorp and Consul are trademarks of Hashicorp®.

## System Requirements
 - Consul instance (authentication isn't yet supported)
 - PHP 7+ enabled web server
 - [Composer](https://getcomposer.org/)

## Installation
Clone this git repo:

```bash
git clone https://github.com/sbrl/consulstatus.git
```

`cd` into the repository:

```bash
cd consulstatus;
```

Install the Composer dependencies:

```bash
composer install
```

Then, point your web server at the `src` directory. For example, to start a PHP development web server:

```bash
# Run this from the root of the repository
php -S [::1]:4567 -t src
```

Finally, create the settings file. Relative to the root of the repository, it should be located `data/settings.toml`. It should look like this:

```toml
title = "ExampleCloud Status Page"

[consul]
# The base URL of the Consul API
base_url = "http://127.0.0.1:8500"
# The list of services to show the status of

[[service_group]]
name = "A Group"

	[[service_group.services]]
	name = "dashboard"
	description = "some description text. The description field is optional."

	[[service_group.services]]
	name = "another_service"

[[service_group]]
name = "Another Group"

	[[service_group.services]]
	name = "apple"
	description = "The apple service"

	[[service_group.services]]
	name = "orange"
	description = "The orange service"
```

Then, you should be able to load `index.php` in your web browser and it should work!

## consulalerter
`consulalerter.sh` is a small Bash script whose purpose is writing service check status updates to an MQTT server - more methods may be added in the future.

**Warning:** The directory used for persisting state gets a number of writes (though attempts have been made to optimise this), so try to avoid it being on an SSD / flash memory.

It writes to the mqtt topic `consul/checks/status_changes` by default whenever the number of failed checks for a services changes in the following format:

```json
{
    "service_name": "SERVICE_NAME_HERE",
    "checks": {
        "total": "2",
        "failed": "0"
    }
}
```

 - `service_name`: The name of the service whose status has changed
 - `checks.total`: The total number of checks the service has
 - `checks.failed`: The number of aforementioned checks that failed

### Setup
Copy the logrotate & rsyslog config files into place:

```bash
cd path/to/consulstatus_repo_root;
sudo cp consulalerter-logrotate /etc/logrotate.d/consulalerter
sudo cp consulalerter-rsyslog.conf /etc/rsyslog.d/consulalerter.conf
```

Copy the systemd service file into place:

```bash
sudo cp consulalerter.service /etc/systemd/system
```

Make sure to edit the service file to match your filepaths:

```bash
sudo nano /etc/systemd/system/consulalerter.service
```

Create the `consulalerter` user:

```bash
sudo useradd --no-create-home --system --home /srv/consulalerter consulalerter
```

Create the data directory:

```bash
sudo mkdir -p /srv/consulalerter;
sudo chown consulalerter:consulalerter /srv/consulalerter;
```

Now, write a config file:

```bash
#!/usr/bin/env bash

interval="60";

mqtt_enabled="true";
mqtt_user="consulalerter";
mqtt_password="CHANGE_ME";
```

Note that consulalerter _requires_ a username & password to login to the MQTT server, and won't function without one.

Save it to the right place:

```bash
# Write the config file....
sudo nano /srv/consulalerter/config.sh
# ....and set the correct permissions on it
sudo chmod +x /srv/consulalerter/config.sh
```

Finally, start `consulalerter.service` and enable it on boot:

```bash
sudo systemctl enable --now consulalerter.service
```


## Contributing
Contributions are very welcome - both issues and pull requests! Please mention in your pull request that you release your work under the MPL-2.0 (see below).

If you're feeling that way inclined, the sponsor button at the top of the page (if you're on GitHub) will take you to my Liberapay profile if you'd like to donate to say an extra thank you :-)


## License
Consulstatus is released under the Mozilla Public License 2.0. The full license text is included in the `LICENSE` file in this repository. Tldr legal have a [great summary](https://tldrlegal.com/license/mozilla-public-license-2.0-(mpl-2)) of the license if you're interested.
