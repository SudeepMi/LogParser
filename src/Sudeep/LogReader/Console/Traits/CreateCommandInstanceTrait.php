<?php namespace Sudeep\LogReader\Console\Traits;

use Sudeep\LogReader\LogReader;

trait CreateCommandInstanceTrait
{
    /**
     * The LogReader instance.
     *
     * @var \Sudeep\LogReader\LogReader
     */
    protected $reader;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(LogReader $reader)
    {
        $this->reader = $reader;

        parent::__construct();
    }

    /**
     * Execute the console command.
     * This is alias of the method fire()
     *
     * @return mixed
     */
    public function handle()
    {
        return $this->fire();
    }
}
