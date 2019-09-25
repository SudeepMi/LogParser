<?php

namespace Sudeep\LogReader;

use Illuminate\Support\ServiceProvider;


/**
 * LogReaderServiceProvider
 *
 * @package Sudeep\LogReader
 * @author Jackie Do <anhvudo@gmail.com>
 * @copyright 2017
 * @access public
 */
class LogReaderServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * Publishing package's config
         */
        $packageConfigPath = __DIR__ . '/../../config/config.php';
        $appconfigPath     = config_path('log-reader.php');

        $this->mergeConfigFrom($packageConfigPath, 'log-reader');

        $this->publishes([
            $packageConfigPath => $appconfigPath,
        ], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('log-reader', 'Sudeep\LogReader\LogReader');

        $this->registerCommands();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['log-reader'];
    }

    /**
     * Register commands
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->app->bind('command.log-reader.delete', 'Sudeep\LogReader\Console\Commands\LogReaderDeleteCommand');
        $this->app->bind('command.log-reader.detail', 'Sudeep\LogReader\Console\Commands\LogReaderDetailCommand');
        $this->app->bind('command.log-reader.file-list', 'Sudeep\LogReader\Console\Commands\LogReaderFileListCommand');
        $this->app->bind('command.log-reader.get', 'Sudeep\LogReader\Console\Commands\LogReaderGetCommand');
        $this->app->bind('command.log-reader.remove-file', 'Sudeep\LogReader\Console\Commands\LogReaderRemoveFileCommand');

        $this->commands('command.log-reader.delete');
        $this->commands('command.log-reader.detail');
        $this->commands('command.log-reader.file-list');
        $this->commands('command.log-reader.get');
        $this->commands('command.log-reader.remove-file');
    }
}
