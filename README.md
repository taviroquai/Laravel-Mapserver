Laravel Mapserver package
=========================

Install
-------

Add to composer.json
```php
	require: {
		"taviroquai/mapserver": "dev-master"
	}
```

Add Service Provider on app/config/app.php
```php
	'providers' => array(
		...
		'Taviroquai\Mapserver\MapserverServiceProvider',
	),
```

Run php composer.phar update

Usage
-----
```php
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
```

Requirements
------------

Of course Laravel 4, Mapserver and MapScript must be installed


Features
--------
1. Check whether MapServer is installed or not at the requested machine
2. Opens a mapfile
3. Export a mapfile
4. Return a GetCapabilities response as Illuminate\HTTP\Response
5. Creates map image as Illuminate\HTTP\Response


Call for Collab
---------------

All GEO lovers are invited to fork and grow this project ;)