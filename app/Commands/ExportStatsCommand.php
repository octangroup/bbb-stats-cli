<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;
use App\Models\Meeting;

class ExportStatsCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'export';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Export stats stored in bbb inside a database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = env('BBB_PATH');
        $directories = File::directories($path);
        $this->info('Processing meetings data');
        foreach ($directories as $directory){
            $xml = File::get($directory.'/events.xml');
            $json = json_encode(simplexml_load_string($xml));

            $array = json_decode($json, true);
            $collection  = collect($array);
            $users = collect();

            foreach($collection['event'] as $event){
                if($event['@attributes']['eventname'] == 'ParticipantJoinEvent'){
                    $users->push($event['userId']);
                }
               
            }

            $groupedUsers = $users->mapToGroups(function ($item, $key) {
                return [$item];
            });

            $events_number = count($collection['event']);

            $meeting = Meeting::firstOrNew([
                'meeting_id' => $collection['meeting']['@attributes']['id']
            ]);

            $meeting->meeting_name = $collection['meeting']['@attributes']['name'];
            $meeting->origin = $collection['metadata']['@attributes']['bbb-origin'];
            $meeting->server_name = $collection['metadata']['@attributes']['bbb-origin-server-name'];
            $meeting->users = $groupedUsers->count();
            $meeting->start_date = date('Y-m-d H:i:s', $collection['event'][0]['timestampUTC']/1000);
            $meeting->end_date = date('Y-m-d H:i:s', $collection['event'][$events_number - 1]['timestampUTC']/1000);
            $meeting->save();
        }
        $this->info('Process complete');
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
