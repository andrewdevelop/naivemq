<?php 

namespace Core\Messaging\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Core\Messaging\Contracts\RpcServer;
use Core\EventSourcing\DomainEvent;
use Core\Contracts\CommandBus;
use ReflectionClass;
use Exception;

class ConsumeRpc extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'rpc:server';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Listen to RPCs.';    

    /** @var CommandBus */
    protected $bus;

    /** @var RpcServer */
    protected $server;

    /**
     * Create a new command instance.
     */
    public function __construct(RpcServer $server, CommandBus $bus)
    {
    	parent::__construct();
        $this->server = $server;
        $this->bus = $bus;
    }


    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        $this->info("Strating new RPC server process ".getmypid());
        $this->server->execute(function($message) {
            return $this->handleCommand($message);
        });
    }

    public function handleCommand($message)
    {
        $payload = json_decode($message->body);
        
        $class = $payload->command;
        $params = isset($payload->params) ? $payload->params : [];

        $reflection = new ReflectionClass($class);
        $command = $reflection->newInstanceArgs($params);

        try {
            $result = $this->bus->execute($command);
            return [
                'status' => 200,
                'response' => json_encode($result)
            ];
        } catch (Exception $e) {
            return [
                'status' => $e->getCode(),
                'response' => $e->getMessage()
            ];
        }

    }

}