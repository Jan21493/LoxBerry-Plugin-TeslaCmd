# LoxBerry-Plugin-TeslaCommand
This plugin is a successor of the TeslaConnect Plugin created by Marius H. - see marius-hh/LoxBerry-Plugin-TeslaConnect. Marius H. has decided to stop working for this plugin. Jan W. has accepted the challenge and has added support for the new API and made some improvements within the UI to send test queries. The new version got a new name (and starts with version 0.1.1), because it is not allowed to change the name of the author of a Loxberry plugin after the initial release. 

Tesla has introduced a new [Vehicle Command SDK](https://github.com/teslamotors/vehicle-command/blob/main/README.md) in October 2023 
as a successor of the [(inofficial) Owner's API](https://tesla-api.timdorr.com/). Pre-2021 model S and X vehicles do not support this new protocol, but all other models will be shifted to the new protocol in 2024.

The Tesla Vehicle Command SDK includes the [Tesla Control Utility](https://github.com/teslamotors/vehicle-command/tree/main/cmd/tesla-control#tesla-control-utility) a command-line interface for sending commands to Tesla vehicles either via Bluetooth Low Energy (BLE) or over the Internet (OAuth token required!). Currently only BLE is implemented in this Plugins and there are no plans to include sending commands over the Internet via Tesla Fleet API.

All commands to control the vehicle, e.g. change climate control, change settings for charging the car, or open or close the trunk/frunk are available via new Vehicle Command API via BLE or Fleet API. Since summer 2024 the new API supports two commands to retrieve status information via BLE. The first command 'body-controller-state' retrieves basic information about closure states, sleep status, and user presence state and works even when the vehicle is asleep. The second command 'state' is used to retrieve detailled status information about either software-update, drive, closures, charge-schedule, precondition-schedule, location, tire-pressure, charge, climate, media, media-detail, and parental-controls.
The plugin also supports the old commands from the (inofficial) Owner's API that are still working, e.g. for pre 2021 S and Y models
and powerwalls. For newer vehicles some status information can still be retrieved via (inofficial) Owner's API (https://owner-api.teslamotors.com/api/1/vehicles/...) . 

Since version 0.5 of this plugin, the proper API is choosen automatically. 

All data from the vehicle is transferred back to the Loxone Miniserver via MQTT. The subscription for this is `teslacmd/#` and is automatically registered in the Loxberry MQTT gateway plugin.

> [!NOTE]
> This plugin uses modified utilities to the ones that were provided by Tesla. See [README.md](https://github.com/teslamotors/vehicle-command/tree/main/cmd/tesla-control#tesla-control-utility) for details about purpose and parameters.

The plugin uses the utilities 'tesla-control', 'tesla-keygen', 'tesla-scan', and 'tesla-blescan'. For Raspberry PI 32- and 64-bit the plugin contains binary files for 'tesla-control', 'tesla-keygen', and 'tesla-scan'. For all other platforms, and currently also for 'tesla-blescan', you have to follow the instructions provided by Telsa to install 'go' and compile and build the utilities. You may choose to compile and build the utilities on a Raspberry PI if you have security concerns or if Tesla has provided a new version of the SDK.

Currently this plugin is in development, so it should be used in any productive environment with care. It shows a list of all commands that are available for your vehicles and powerwalls as well as entering test queries to know more about each command and the responses.

See 'Queries' and 'Test Queries' tabs for description and parmeters for each command. See https://wiki.loxberry.de/plugins/teslacmd/start for informations regarding installation and integration into Loxone home automation.

## Example queries
### Returns all products including vehicles, powerwalls, and energy sites
`http://<user>:<pass>@192.168.1.1/admin/plugins/teslacmd/send.php?action=product_list`
### Shows basic status information from a vehicle
`http://<user>:<pass>@192.168.1.1/admin/plugins/teslacmd/send.php?action=body_controller_statep&vin=LRW31234567890123`
### Wake up vehicle
`http://<user>:<pass>@192.168.1.1/admin/plugins/teslacmd/send.php?action=wake_up&vin=LRW31234567890123`

## Instructions to install tools and other stuff - not required for a standard installation

Please follow the installation described at [TeslaCommand Plugin Installation](https://wiki.loxberry.de/plugins/teslacmd/start#installation).

The following notes may be outdated and should only be used if the link from above does not work anymore.

```
Install vehicle command API on Raspberry (I've not tested steps 3.-5. You may have to use 'su' to switch to the root user instead of 'sudo')

1. Install Tesla Command Plugin as usual for plugins.

2. If you've installed the plugin on a Raspberry PI, you may skip the manual build of the two binaries for the Tesla Vehicle Command SDK and go directly to step 6.

3. Install git (if not done already)

sudo apt-get install git
 
4. Install golang

mkdir ~/golang
cd ~/golang
# on 32-Bit Linux ARM
wget https://go.dev/dl/go1.23.2.linux-armv6l.tar.gz
tar -xzf go1.23.2.linux-armv6l.tar.gz
# on 64-Bit Linux ARM
wget https://go.dev/dl/go1.23.2.linux-arm64.tar.gz
tar -xzf go1.23.2.linux-arm64.tar.gz
#
export PATH=$PATH:/opt/loxberry/golang/go/bin
# Verify that Go is working
go version

for all other platforms:
see help files in golang

5. Install the Tesla Vehicle Command SDK

# install GIT as superuser - only if required
# su
# apt-get install git
# exit
cd ~
# git clone https://github.com/teslamotors/vehicle-command.git
git clone https://github.com/jan21493/vehicle-command.git
# git clone https://github.com/rigado/ble.git
git clone --single-branch --branch local-enhancements https://github.com/Jan21493/ble
cd vehicle-command/
# if not set yet: export PATH=$PATH:/opt/loxberry/golang/go/bin
go get ./...
 
6. Build the command line tools from the Tesla Vehicle Command SDK

# set environment variables that are used for version information
HWINFO=$(cat /sys/firmware/devicetree/base/model|xargs --null printf "%s")
HWARCH=$(uname -m)
VCVERSION=$(git rev-parse --short HEAD)
TODAY=$(date "+%a, %d %b %Y %T")
echo "Hardware info: "$HWINFO
echo "Hardware architecure: "$HWARCH
echo "Vehicle-Command SDK version: "$VCVERSION
echo "Date: "$TODAY
 
# build tesla-control utility - may take a while
cd cmd/tesla-control
go build -ldflags "-X 'main.version=$VCVERSION' -X 'main.hwinfo=$HWINFO' -X 'main.hwarch=$HWARCH' -X 'main.today=$TODAY'"  ./...
 
# build tesla-scan utility - may take a while
cd ../tesla-scan
go build -ldflags "-X 'main.version=$VCVERSION' -X 'main.hwinfo=$HWINFO' -X 'main.hwarch=$HWARCH' -X 'main.today=$TODAY'"  ./...
 
# build tesla-keygen utility - may take a while
cd ../tesla-keygen
go build ./...
cd ..

7. Copy binary files (Raspberry PI, 64-bit) to local local bin directory as root user 
   and add rights to 'tesla-control' and 'tesla-scan' executable to allow BLE commands

# execute as Superuser/root
su
cd /opt/loxberry
mv ./vehicle-command/cmd/tesla-control/tesla-control /usr/local/bin/
setcap 'cap_net_admin=eip' /usr/local/bin/tesla-control
mv ./vehicle-command/cmd/tesla-keygen/tesla-keygen /usr/local/bin/
mv ./vehicle-command/cmd/tesla-scan/tesla-scan /usr/local/bin/
setcap 'cap_net_admin=eip' /usr/local/bin/tesla-scan
exit

8. (optional) Add following lines to /etc/sudoers.d/lbdefaults to allow start of bluetooth daemon by loxberry user

/etc/sudoers.d# vi /etc/sudoers.d/lbdefaults

loxberry ALL = NOPASSWD: /bin/systemctl start bluetooth.service
loxberry ALL = NOPASSWD: /bin/systemctl restart bluetooth.service
loxberry ALL = NOPASSWD: /bin/systemctl stop bluetooth.service

9. Turn on Bluetooth via 'dietpi-config' (menu 'advanced options')

IMPORTANT: the bluetooth adapter is disabled by default in Diet PI!

10. Reboot your Loxberry

sudo reboot

11. Log in again as user loxberry on your Loxberry via ssh

12. (optional9 Create a new private and public key pair. You should use the VIN of your car as the name of the key.

Since version 0.5 you can create the keypair directly from the GUI, so you don't have to use any command line tools.

cd /opt/loxberry/config/plugins/teslacmd/
tesla-keygen -key-file LRW31234567890123-private.pem create > LRW31234567890123-public.pem

13. Install the public key in your car - requires nfc key card to tap on center console. You may use a different device as your Loxberry, but you may have to be in or very close to the car

cd /opt/loxberry/config/plugins/teslacmd/
tesla-control -vin LRW31234567890123 -ble add-key-request ./LRW31234567890123-public.pem owner cloud_key

Once the public key is installed in your car you may delete this key.

14. (optional) Verify if you are able to retrieve the status from the car and send commands to it

tesla-control -ble -vin LRW31234567890123 -key-file /opt/loxberry/config/plugins/teslacmd/LRW31234567890123-private.pem body-controller-state
- output is different depending on the sleep state of the car

# sample output - vehicle is asleep:
{
	"vehicleLockState": "VEHICLELOCKSTATE_LOCKED",
	"vehicleSleepStatus": "VEHICLE_SLEEP_STATUS_ASLEEP",
	"userPresence": "VEHICLE_USER_PRESENCE_NOT_PRESENT"
}

# when the command is executed via URL to the Loxberry, the statuses are translated to numbers to make it easier for the Loxone Miniserver to process the values. See enums ClosureState_E, VehicleLockState_E, and VehicleSleepStatus_E
# in vehicle-command/pkg/protocol/protobuf/vcsec.proto - https://github.com/teslamotors/vehicle-command/blob/05bc5dd8d0649b4ccb45a765b9127d06f1050a6f/pkg/protocol/protobuf/vcsec.proto
{
	"vehicleLockState": 1,
	"vehicleSleepStatus": 2,
	"userPresence": 1
}

# sample output - vehicle is awake:
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


tesla-control -ble -vin LRW31234567890123 -key-file /opt/loxberry/data/plugins/teslacmd/LRW31234567890123-private.pem wake
- if everything was ok, then you don't get an output. Only if there are errors you get a message, but that message may not be very helpful.

tesla-control -ble -vin LRW31234567890123 -key-file /opt/loxberry/data/plugins/teslacmd/LRW31234567890123-private.pem honk
- you should hear the horn of your car if it works.

```

See https://shankar-k.medium.com/tesla-developer-api-guide-ble-key-pair-auth-and-vehicle-commands-part-3-485e4a357e7d for a different way to install the key in your car.


Some other tries with key ring, but skipped, because a passphrase is required to access the key.
```
# create a new key in keyring (default is '~/.tesla_keys') and save public key - you have to enter a password for the key
cd /opt/loxberry/data/plugins/teslacmd/
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