<?php 

namespace Core\Messaging\Concrete;

use Core\Messaging\Contracts\Consumer as ConsumerContract;
use Core\Messaging\InteractsWithQueue;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class Consumer extends InteractsWithQueue implements ConsumerContract
{
	public function on($exchange = '', $queue = '', $routing_key = '')
	{
		$this->exchange = $exchange;
		$this->queue = $queue;
		$this->routing_key = $routing_key;
		
		return $this;
	}


	public function execute($callback)
	{
		$this->connect();

		$args = [
			'queue' 	   => 'evt_'.$this->service_id,
			'consumer_tag' => $this->service_id,
			'no_local' 	   => false,
			'no_ack' 	   => true, # если false то проигралось, потом после перезапуска еще раз... и еще раз.
			'exclusive'    => false,
			'nowait' 	   => false,
			'callback' 	   => $callback,
			'ticket' 	   => null,
			'arguments'    => [],
		];
		call_user_func_array([$this->channel, 'basic_consume'], $args);

		while ($this->channel->is_consuming()) {
		    $this->channel->wait();
		}

		$this->close();
	}

}