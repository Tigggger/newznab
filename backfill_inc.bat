:Top

CD..
php.exe backfill_inc.php
CD..
CD testing
php.exe update_parsing.php
REM php.exe removespecial.php
REM php.exe update_cleanup.php
CD..
CD update_scripts
php.exe optimise_db.php
CD win_scripts

Sleep 15

GOTO TOP
