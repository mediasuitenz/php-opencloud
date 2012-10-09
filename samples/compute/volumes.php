<?php
/**
 * (c)2012 Rackspace Hosting. See COPYING for license details
 *
 */
$start = time();
ini_set('include_path', './lib:'.ini_get('include_path'));
require('rackspace.inc');

/**
 * Relies upon environment variable settings — these are the same environment
 * variables that are used by python-novaclient. Just make sure that they're
 * set to the right values before running this test.
 */
define('AUTHURL', 'https://identity.api.rackspacecloud.com/v2.0/');
define('USERNAME', $_ENV['OS_USERNAME']);
define('TENANT', $_ENV['OS_TENANT_NAME']);
define('APIKEY', $_ENV['NOVA_API_KEY']);

define('VOLUMENAME', 'SampleVolume');
define('SERVERNAME', 'CBS-test-server');

/**
 * numbers each step
 */
function step($msg,$p1=NULL,$p2=NULL,$p3=NULL) {
    global $STEPCOUNTER;
    printf("\nStep %d. %s\n", ++$STEPCOUNTER, sprintf($msg,$p1,$p2,$p3));
}
function info($msg,$p1=NULL,$p2=NULL,$p3=NULL) {
    printf("  %s\n", sprintf($msg,$p1,$p2,$p3));
}
define('TIMEFORMAT', 'r');

step('Authenticate');
$rackspace = new OpenCloud\Rackspace(AUTHURL,
	array( 'username' => USERNAME,
		   'tenantName' => TENANT,
		   'apiKey' => APIKEY ));

step('Connect to the Compute Service');
$compute = $rackspace->Compute('cloudServersOpenStack', 'DFW');

/*
step('List Extensions');
$arr = $compute->Extensions();
foreach($arr as $item)
	print($item->alias."\n");
exit;
*/

step('Connect to the VolumeService');
$cbs = $rackspace->VolumeService('cloudBlockStorage', 'DFW');

step('Volume Types');
$list = $cbs->VolumeTypeList();
while($vtype = $list->Next()) {
	info('%s - %s', $vtype->id, $vtype->name);
}

step('Create a new Volume');
$volume = $cbs->Volume();
$volume->Create(array(
	'display_name' => VOLUMENAME,
	'display_description' => 'A sample volume for testing',
	'size' => 1
));

step('Listing volumes');
$list = $cbs->VolumeList();
while($vol = $list->Next()) {
	info('Volume: %s [%s] size=%d', 
		$vol->display_name, 
		$vol->display_description,
		$vol->size);
}

step('Find a server');
$slist = $compute->ServerList(TRUE, array('name'=>SERVERNAME));

if ($slist->Size() > 0) {
	$server = $slist->First();
}
else {
	step('Create a server');
	$server = $compute->Server();
	$server->Create(array(
		'name' => SERVERNAME,
		'flavor' => $compute->Flavor(2),
		'image' => $compute->
				ImageList(TRUE,array('name'=>'CentOS 6.3'))->
				Next()));
	$server->WaitFor('ACTIVE', 300, 'dot');
	print "\n";
}

step('Attach volume to server');
$server->AttachVolume($volume);	// use 'auto' device

step('Create a snapshot');
$snap = $volume->Snapshot();
$snap->Create();

step('DONE');
exit;

// callback for WaitFor
function dot($server) {
    printf("\r\t%s %s %3d%% %s",
        $server->id, $server->name, $server->progress, $server->status);
}
