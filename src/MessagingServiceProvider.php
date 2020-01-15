<?php 

namespace Core\Messaging;

use Illuminate\Support\ServiceProvider;
use Core\EventSourcing\Contracts\EventDispatcher;
use Core\Messaging\Contracts\Publisher as PublisherContract;
use Core\Messaging\Contracts\Consumer as ConsumerContract;
use Core\Messaging\Contracts\RpcClient as RpcClientContract;
use Core\Messaging\Contracts\RpcServer as RpcServerContract;
use Core\Messaging\Concrete\Publisher;
use Core\Messaging\Concrete\Consumer;
use Core\Messaging\Concrete\RpcClient;
use Core\Messaging\Concrete\RpcServer;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use InvalidArgumentException;

class MessagingServiceProvider extends ServiceProvider
{
	/**
	 * Register bindings in the container.
	 * @return void
	 */
	public function register()
	{
	    $this->mergeConfigFrom(__DIR__.'/../config/mq.php', 'mq');
	    $this->app->configure('mq');

        if (!$this->app->config->get('mq.service_id')) {
        	throw new InvalidArgumentException('Message queue not configured properly. Please declare a unique ID for your service in /config/mq.php file with key "service_id" or in your .env file with key "SERVICE_ID".');
        }

        $service_id = $this->app->config->get('mq.service_id');
        $host       = $this->app->config->get('mq.host');
        $port       = $this->app->config->get('mq.port');
        $login      = $this->app->config->get('mq.login');
        $password   = $this->app->config->get('mq.password');
        $vhost      = $this->app->config->get('mq.vhost');


        $this->app->singleton(PublisherContract::class, function() use ($host, $port, $login, $password, $vhost, $service_id) {
            return new Publisher($host, $port, $login, $password, $vhost, $service_id);
        });

        $this->app->singleton(ConsumerContract::class, function() use ($host, $port, $login, $password, $vhost, $service_id) {
            return new Consumer($host, $port, $login, $password, $vhost, $service_id);
        });

        $this->app->singleton(RpcClientContract::class, function() use ($host, $port, $login, $password, $vhost, $service_id) {
            $exchange = ''; // 'rpc'; // some bug here - using default
            $queue = 'rpc_'.$service_id; // 'rpc_user';
            $routing_key = '';

            $concrete = new RpcClient($host, $port, $login, $password, $vhost, $service_id);
            return $concrete->on($exchange, $queue, $routing_key);
        });

        $this->app->singleton(RpcServerContract::class, function() use ($host, $port, $login, $password, $vhost, $service_id) {
            $exchange = ''; // 'rpc'; // some bug here - using default
            $queue = 'rpc_'.$service_id; // 'rpc_user';
            $routing_key = '';

            $concrete = new RpcServer($host, $port, $login, $password, $vhost, $service_id);
            return $concrete->on($exchange, $queue, $routing_key);
        });        
	}

	/**
     * Perform post-registration booting of services.
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ConsumeMessages::class,
                Console\ConsumeRpc::class
            ]);
        }
    }


    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return [
            PublisherContract::class,
            ConsumerContract::class,
            RpcClientContract::class,
            RpcServerContract::class,
        ];
    }        
}