<?php 

namespace Core\Messaging;

use Core\Contracts\Event;
use Core\EventSourcing\Projector;
use Core\Messaging\Contracts\Publisher;

class EventPublisher extends Projector
{
	protected $publisher;

	public function __construct(Publisher $publisher)
	{
		$this->publisher = $publisher;
	}

	public function handle($event_name, Event $event)
	{
		$routing_key = $event_name;
		$message = (string) $event;
		return $this->publisher->execute($message, $routing_key);
	}
}