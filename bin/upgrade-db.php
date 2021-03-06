#!/usr/bin/php
<?php
error_reporting(2147483647);

try {
	require_once __DIR__ . '/../vendor/autoload.php';

    /*
     * Make sure we are not run from the server, an db upgrade can take too much time and
     * will easily be aborted by either a database, apache or browser timeout
     */
	SpotCommandline::initialize(array('reset-groupmembership', 'reset-securitygroups', 'reset-filters'), 
								array('reset-groupmembership' => false, 'reset-securitygroups' => false, 'reset-filters' => false,
									  'set-systemtype' => false, 'reset-password' => false, 'mass-userprefchange' => false));
	if (!SpotCommandline::isCommandline()) {
		die("upgrade-db.php can only be run from the console, it cannot be run from the web browser");
	} # if

	/*
	 * Create a DAO factory. We cannot use the bootstrapper here,
	 * because it validates for a valid settings etc. version.
	 */
    $bootstrap = new Bootstrap();
    $daoFactory = $bootstrap->getDaoFactory();
    $settings = $bootstrap->getSettings($daoFactory, false);
    $dbSettings = $bootstrap->getDbSettings();

	/*
	 * And actually start updating or creating the schema and settings
	 */
	echo "Updating schema..(" . $dbSettings['engine'] . ")" . PHP_EOL;
	
	$svcUpgradeBase = new Services_Upgrade_Base($daoFactory, $settings, $dbSettings['engine']);
	$svcUpgradeBase->database();
	echo "Schema update done" . PHP_EOL;
	echo "Updating settings" . PHP_EOL;
	$svcUpgradeBase->settings();
    $svcUpgradeBase->usenetState();
	echo "Settings update done" . PHP_EOL;
	$svcUpgradeBase->users($settings);
	echo "Updating users" . PHP_EOL;
	echo "Users' update done" . PHP_EOL;

	/* 
	 * If the user asked to change the system type..
	 */
	if (SpotCommandline::get('set-systemtype')) {
		echo "Resetting the system type of Spotweb to " . SpotCommandline::get('set-systemtype') . PHP_EOL;
		$svcUpgradeBase->resetSystemType(SpotCommandline::get('set-systemtype'));
		echo "System type changed" . PHP_EOL;
	} # if

	/* 
	 * If the user asked to change the preference of all users..
	 */
	if (SpotCommandline::get('mass-userprefchange')) {
		$prefToChange = explode("=", SpotCommandline::get('mass-userprefchange'));
		if (count($prefToChange) != 2) {
			throw new Exception("Please specify new preference as follows: perpage=10 or count_newspots=off");
		} # if

		echo "Mass changing a users' preference " . $prefToChange[0] .  " to a value of " . $prefToChange[1] . PHP_EOL;
		$svcUpgradeBase->massChangeUserPreferences($prefToChange[0], $prefToChange[1]);
		echo "Users' preferences changed" . PHP_EOL;
	} # if


	/* 
	 * If the user asked to reset the password of a user
	 */
	if (SpotCommandline::get('reset-password')) {
		echo "Resetting the password of '". SpotCommandline::get('reset-password') . "' to 'spotweb'" . PHP_EOL;
		$svcUpgradeBase->resetPassword(SpotCommandline::get('reset-password'));
		echo "Password changed" . PHP_EOL;
	} # if

	/* If the user asked to reset group membership, reset all group memberships */
	if (SpotCommandline::get('reset-securitygroups')) {
		echo "Resetting security groups to their default settings" . PHP_EOL;
		$svcUpgradeBase->resetSecurityGroups();
		echo "Reset security groups to their default settings done" . PHP_EOL;
	} # if


	/* 
	 * If the user asked to reset group membership, reset all group memberships.
	 */
	if (SpotCommandline::get('reset-groupmembership')) {
		echo "Resetting users' group membeship to the default" . PHP_EOL;
		$svcUpgradeBase->resetUserGroupMembership();
		echo "Reset of users' group membership done" . PHP_EOL;
	} # if

	/* 
	 * If the user asked to reset filters, do so
	 */
	if (SpotCommandline::get('reset-filters')) {
		echo "Resetting users' filters to the default" . PHP_EOL;
		$svcUpgradeBase->resetFilters();
		echo "Reset of users' filters done" . PHP_EOL;
	} # if

	echo "Performing basic analysis of database tables" . PHP_EOL;
	$svcUpgradeBase->analyze($settings);
	echo "Basic database optimalisation done" . PHP_EOL;
} 

catch(CacheMustBeMigratedException $x) {
    die("Your current Spotweb installation has an old way of storing Spotweb related files like images and NZB files. " . PHP_EOL .
        "We provide the script 'migrate-cache.php' to migrate the cache without losing your data. Depending on the " . PHP_EOL .
        "size of your cache this can take a very long time." . PHP_EOL . PHP_EOL .
        "Please run the 'bin/migrate-cache.php' script because attempting to run 'upgrade-db.php' again will erase your cache completely" . PHP_EOL);
}
catch(CacheMustBeMigrated2Exception $x) {
    die("Apologies for the inconvience, but Spotweb has once again changed the way we store files for cache. This " . PHP_EOL .
        "means you need to run the script 'migrate-cache2.php' again.  " . PHP_EOL .
        "Depending on the size of your cache this can take a very long time." . PHP_EOL . PHP_EOL .
        "Please run the 'bin/migrate-cache.php2' script again");
}
catch(SpotwebCannotBeUpgradedToooldException $x) {
	die("Your current Spotweb installation is too old to be upgraded to this current version of Spotweb. " . PHP_EOL . 
		"Please download an earlier version of Spotweb (https://github.com/spotweb/spotweb/zipball/" . $x->getMessage() . "), " . PHP_EOL .
		"run bin/upgrade-db.php using that version and then upgrade back to this version to run upgrade-db.php once more.");
} # SpotwebCannotBeUpgradedToooldException

catch(InvalidOwnSettingsSettingException $x) {
	echo "There is an error in your settings. Please open install.php to configure Spotweb" . PHP_EOL . PHP_EOL;
	echo $x->getMessage() . PHP_EOL;
} # InvalidOwnSettingsSettingException

catch(Exception $x) {
	echo PHP_EOL . PHP_EOL;
	echo 'SpotWeb crashed' . PHP_EOL . PHP_EOL;
	echo "Database schema or settings upgrade failed:" . PHP_EOL;
	echo "   " . $x->getMessage() . PHP_EOL;
	echo PHP_EOL . PHP_EOL;
	echo $x->getTraceAsString();
	die(1);
} # catch
