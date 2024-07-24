# LoxBerry-Plugin-TeslaCommand
This plugin is a successor of the TeslaConnect Plugin created by Marius HH - see marius-hh/LoxBerry-Plugin-TeslaConnect. Marius H. has decided to stop working for this plugin. Jan W. has accepted the challenge and has added support for the new API and made some improvements within the UI to send test queries. The new version got a new name (and starts with version 0.1.1), because it is not allowed to change the name of the author of a Loxberry plugin after the initial release. 

Tesla has introduced a new [Vehicle Command SDK](https://github.com/teslamotors/vehicle-command/blob/main/README.md) in October 2023 
as a successor of the [(inofficial) Owner's API](https://tesla-api.timdorr.com/). Pre-2021 model S and X vehicles do not support this new protocol, but all other models will be shifted to the new protocol in 2024.

The Tesla Vehicle Command SDK includes the [Tesla Control Utility](https://github.com/teslamotors/vehicle-command/tree/main/cmd/tesla-control#tesla-control-utility) a command-line interface for sending commands to Tesla vehicles either via Bluetooth Low Energy (BLE) or over the Internet (OAuth token required!). Currently only BLE is implemented in this Plugins and there are no plans to include sending commands over the Internet via Tesla Fleet API.

As of July 2024 only the commands to control the vehicle, e.g. change climate control, change settings for charging the car, or open or close the trunk/frunk are available via new Vehicle Command API. Currently the new API supports a single command 'body-controller-state' that retrieves basic information about closure states, sleep status, and user presence state only. Therefore the plugin supports the old commands from the (inofficial) Owner's API that are still working when the vehicle was switched to the new Vehicle Control API.

The plugin still uses the (inofficial) Owner's API (https://owner-api.teslamotors.com/api/1/vehicles/...) to retrieve status information from the vehicle or powerwall. In the settings section you may choose either 
- the old (inofficial) Owner's API for ALL commands if your vehicle still supports the old commands or you have an energy site
- a mix of (inofficial) Owner's API for commands to retrieve the status of the vehicle and the new Vehicle Command API via BLE to send commands to the vehicle via Tesla Control Utility (tesla-control)

All data from the vehicle is transferred back to the Loxone Miniserver via MQTT. The subscription for this is `teslacmd/#` and is automatically registered in the Loxberry MQTT gateway plugin.

> [!NOTE]
> To use the Tesla Control Utility via BLE you may have to install the tool, set up local keys and authorize the new key by tapping your Tesla NFC card on the center console in your car. 
See [README.md](https://github.com/teslamotors/vehicle-command/tree/main/cmd/tesla-control#tesla-control-utility) for details.

The plugin contains binary files for the two utilities 'tesla-control' and 'tesla-keygen' for Raspberry PI 64-bit version only. For all other platforms you have to follow the instructions provided by Telsa to install 'go' and compile and build the two utilities. You may choose to compile and build the two utilities on a Raspberry PI 64-bit if you have security concerns.

Currently this plugin is in development, so it should not be used in any productive environment. It allows you to select the API for each of your cars and a list of query commands for both API's as well as entering test quieries that show the URL for commands that use the Owner's API or the full tesla-control command for commands that use the Vehicle Command API via BLE.

See 'Queries' and 'Test queries' tabs for description and parmeters for each command.

## Example queries
### Returns all products including vehicles, powerwalls, and energy sites
`http://<user>:<pass>@192.168.1.1/admin/plugins/teslacmd/send.php?action=product_list`
### Wake up vehicle
`http://<user>:<pass>@192.168.1.1/admin/plugins/teslacmd/send.php?action=wake_up&vid=123456789`

## Instructions to install tools and other stuff - this might be included in a final version of the plugin

```
Install vehicle command API on Raspberry

Prerequisites
 - install git

 sudo apt-get install git
 
 - install golang

mkdir ~/golang
cd ~/golang

for Raspberry PI 32-Bit:
wget https://go.dev/dl/go1.22.5.linux-armv6l.tar.gz
sudo tar -C /usr/local -xzf go1.22.5.linux-armv6l.tar.gz

for Raspberry PI 64-Bit:
wget https://go.dev/dl/go1.22.5.linux-arm64.tar.gz
sudo tar -C /usr/local -xzf go1.22.5.linux-arm64.tar.gz

for all other platforms:
see help files in golang

export PATH=$PATH:/usr/local/go/bin

- install vehicle command API

cd ~
git clone https://github.com/teslamotors/vehicle-command.git
cd vehicle-command/
sudo env "PATH=$PATH" go get ./...

cd cmd
cd tesla-control
sudo env "PATH=$PATH" go build ./...
sudo mv tesla-keygen /usr/local/bin/

cd ../tesla-keygen
sudo env "PATH=$PATH" go build ./...
sudo mv tesla-keygen /usr/local/bin/

# create a new key in keyring (default is '~/.tesla_keys') and save public key - you have to enter a password for the key
cd ~
mkdir tesla-connect
tesla-keygen -key-name LRW31234567890123 -keyring-type file -keyring-debug create > LRW31234567890123-public.pem

# OPTION - delete key from keyring
tesla-keygen -key-name MY-OLD-KEY-VIN delete

# OPTION: migrate an existing private key into keyring - you have to enter a password for the key
tesla-keygen -key-file LRW31234567890123-private.pem -key-name LRW31234567890123 -keyring-type file migrate

# OPTION: export an existing private key into a file
tesla-keygen -key-name LRW31234567890123 -keyring-type file export > NEW-LRW31234567890123-public.pem

# install public key in vehicle - requires nfc key card to tap on center console
tesla-control -vin LRW31234567890123 -ble add-key-request ./LRW31234567890123-public.pem owner cloud_key

# grant rights to Loxberry user for BLE
sudo setcap 'cap_net_admin=eip' "$(which tesla-control)"

# example command - recommended
tesla-control -ble -vin LRW31234567890123 -key-name LRW31234567890123 body-controller-state

# example command - not recommended
tesla-control -ble -vin LRW31234567890123 -key-file /opt/loxberry/config/plugins/teslaconnect/LRW31234567890123-private.pem body-controller-state

# sample output - vehicle is asleep
sudo tesla-control -ble -vin LRW31234567890123 -key-name LRW31234567890123 body-controller-state
{
	"vehicleLockState": "VEHICLELOCKSTATE_LOCKED",
	"vehicleSleepStatus": "VEHICLE_SLEEP_STATUS_ASLEEP",
	"userPresence": "VEHICLE_USER_PRESENCE_NOT_PRESENT"
}

# sample output - vehicle is awake
tesla-control -ble -vin LRW31234567890123 -key-name LRW31234567890123 body-controller-state
{
	"closureStatuses": {
		"frontDriverDoor": "CLOSURESTATE_CLOSED",
		"frontPassengerDoor": "CLOSURESTATE_CLOSED",
		"rearDriverDoor": "CLOSURESTATE_CLOSED",
		"rearPassengerDoor": "CLOSURESTATE_CLOSED",
		"rearTrunk": "CLOSURESTATE_CLOSED",
		"frontTrunk": "CLOSURESTATE_CLOSED",
		"chargePort": "CLOSURESTATE_OPEN",
		"tonneau": "CLOSURESTATE_CLOSED"
	},
	"vehicleLockState": "VEHICLELOCKSTATE_LOCKED",
	"vehicleSleepStatus": "VEHICLE_SLEEP_STATUS_AWAKE",
	"userPresence": "VEHICLE_USER_PRESENCE_NOT_PRESENT"
}

# ERROR - vehicle asleep, sometimes I got (problem with BLE beacon)
tesla-control -ble -vin LRW31234567890123 -key-name LRW31234567890123 body-controller-state
Error: failed to find BLE beacon for LRW31234567890123 (S907645abfbbd733bC): can't dial: can't dial: connection canceled

# ERROR - key is not missing in keyring - use 'keyctl show' to display keys in keyring
tesla-control -ble -vin LRW31234567890123 -key-name LRW31234567890123 ping
Error loading credentials: could not load key: The specified item could not be found in the keyring

# ERROR - no rights for user to BLI device
tesla-control -ble -vin LRW31234567890123 -key-name LRW31234567890123 wake
Error: failed to find a BLE device: can't init hci: no devices available: (hci0: can't down device: operation not permitted)

Try again after granting this application CAP_NET_ADMIN:

	sudo setcap 'cap_net_admin=eip' "$(which tesla-control)"

# ERROR - vehicle is asleep
tesla-control -ble -vin LRW31234567890123 -key-name LRW31234567890123 ping
Error: context deadline exceeded
```
