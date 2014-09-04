Laravel Mapserver package
=========================

Install
-------

Add to composer.json

	require: {
		"taviroquai/mapserver": "dev-master"
	}

Add Service Provider on app/config/app.php

	'providers' => array(
		...
		'Taviroquai\Mapserver\MapserverServiceProvider',
	),

Run php composer.phar update

Run artisan dump-autoload


Usage
-----

	// Create a MapServer instance
	$mapserver = new Taviroquai\Mapserver\Mapserver();

	// Create a new map object (mapObj)
	$map = $mapserver->createMap(
		'test',
		storage_path() . '/userdata/default.map',
		storage_path() . '/userdata/template.html'
	);

	// Return WMS capabilities
	$response = $mapserver->getCapabilitiesResponse($map);

	// Return map image as Illuminate response
    $response = $mapserver->getImageResponse($map);

	return $response;


Features
--------

TODO
