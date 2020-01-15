<?php 

namespace Core\Messaging\Concrete;

use Core\Messaging\Contracts\RpcServer as RpcServerContract;
use Core\Messaging\InteractsWithQueue;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Ramsey\Uuid\Uuid;

class RpcServer extends InteractsWithQueue implements RpcServerContract
{

	protected $response = null;

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
			$this->queue = $this->initQueue($this->queue, $this->exchange, $this->routing_key, $args);
		}
		

		// $this->exchange = '';
		// $this->queue = 'rpc_queue';

		$this->channel->queue_declare($this->queue, false, false, false, false);

		// if (php_sapi_name() == 'cli') {
		// 	echo " ■ Watchig for RPC \"".$this->queue."\" bound to exchange \"".$this->exchange."\"\n";
		// }

		return $this;
	}


	public function execute(callable $callable)
	{
		$callback = function ($request) use ($callable) {

			$correlation_id = $request->get('correlation_id');
			$reply_to_queue = $request->get('reply_to');
			$reply_channel = $request->delivery_info['channel'];

			# echo " ■■■ Recieving request from queue \"".$reply_to_queue."\" / exchange \"".$this->exchange."\"\n";

			// ! HARDCODE
			$response = $callable($request);

		    $msg = new AMQPMessage($response, [
		    	'correlation_id' => $correlation_id
		    ]);

		    $args = [
				'msg' => $msg,
				'exchange' => $this->exchange,
				'routing_key' => $reply_to_queue,
				'mandatory' => false,
				'immediate' => false,
				'ticket' => null
		    ];
		    call_user_func_array([$reply_channel, 'basic_publish'], $args);

		    $args = [
		    	'delivery_tag' => $request->delivery_info['delivery_tag'], 
		    	'multiple' => false
		    ];
		    call_user_func_array([$reply_channel, 'basic_ack'], $args);

		};

		$this->channel->basic_qos(null, 1, null);
		$this->channel->basic_consume($this->queue, '', false, false, false, false, $callback);

		while ($this->channel->is_consuming()) {
		    $this->channel->wait();
		}

		// Close connections after successful response.
		$this->close();

		// Return RPC response
		return $response;
	}
}