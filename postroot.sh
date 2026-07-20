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

# Track overall installation errors
INSTALL_ERRORS=0

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
 
# Helper to run a command, log it, and handle failures consistently.
# NOTE: we do not exit the script if the command / module installation fails,
#       but we log the error and continue with the next steps.
run_cmd() {
  desc="$1"
  shift

  echo "<INFO> $desc"
  "$@"
  rc=$?
  if [ $rc -ne 0 ]; then
    echo "<ERROR> Failed: $desc (exit code $rc)"
    INSTALL_ERRORS=$((INSTALL_ERRORS + 1))
    return $rc
  fi
  echo "<OK> $desc"
  return 0
}

echo "<INFO> Copy pre-build binary tools from Tesla Vehicle Command SDK if Linux Version is 64-bit and ARMv8 architecture"
if [[ "$(uname -m)" == 'aarch64' ]]; then
   echo "<INFO> Copying pre-build binary tools for aarch64 architecture"
   run_cmd "Copying tesla-control" cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-control.aarch64 /usr/local/bin/tesla-control
   run_cmd "Copying tesla-keygen" cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-keygen.aarch64 /usr/local/bin/tesla-keygen
   run_cmd "Copying tesla-blescan" cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-blescan.aarch64 /usr/local/bin/tesla-blescan
   # add rights for BLE access to binary file
   run_cmd "Setting capabilities for tesla-control" setcap 'cap_net_admin=eip' /usr/local/bin/tesla-control
   run_cmd "Setting capabilities for tesla-blescan" setcap 'cap_net_admin=eip' /usr/local/bin/tesla-blescan
elif [[ "$(uname -m)" == 'armv7l' ]]; then
   echo "<INFO> Copying pre-build binary tools for armv7l architecture"
   run_cmd "Copying tesla-control" cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-control.armv7l /usr/local/bin/tesla-control
   run_cmd "Copying tesla-keygen" cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-keygen.armv7l /usr/local/bin/tesla-keygen
   run_cmd "Copying tesla-blescan" cp -f -r $LBHOMEDIR/bin/plugins/$PDIR/tesla-blescan.armv7l /usr/local/bin/tesla-blescan
   # add rights for BLE access to binary file
   run_cmd "Setting capabilities for tesla-control" setcap 'cap_net_admin=eip' /usr/local/bin/tesla-control
   run_cmd "Setting capabilities for tesla-blescan" setcap 'cap_net_admin=eip' /usr/local/bin/tesla-blescan
else
   echo "<ERROR> No pre-build binary tools available for this architecture ($(uname -m)). Please build the tools (tesla-control, tesla-keygen, tesla-blescan) from source code."
   INSTALL_ERRORS=$((INSTALL_ERRORS + 1))
fi

if [ $INSTALL_ERRORS -gt 0 ]; then
  echo "<ERROR> POSTROOT completed with $INSTALL_ERRORS error(s). Some features may not work correctly."
else
  echo "<INFO> POSTROOT script completed!"
fi

# Exit with Status 0 - non-critical errors (e.g. optional Perl modules) should
# not abort the installation. Change to 'exit $INSTALL_ERRORS' if you want the
# installer to treat any failure as a hard error.
exit 0