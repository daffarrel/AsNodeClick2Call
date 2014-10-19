#!/bin/bash

echo "Prepare to start Asterisk click2call service"
rm -f asterisk/config/moveivr_click2call_moh.conf
rm -rf /tmp/click2calmoh_*
touch asterisk/config/moveivr_click2call_moh.conf
forever stop 0
forever start app.js

echo "Service started"
