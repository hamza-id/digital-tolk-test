<?php

namespace DTApi\Repository;

use Monolog\Logger;
use DTApi\Models\{Job, Distance};
use DTApi\Mailers\MailerInterface;


/**
 * Class DistanceRepository
 * @package DTApi\Repository
 */
class DistanceRepository extends BaseRepository
{
    protected $model;
    protected $mailer;
    protected $logger;

    public function __construct(Job $model, MailerInterface $mailer, Logger $logger)
    {
        parent::__construct($model);
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    public function distance($data)
    {
        $distance         = $data['distance'] ?? null;
        $time             = $data['time'] ?? null;
        $job_id           = $data['job_id'] ?? null;
        $session          = $data['session_time'] ?? null;
        $admin_comment    = $data['admin_comment'] ?? null;
        $flagged          = $data['flagged'] == true ? 'yes' : 'no';
        $by_admin         = $data['by_admin'] == true ? 'yes' : 'no';
        $manually_handled = $data['manually_handled'] == true ? 'yes' : 'no';

        if ($time || $distance) {
            Distance::where('job_id', '=', $job_id)->update(['distance' => $distance, 'time' => $time]);
        }

        if ($admin_comment || $session || $flagged || $manually_handled || $by_admin) {
            Job::where('id', $job_id)->update(
                [
                    'admin_comments'   => $admin_comment,
                    'flagged'          => $flagged,
                    'session_time'     => $session,
                    'manually_handled' => $manually_handled,
                    'by_admin'         => $by_admin,
                ]
            );
        }
    }
}
