<?php

namespace DTApi\Repository;

use Carbon\Carbon;
use Monolog\Logger;
use DTApi\Models\{Job, User};
use DTApi\Helpers\{TeHelper, MailerInterface};

/**
 * Class BookingRepository
 * @package DTApi\Repository
 */
class BookingRepository extends BaseRepository
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

    /**
     * @param $user_id
     * @return array
     */
    public function getUsersJobs($user_id)
    {
        $authUser = User::find($user_id);
        $usertype = '';
        $emergencyJobs = $noramlJobs = array();
        if ($authUser && $authUser->is('customer')) {
            $jobs     = $authUser->jobs()->with('user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback')->whereIn('status', ['pending', 'assigned', 'started'])->orderBy('due', 'asc')->get();
            $usertype = 'customer';
        } elseif ($authUser && $authUser->is('translator')) {
            $jobs     = Job::getTranslatorJobs($authUser->id, 'new')->pluck('jobs')->all();
            $usertype = 'translator';
        }
        if ($jobs) {
            foreach ($jobs as $jobitem) {
                if ($jobitem->immediate == 'yes') {
                    $emergencyJobs[] = $jobitem;
                } else {
                    $noramlJobs[] = $jobitem;
                }
            }
            $noramlJobs = collect($noramlJobs)->each(function ($item, $key) use ($user_id) {
                $item['usercheck'] = Job::checkParticularJob($user_id, $item);
            })->sortBy('due')->all();
        }

        return [
            'emergencyJobs' => $emergencyJobs,
            'noramlJobs'    => $noramlJobs,
            'cuser'         => $authUser,
            'usertype'      => $usertype
        ];
    }


    /**
     * @param $user
     * @param $data
     * @return mixed
     */
    public function store($user, $data)
    {
        $response      = array();
        $immediateTime = 5;
        $consumerType  = $user->userMeta->consumer_type;


        if ($user->user_type === config('app.customer_role_id')) {
            $authUser = $user;

            // Define an array of required fields.
            $requiredFields = ['from_language_id', 'due_date', 'due_time', 'duration', 'customer_phone_type', 'customer_physical_type'];

            foreach ($requiredFields as $fieldName) {
                if (!isset($data[$fieldName]) || empty($data[$fieldName])) {
                    $response['status'] = 'fail';
                    $response['message'] = 'Du måste fylla in alla fält';
                    $response['field_name'] = $fieldName;
                    return $response;
                }
            }

            $data['customer_phone_type'] = isset($data['customer_phone_type']) ? 'yes' : 'no';
            $data['customer_physical_type'] = isset($data['customer_physical_type']) ? 'yes' : 'no';

            if ($data['immediate'] === 'yes') {
                $dueCarbon = Carbon::now()->addMinute($immediateTime);
                $data['due']        = $dueCarbon->format('Y-m-d H:i:s');
                $data['immediate']  = 'yes';
                $response['type']   = 'immediate';
                $data['customer_phone_type'] = 'yes';
            } else {
                $due = $data['due_date'] . ' ' . $data['due_time'];
                $response['type'] = 'regular';
                $dueCarbon        = Carbon::createFromFormat('m/d/Y H:i', $due);
                $data['due']      = $dueCarbon->format('Y-m-d H:i:s');

                if ($dueCarbon->isPast()) {
                    $response['status']  = 'fail';
                    $response['message'] = "Can't create booking in the past";
                    return $response;
                }
            }

            // Simplify gender and certified field assignment using arrays.
            $genderMapping = ['male' => 'Man', 'female' => 'Kvinna'];
            $certifiedMapping = [
                'both'     => ['normal', 'certified'],
                'yes'      => ['certified'],
                'n_law'    => ['normal', 'certified_in_law'],
                'n_health' => ['normal', 'certified_in_health']
            ];

            $data['gender'] = $genderMapping[$data['gender']] ?? null;
            $data['certified'] = $certifiedMapping[$data['certified']] ?? null;

            $jobTypeMapping = [
                'rwsconsumer' => 'rws',
                'ngo' => 'unpaid',
                'paid' => 'paid'
            ];

            $data['job_type'] = $jobTypeMapping[$consumerType] ?? null;
            $data['b_created_at'] = date('Y-m-d H:i:s');

            if (isset($due)) {
                $data['will_expire_at'] = TeHelper::willExpireAt($due, $data['b_created_at']);
            }

            $data['by_admin'] = isset($data['by_admin']) ? $data['by_admin'] : 'no';

            $job = $authUser->jobs()->create($data);

            $response['status'] = 'success';
            $response['id'] = $job->id;

            // Build job_for array based on gender and certified fields.
            $data['job_for'] = [];

            if ($data['gender'] !== null) {
                $data['job_for'][] = $genderMapping[$data['gender']];
            }

            if ($data['certified'] !== null) {
                $data['job_for'] = array_merge($data['job_for'], $certifiedMapping[$data['certified']]);
            }

            $data['customer_town'] = $authUser->userMeta->city;
            $data['customer_type'] = $authUser->userMeta->customer_type;
        } else {
            $response['status']  = 'fail';
            $response['message'] = 'Translator can not create booking';
        }

        return $response;
    }

    /**
     * @param $id
     * @param $data
     * @return mixed
     */

    public function updateJob($id, $data, $authUser)
    {
        $log_data    = [];
        $langChanged = false;
        $message     = null;

        $job                = Job::findOrFail($id);
        $current_translator = $job->translatorJobRel->whereNull('cancel_at')->first();

        if (is_null($current_translator)) {
            $current_translator = $job->translatorJobRel->whereNotNull('completed_at')->first();
        }

        $changeTranslator = $this->changeTranslator($current_translator, $data, $job);

        if ($changeTranslator['translatorChanged']) {
            $log_data[] = $changeTranslator['log_data'];
        }

        $changeDue = $this->changeDue($job->due, $data['due']);

        if ($changeDue['dateChanged']) {
            $old_time = $job->due;
            $job->due = $data['due'];
            $log_data[] = $changeDue['log_data'];
        }

        if ($job->from_language_id != $data['from_language_id']) {
            $log_data[] = [
                'old_lang' => TeHelper::fetchLanguageFromJobId($job->from_language_id),
                'new_lang' => TeHelper::fetchLanguageFromJobId($data['from_language_id']),
            ];
            $old_lang = $job->from_language_id;
            $job->from_language_id = $data['from_language_id'];
            $langChanged = true;
        }

        $changeStatus = $this->changeStatus($job, $data, $changeTranslator['translatorChanged']);

        if ($changeStatus['statusChanged']) {
            $log_data[] = $changeStatus['log_data'];
        }

        $job->admin_comments = $data['admin_comments'];

        $this->logger->addInfo('USER #' . $authUser->id . '(' . $authUser->name . ')' . ' has updated booking <a class="openjob" href="/admin/jobs/' . $id . '">#' . $id . '</a> with data: ', $log_data);

        $job->reference = $data['reference'];
        $job->save();

        if ($job->due <= Carbon::now()) {
            $message =  'Updated';
        } else {
            $notificationRepository = new NotificationRepository();
            if ($changeDue['dateChanged']) {
                $notificationRepository->sendChangedDateNotification($job, $old_time);
            }

            if ($changeTranslator['translatorChanged']) {
                $notificationRepository->sendChangedTranslatorNotification($job, $current_translator, $changeTranslator['new_translator']);
            }

            if ($langChanged) {
                $notificationRepository->sendChangedLangNotification($job, $old_lang);
            }
        }
        return $message;
    }
}
