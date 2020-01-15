<?php 

namespace Core\Messaging\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Core\Messaging\Contracts\Consumer;
use Core\EventSourcing\DomainEvent;
use Core\EventSourcing\Contracts\EventDispatcher;
use Exception;

class ConsumeMessages extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'mq:server';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Listen to a queue.';    

    /** @var Consumer */
    protected $consumer;

    /**
     * Create a new command instance.
     */
    public function __construct(Consumer $consumer, EventDispatcher $dispatcher)
    {
    	parent::__construct();
        $this->consumer = $consumer;
        $this->dispatcher = $dispatcher;
    }


    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        $this->info("Strating new consumer process ".getmypid());
        $this->consumer->execute(function($message) {
            $this->handleEvent($message);
        });
    }

    /**
     * Convert message to domain event and dispatch it.
     * @param  string $message 
     * @return void
     */
    public function handleEvent($message)
    {
        $payload = json_decode($message->body, true);
        $event = new DomainEvent($payload);
        $this->dispatcher->dispatch($event);
    }

}