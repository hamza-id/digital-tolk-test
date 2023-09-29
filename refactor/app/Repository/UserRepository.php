<?php

namespace DTApi\Repository;

use Monolog\Logger;
use Illuminate\Http\Request;
use DTApi\Models\{Job, User};
use DTApi\Mailers\MailerInterface;

class UserRepository extends BaseRepository
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

    public function getUsersJobsHistory($user_id, Request $request)
    {
        $page       = $request->get('page');
        $pageNumber = isset($page) ? $page : 1;
        $user       = User::findOrFail($user_id);

        $usertype = null;
        $emergencyJobs = $noramlJobs = array();

        if ($user->is('customer')) {
            list($jobs, $usertype) = $this->getCustomerJobs($user);
        } elseif ($user->is('translator')) {
            list($jobs, $noramlJobs, $usertype, $numberPages) = $this->getTranslatorJobs($user, $pageNumber);
        }

        return [
            'emergencyJobs' => $emergencyJobs,
            'noramlJobs'    => $noramlJobs,
            'jobs'          => $jobs,
            'user'          => $user,
            'usertype'      => $usertype,
            'numberPages'   => $numberPages ?? 0,
            'pageNumber'    => $pageNumber,
        ];
    }

    protected function getCustomerJobs($user)
    {
        $jobs = $user->jobs()
            ->with('user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback', 'distance')
            ->whereIn('status', ['completed', 'withdrawbefore24', 'withdrawafter24', 'timedout'])
            ->orderBy('due', 'desc')
            ->paginate(15);

        return [$jobs, 'customer'];
    }

    protected function getTranslatorJobs($user, $pageNumber)
    {
        $jobs_ids     = Job::getTranslatorJobsHistoric($user->id, 'historic', $pageNumber);
        $total_jobs   = $jobs_ids->total();
        $numberPages  = ceil($total_jobs / 15);
        $usertype     = 'translator';

        return [$jobs_ids, $jobs_ids, $usertype, $numberPages];
    }
}
