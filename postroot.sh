#!/bin/bash
 
# Shell script which is executed by bash *AFTER* complete installation is done
# (*AFTER* postinstall and *AFTER* postupdate). Use with caution and remember,
# that all systems may be different!
#
# Exit code must be 0 if executed successfull. 
# Exit code 1 gives a warning but continues installation.
# Exit code 2 cancels installation.
#
# !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
# Will be executed as user "root".
# !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
#
# You can use all vars from /etc/environment in this script.
#
# We add 5 additional arguments when executing this script:
# command <TEMPFOLDER> <NAME> <FOLDER> <VERSION> <BASEFOLDER>
#
# For logging, print to STDOUT. You can use the following tags for showing
# different colorized information during plugin installation:
#
# <OK> This was ok!"
# <INFO> This is just for your information."
# <WARNING> This is a warning!"
# <ERROR> This is an error!"
# <FAIL> This is a fail!"
 
# To use important variables from command line use the following code:
COMMAND=$0    # Zero argument is shell command
PTEMPDIR=$1   # First argument is temp folder during install
PSHNAME=$2    # Second argument is Plugin-Name for scipts etc.
PDIR=$3       # Third argument is Plugin installation folder
PVERSION=$4   # Forth argument is Plugin version
#LBHOMEDIR=$5 # Comes from /etc/environment now. Fifth argument is
              # Base folder of LoxBerry
PTEMPPATH=$6  # Sixth argument is full temp path during install (see also $1)
 
# Combine them with /etc/environment
PHTMLAUTH=$LBPHTMLAUTH/$PDIR
PHTML=$LBPHTML/$PDIR
PTEMPL=$LBPTEMPL/$PDIR
PDATA=$LBPDATA/$PDIR
PLOG=$LBPLOG/$PDIR # Note! This is stored on a Ramdisk now!
PCONFIG=$LBPCONFIG/$PDIR
PSBIN=$LBPSBIN/$PDIR
PBIN=$LBPBIN/$PDIR
 
#echo -n "<INFO> Current working folder is: "
#pwd
#echo "<INFO> Command is: $COMMAND"
#echo "<INFO> Temporary folder is: $PTEMPDIR"
#echo "<INFO> (Short) Name is: $PSHNAME"
#echo "<INFO> Installation folder is: $PDIR"
#echo "<INFO> Plugin version is: $PVERSION"
#echo "<INFO> Plugin CGI folder is: $PHTMLAUTH"
#echo "<INFO> Plugin HTML folder is: $PHTML"
#echo "<INFO> Plugin Template folder is: $PTEMPL"
#echo "<INFO> Plugin Data folder is: $PDATA"
#echo "<INFO> Plugin Log folder (on RAMDISK!) is: $PLOG"
#echo "<INFO> Plugin CONFIG folder is: $PCONFIG"
 
echo "<INFO> Copy pre-build binary tools from Tesla Vehicle Command SDK if Linux Version is 64-bit and ARMv8 architecture"
if [[ "$(uname -m)" == 'aarch64' ]]; then
   cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-control.aarch64 /usr/local/bin/tesla-control
   cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-keygen.aarch64 /usr/local/bin/tesla-keygen
   cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-scan.aarch64 /usr/local/bin/tesla-scan
   # add rights for BLE access to binary file
   setcap 'cap_net_admin=eip' /usr/local/bin/tesla-control
   setcap 'cap_net_admin=eip' /usr/local/bin/tesla-scan
elif [[ "$(uname -m)" == 'armv7l' ]]; then
   cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-control.armv7l /usr/local/bin/tesla-control
   cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-keygen.armv7l /usr/local/bin/tesla-keygen
   cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-scan.armv7l /usr/local/bin/tesla-scan
   # add rights for BLE access to binary file
   setcap 'cap_net_admin=eip' /usr/local/bin/tesla-control
   setcap 'cap_net_admin=eip' /usr/local/bin/tesla-scan
fi

# Exit with Status 0
exit 0