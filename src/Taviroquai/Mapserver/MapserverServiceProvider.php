<?php namespace Taviroquai\Mapserver;

use Illuminate\Support\ServiceProvider;
use Taviroquai\Mapserver\Mapserver;

class MapserverServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        $hostname = !empty($this->app['config']['mapserver.hostname']) ?
                $this->app['config']['mapserver.hostname']
                : 'localhost';
        $uri = !empty($this->app['config']['mapserver.uri']) ?
                $this->app['config']['mapserver.uri']
                : '/cgi-bin/mapserv';
		$this->app->singleton('mapserver', function() {
            return new Mapserver($hostname, $uri);
        });
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
