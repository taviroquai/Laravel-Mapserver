<?php

namespace Taviroquai\Mapserver;

use GuzzleHttp\Client;

class Mapserver {

	/**
	 * Holds mapserver cgi path
	 *
	 * @var string The CGI URL where mapserver runs
	 */
	protected $path = '';

	/**
	 * Holds mapserver hostname
	 *
	 * @var string The hostname where mapserver runs
	 */
	protected $hostname = 'localhost';

	/**
	 * Holds mapserver hostname
	 *
	 * @var string The path where to save mapfiles
	 */
	protected $storagePath = '';

	/**
	 * Holds the boolean value whether mapserver is detected or not
	 *
	 * @var string The boolean value
	 */
	protected $isInstalled = false;

	/**
	 * Holds default mapfile string
	 *
	 * @var string The default mapfile string
	 */
	protected $defaultMap = <<<EOF
MAP
    NAME "default"
    DEBUG off

    MAXSIZE 2600
    SIZE 1920 1080
    UNITS meters
    EXTENT -180.0000 -90.0000 180.0000 90.0000

    # FONTSET "/path/to/fontset"
    # SYMBOLSET "/path/to/symbolset"

    PROJECTION
    	"init=epsg:4326"
    END

    IMAGECOLOR 255 255 255
    IMAGETYPE PNG
  
    WEB
        IMAGEPATH '/tmp/'
        IMAGEURL '/tmp/'

        METADATA
        	"wms_srs" "epsg:4326"
			"wms_name" "default"
			"wms_server_version" "1.1.1"
			"wms_format" "image/png"
			"wms_title" "default"
			"wms_onlineresource" ""
			"wms_srs" "EPSG:4326"
			"ows_enable_request" "*"
        END
        TEMPLATE "./template.html"
    END
END
EOF;

	/**
	 * Holds default map template
	 *
	 * @var string The default template
	 */
	protected $defaultTemplate = "<!-- MapServer Template -->\n<img src=\"[img]\">";

	/**
	 * Creates a new MapServer instance
	 *
	 * @param string $hostname The hostname where mapserver runs
	 * @param string $uri The URI where mapserver responds
	 */
	public function __construct($hostname = 'localhost', $uri = '/cgi-bin/mapserv')
	{
		$this->hostname = $hostname;
		$this->uri = $uri;
		$this->path = "http://" . $this->hostname . $this->uri;
	}

	/**
	 * Get the MapServer cgi path
	 *
	 * @return string The CGI URL where mapserver responds
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * Get the default template string
	 *
	 * @return string The default template
	 */
	public function getDefaultTemplate()
	{
		return $this->defaultTemplate;
	}

	/**
	 * Set storage path
     * 
     * @param string $path The storage path
	 */
	public function setStoragePath($path = './userdata')
	{
		if (!is_writeable(dirname($path))) {
			throw new \Exception("Error MapServer storage path is not writeable", 1);
		}
		$this->storagePath = $path;
	}

	/**
	 * Get storage path
	 *
	 * @return string The storage full path
	 */
	public function getStoragePath()
	{
		return $this->storagePath;
	}

	/**
	 * Checks whether MapServer CGI is installed or not
	 *
	 * @return boolean Whether mapserver is detected or not
	 */
	public function isInstalled()
	{
		if (!$this->isInstalled) {
			$client = new Client(['base_url' => $this->getPath()]);

			$response = $client->get("")->getStatusCode();
			if ($response != "200") {
				$this->isInstalled = false;
				throw new \Exception("Error MapServer not responding at " . $this->getPath(), 2);
			}
			if ($this->hostname == 'localhost') {
				if (!$this->mapscriptExists()) {
					$this->isInstalled = false;
					throw new \Exception("Error MapScript extension is not available", 3);
				}
			}
			$this->isInstalled = true;	
		}
		
		return $this->isInstalled;
	}

	/**
	 * Check whether mapscript extension is loaded or not
	 *
	 * @return boolean Whether local mapscript extension is exists or not
	 */
	public function mapscriptExists()
	{
		return extension_loaded('mapscript');
	}

	/**
	 * Returns the MapServer version as integer
	 *
	 * @return integer The local mapserver version
	 */
	public function getVersion()
	{
		return ms_GetVersionInt();
	}

	/**
	 * Returns the map capabilities
     * 
     * @param \mapObj $map The map instance
	 *
	 * @return string The capabilities response
	 */
	public function getCapabilitiesResponse(\mapObj $map)
	{
		$request = new \OWSRequestObj();
		$request->addparameter('map', $map->getMetaData('wms_onlineresource'));
		$request->addparameter('SERVICE', 'WMS');
		$request->addparameter('VERSION', '1.1.1');
		$request->addparameter('REQUEST', 'GetCapabilities');
		
		\ms_ioinstallstdouttobuffer();
		$map->owsDispatch($request);
		$buffer = \ms_iogetstdoutbufferstring();
		$contentType = \ms_iostripstdoutbuffercontenttype();
		$buffer = substr($buffer, strpos($buffer, '<?xml'));
		$response = \Response::make($buffer);
		$response->header('content-type', $contentType);
		return $response;
	}	

	/**
	 * Creates a new map - mapscript object
     * 
     * @param string $name         The name for the map
     * @param string $mapfilePath  The mapfile full path
     * @param string $templatePath The template path
	 *
	 * @return \mapObj The created map object
	 */
	public function createMap($name, $mapfilePath, $templatePath)
	{
		if (!\File::exists($mapfilePath)) {
			\File::put($mapfilePath, $this->defaultMap);
		}
		if (!\File::exists($templatePath)) {
			\File::put($templatePath, $this->defaultTemplate);
		}
		$map = new \mapObj($mapfilePath);
		$map->name = $name;
		$map->web->template = $templatePath;
		$map->setMetaData('wms_onlineresource', $this->getPath() . '?map=' . $mapfilePath);
		$map->save($mapfilePath);
		return $map;
	}

	/**
	 * Creates an image response from map
	 *
	 * @param \mapObj $map       The map object
	 * @param string  $imagePath The path where to save the image
     * 
	 * @return \Illuminate\Http\Response The image response
	 */
	public function getImageResponse(\mapObj $map, $imagePath = '/tmp/', $imageURL = '/tmp/')
	{
		$filename = $imagePath .'/'.$map->name;
		$map->web->set('imagepath', $imagePath);
		$image = $map->draw();
		$image->saveImage($filename);
		$response = \Response::make(\File::get($filename));
		$file = new \Symfony\Component\HttpFoundation\File\File($filename);
	    $response->header('content-type', $file->getMimeType());
	    return $response;
	}

}