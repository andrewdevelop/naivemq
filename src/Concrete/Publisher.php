<?php 

namespace Core\Messaging\Concrete;

use Core\Messaging\Contracts\Publisher as PublisherContract;
use Core\Messaging\InteractsWithQueue;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Ramsey\Uuid\Uuid;

class Publisher extends InteractsWithQueue implements PublisherContract
{

	public function on($exchange = '', $queue = '', $routing_key = '')
	{
		$this->exchange = $exchange;
		$this->queue = $queue;
		$this->routing_key = $routing_key;

		return $this;
	}


	public function execute($message, $routing_key = null)
	{
		$this->connect();

		$this->correlation_id = (string) Uuid::uuid4();

		$msg = new AMQPMessage($message, [
			'correlation_id' => $this->correlation_id,
			'content_type' => 'application/json'
		]);
		$args = [
			'msg' 			=> $msg,
			'exchange' 		=> 'events',
			'routing_key' 	=> $this->service_id,
			'mandatory' 	=> false,
			'immediate' 	=> false,
			'ticket' 		=> null
		];
		call_user_func_array([$this->channel, 'basic_publish'],  $args);

		$this->close();

		return $msg;
	}
}