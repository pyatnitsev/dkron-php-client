<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

function logger(string $mask, ...$params): void
{
    echo sprintf($mask, ...$params)."\n";
}

if (count($argv) > 1) {
    $params = array_filter(array_slice($argv, 1));
} else {
    logger('Run with real Dkron hosts');
    logger('Example: %s http://localhost:8080', $argv[0]);
    logger('Example with authenticate: %s http://localhost:8080 user password', $argv[0]);
    exit(0);
}

$client = new Updevru\Dkron\ApiClient(
    new Updevru\Dkron\Endpoint\EndpointCollection([
        [
            'url' => $params[0],
            'login' => $params[1] ?? null,
            'password' => $params[2] ?? null,
        ],
    ]),
    new Http\Client\Curl\Client(),
    new Nyholm\Psr7\Factory\Psr17Factory(),
    new Nyholm\Psr7\Factory\Psr17Factory()
);

$api = new Updevru\Dkron\Api($client, new Updevru\Dkron\Serializer\JMSSerializer());

$status = $api->getStatus();
logger('Dkron on host %s connected!', $params[0]);
logger('Agent name:%s version:%s', $status->getAgent()['name'], $status->getAgent()['version']);

$newJob = new Updevru\Dkron\Dto\JobDto();
$newJob->setName('test_job');
$newJob->setSchedule('*/2 * * * * *');
$newJob->setConcurrency(Updevru\Dkron\Dto\JobDto::CONCURRENCY_FORBID);
$newJob->setExecutor('shell');
$newJob->setExecutorConfig(['command' => 'echo Hello']);

logger(
    'Creating Job name:%s schedule: execute by %s command %s',
    $newJob->getName(),
    $newJob->getSchedule(),
    $newJob->getExecutor(),
    $newJob->getExecutorConfig()['command']
);

$api->jobs->createOrUpdateJob($newJob);
logger('Job created!');

logger('Run job test_job manualy');
$api->jobs->runJob('test_job');
logger('Job test_job running, waiting...');
sleep(5);

logger('Job executions:');
$showed = [];
for ($i = 0; $i <= 10; ++$i) {
    foreach ($api->executions->getExecutionsByJob('test_job') as $num => $execution) {
        if (in_array($execution->getId(), $showed, true)) {
            continue;
        }

        logger(
            'Job execution #%s started: %s finished: %s output:',
            $execution->getId(),
            $execution->getStartedAt()->format('c'),
            $execution->getFinishedAt()->format('c'),
        );
        logger($execution->getOutput());
        $showed[] = $execution->getId();
    }
    sleep(2);
}

logger('Disable job test_job');
$api->jobs->toggleJob('test_job');

logger('Deleting job test_job');
$api->jobs->deleteJob('test_job');
logger('Deleted job test_job');
