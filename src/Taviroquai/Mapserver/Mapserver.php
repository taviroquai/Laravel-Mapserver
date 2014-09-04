<?php

namespace Taviroquai\Mapserver;

use GuzzleHttp\Client;

class Mapserver {

	/**
	 * Holds mapserver cgi path
	 *
	 * @var string
	 */
	protected $path = '';

	/**
	 * Holds mapserver hostname
	 *
	 * @var string
	 */
	protected $hostname = 'localhost';

	/**
	 * Holds mapserver hostname
	 *
	 * @var string
	 */
	protected $storagePath = '';

	/**
	 * Holds mapserver hostname
	 *
	 * @var string
	 */
	protected $isInstalled = false;

	/**
	 * Holds default mapfile string
	 *
	 * @var string
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
	 * @var string
	 */
	protected $defaultTemplate = "<!-- MapServer Template -->\n<img src=\"[img]\">";

	/**
	 * Creates a new MapServer instance
	 *
	 * @param string $hostname
	 * @param string $uri
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
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * Get the default template string
	 *
	 * @return string
	 */
	public function getDefaultTemplate()
	{
		return $this->defaultTemplate;
	}

	/**
	 * Set storage path
	 *
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
	 * @return string
	 */
	public function getStoragePath()
	{
		return $this->storagePath;
	}

	/**
	 * Checks whether MapServer CGI is installed or not
	 *
	 * @return boolean
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
	 * @return boolean
	 */
	public function mapscriptExists()
	{
		return extension_loaded('mapscript');
	}

	/**
	 * Returns the MapServer version as integer
	 *
	 * @return integer
	 */
	public function getVersion()
	{
		return ms_GetVersionInt();
	}

	/**
	 * Returns the map capabilities
	 *
	 * @return string
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
	 * @return ms_mapObj
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
	 * @param \mapObj $map
	 * @param string $imagePath
	 * @return \Illuminate\Http\Response
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