<?php 

namespace Core\Messaging\Concrete;

use Core\Messaging\Contracts\RpcClient as RpcClientContract;
use Core\Messaging\InteractsWithQueue;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Ramsey\Uuid\Uuid;

class RpcClient extends InteractsWithQueue implements RpcClientContract
{

	protected $response = null;

	protected $callback_queue;

	protected $correlation_id;

	public function on($exchange = '', $queue = '', $routing_key = '')
	{
		$this->exchange = $exchange;
		$this->queue = $queue;
		$this->routing_key = ''; // ! HARDCODE
		
		if ($this->exchange != '') {
			$this->initExchange($this->exchange, AMQPExchangeType::DIRECT);
		}

		if ($this->queue != '') {
			$args = [
			    'durable' 	  => false, // !important false
			];
			$this->initQueue($this->queue, $this->exchange, $this->routing_key, $args);
		}

		// $this->exchange = '';
		// $this->queue = 'rpc_queue';

		return $this;
	}


	public function execute($to_service, $json)
	{
		$this->correlation_id = (string) Uuid::uuid4();

		/*
		 * Creates an anonymous exclusive callback queue.
		 * A client sends a request message and a server replies with a response message. 
		 * In order to receive a response we need to send a 'callback' queue address with the request. 
		 * We can use the default queue.
		 * $queue_name has a value like amq.gen-_U0kJVm8helFzQk9P0z9gg
		 */
		$args = [
		    'queue' 	  => '', # amq.gen-_U0kJVm8helFzQk9P0z9gg
		    'passive' 	  => false,
		    'durable' 	  => false,
		    'exclusive'   => true, # !important
		    'auto_delete' => false,
		    'nowait' 	  => false,
		    'arguments'   => [],
		    'ticket' 	  => null
		];
		list($this->callback_queue, ,) = call_user_func_array([$this->channel, 'queue_declare'], $args);

		
		if ($this->exchange != '') {
			$this->channel->queue_bind($this->callback_queue, $this->exchange, $this->callback_queue);
		}

		/**
		 * Subscribe to channel and wait for response.
		 */
		$this->channel->basic_consume(
			$this->callback_queue, 		# queue (callback)
			'',							# consumer tag
			false, 						# no local
			true, 						# no ack !!!
			false, 						# exclusive
			false, 						# no wait
			function (AMQPMessage $response) {
				if ($response->get('correlation_id') == $this->correlation_id) {
					$this->response = $response->body;
				}
			}
		);

		/*
		 * Create a message with two properties: reply_to, which is set to the 
		 * callback queue and correlation_id, which is set to a unique value for 
		 * every request
		 */
		$props = [
			'reply_to' => $this->callback_queue,
			'correlation_id' => $this->correlation_id,
			'content_type' => 'application/json'
		];
		$message = new AMQPMessage($json, $props);
		/* 
		$headers = new AMQPTable([
			'x-message-ttl' => 30000 * 100, # 30 sec
		]);
		$message->set('application_headers', $headers); 
		*/

		// Publish message.
		/*
		$args = [
			'msg' 			=> $message,
			'exchange' 		=> $this->exchange,
			'routing_key' 	=> $this->routing_key,
			'mandatory' 	=> false,
			'immediate' 	=> false,
			'ticket' 		=> null
		];
		var_dump($args);
		call_user_func_array([$this->channel, 'basic_publish'],  $args);
		*/
		# $queue = $this->queue;
		# $queue = 'rpc_'.$this->service_id;
		$queue = 'rpc_'.$to_service;
		$this->channel->basic_publish($message, $this->exchange, $queue);

		// Wait for response.
		while ($this->response === null) {
			$this->channel->wait();
		}

		// DON'T Close connections after successful response.
		// $this->close();

		// Return RPC response
		return $this->response;
	}
}