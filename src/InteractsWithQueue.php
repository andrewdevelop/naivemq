<?php 

namespace Core\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class InteractsWithQueue
{
	/**
	 * Connection instance.
	 * @var \PhpAmqpLib\Connection\AMQPStreamConnection
	 */
	protected $connection;

	/** @var string */
	protected $exchange = '';

	/** @var string */
	protected $queue = '';

	/** @var string */
	protected $routing_key = '';


	protected $host;
	protected $port;
	protected $login;
	protected $password;
	protected $vhost;

	protected $service_id;

	/**
	 * Class Constructor
	 * @param    $connection   
	 * @param    $channel   
	 */
	public function __construct($host, $port, $login, $password, $vhost, $service_id)
	{
		$this->host = $host;
		$this->port = $port;
		$this->login = $login;
		$this->password = $password;
		$this->vhost = $vhost;

		$this->service_id = $service_id;
		
		$this->connect();
	}


	public function connect()
	{
		$this->connection = new AMQPStreamConnection(
			$this->host, 
			$this->port, 
			$this->login, 
			$this->password, 
			$this->vhost
		);
		$this->channel = $this->connection->channel();

		return $this;
	}

	protected function initExchange($exchange_name, $exchange_type)
	{
		$args = [
			'exchange' 	  => $exchange_name,
			'type' 		  => $exchange_type,
			'passive' 	  => false,
			'durable' 	  => true, 	# the exchange will survive server restarts.
			'auto_delete' => false, # the exchange won't be deleted once the channel is closed.
			'internal' 	  => false,
			'nowait' 	  => false,
			'arguments'   => [],
			'ticket' 	  => null
		];
		call_user_func_array([$this->channel, 'exchange_declare'], $args);

		return $exchange_name;
	}


	protected function initQueue($queue_name, $bind_to_exchange = '', $routing_key = '', $custom = [])
	{
		$args = [
		    'queue' 	  => $queue_name,
		    'passive' 	  => false,
		    'durable' 	  => true,
		    'exclusive'   => false,
		    'auto_delete' => false,
		    'nowait' 	  => false,
		    'arguments'   => [],
		    'ticket' 	  => null
		];
		$args = array_merge($args, $custom);

		list($queue_name, ,) = call_user_func_array([$this->channel, 'queue_declare'], $args);

		if ($bind_to_exchange != '') {
			$this->channel->queue_bind($queue_name, $bind_to_exchange, $routing_key);
		}

		return $queue_name;
	}


	protected function close()
	{
		$this->channel->close();
		$this->connection->close();
	}	
}