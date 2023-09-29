<?php

namespace DTApi\Repository;

use Event;
use Carbon\Carbon;
use Monolog\Logger;
use DTApi\Helpers\TeHelper;
use DTApi\Models\{Job, Translator, UserLanguages};
use DTApi\Mailers\{AppMailer, MailerInterface};
use DTApi\Events\{SessionEnded, JobWasCanceled};

/**
 * Class JobRepository
 * @package DTApi\Repository
 */
class JobRepository extends BaseRepository
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
     * @param array $post_data
     */

    public function jobEnd($postData = [])
    {
        $completedDate = now();
        $jobId         = $postData["job_id"];
        $job           = Job::with('translatorJobRel')->find($jobId);

        $dueDate         = $job->due;
        $startTime       = date_create($dueDate);
        $endTime         = date_create($completedDate);
        $diff            = date_diff($endTime, $startTime);
        $sessionInterval = $diff->format('%h:%i:%s');

        $job->end_at        = $completedDate;
        $job->status        = 'completed';
        $job->session_time  = $sessionInterval;

        $user    = $job->user;
        $email   = !empty($job->user_email) ? $job->user_email : $user->email;
        $name    = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;

        $sessionTimeExplode = explode(':', $job->session_time);
        $sessionTime        = $sessionTimeExplode[0] . ' tim ' . $sessionTimeExplode[1] . ' min';

        $data = [
            'user'          => $user,
            'job'           => $job,
            'session_time'  => $sessionTime,
            'for_text'      => 'faktura',
        ];

        Mail::send('emails.session-ended', $data, function ($message) use ($email, $name, $subject) {
            $message->to($email, $name)->subject($subject);
        });

        $job->save();

        $translator = $job->translatorJobRel->whereNull('completed_at')->whereNull('cancel_at')->first();

        event(new SessionEnded($job, ($postData['userid'] == $job->user_id) ? $translator->user_id : $job->user_id));

        $translatorUser  = $translator->user;
        $translatorEmail = $translatorUser->email;
        $translatorName  = $translatorUser->name;

        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
        $data = [
            'user'         => $translatorUser,
            'job'          => $job,
            'session_time' => $sessionTime,
            'for_text'     => 'lön',
        ];

        Mail::send('emails.session-ended', $data, function ($message) use ($translatorEmail, $translatorName, $subject) {
            $message->to($translatorEmail, $translatorName)->subject($subject);
        });

        $translator->completed_at = $completedDate;
        $translator->completed_by = $postData['userid'];
        $translator->save();
    }


    /**
     * @param $data
     * @param $user
     */
    public function acceptJob($job_id, $user)
    {
        $authUser = $user;
        $job = Job::findOrFail($job_id);
        if (!Job::isTranslatorAlreadyBooked($job_id, $authUser->id, $job->due)) {
            if ($job->status == 'pending' && Job::insertTranslatorJobRel($authUser->id, $job_id)) {
                $job->status = 'assigned';
                $job->save();
                $user = $job->user()->get()->first();
                $mailer = new AppMailer();

                if (!empty($job->user_email)) {
                    $email = $job->user_email;
                    $name = $user->name;
                    $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';
                } else {
                    $email = $user->email;
                    $name = $user->name;
                    $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';
                }
                $data = [
                    'user' => $user,
                    'job'  => $job
                ];
                $mailer->send($email, $name, $subject, 'emails.job-accepted', $data);
            }

            $jobs     = $this->getPotentialJobs($authUser);
            $response = array();
            $response['list']   = json_encode(['jobs' => $jobs, 'job' => $job], true);
            $response['status'] = 'success';
        } else {
            $response['status']  = 'fail';
            $response['message'] = 'Du har redan en bokning den tiden! Bokningen är inte accepterad.';
        }

        return $response;
    }

    /*Function to accept the job with the job id*/
    public function acceptJobWithId($job_id, $authUser)
    {
        $job      = Job::findOrFail($job_id);
        $response = array();

        if (!Job::isTranslatorAlreadyBooked($job_id, $authUser->id, $job->due)) {
            if ($job->status == 'pending' && Job::insertTranslatorJobRel($authUser->id, $job_id)) {
                $job->status = 'assigned';
                $job->save();
                $user = $job->user()->get()->first();
                $mailer = new AppMailer();

                if (!empty($job->user_email)) {
                    $email = $job->user_email;
                    $name = $user->name;
                } else {
                    $email = $user->email;
                    $name = $user->name;
                }
                $subject = 'Bekräftelse - tolk har accepterat er bokning (bokning # ' . $job->id . ')';
                $data = [
                    'user' => $user,
                    'job'  => $job
                ];
                $mailer->send($email, $name, $subject, 'emails.job-accepted', $data);

                $data = array();
                $data['notification_type'] = 'job_accepted';
                $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
                $msg_text = array('en' => 'Din bokning för ' . $language . ' translators, ' . $job->duration . 'min, ' . $job->due . ' har accepterats av en tolk. Vänligen öppna appen för att se detaljer om tolken.');
                if ($this->isNeedToSendPush($user->id)) {
                    $users_array = array($user);
                    $this->sendPushNotificationToSpecificUsers($users_array, $job_id, $data, $msg_text, $this->isNeedToDelayPush($user->id));
                }
                // Your Booking is accepted sucessfully
                $response['status'] = 'success';
                $response['list']['job'] = $job;
                $response['message'] = 'Du har nu accepterat och fått bokningen för ' . $language . 'tolk ' . $job->duration . 'min ' . $job->due;
            } else {
                // Booking already accepted by someone else
                $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
                $response['status'] = 'fail';
                $response['message'] = 'Denna ' . $language . 'tolkning ' . $job->duration . 'min ' . $job->due . ' har redan accepterats av annan tolk. Du har inte fått denna tolkning';
            }
        } else {
            // You already have a booking the time
            $response['status'] = 'fail';
            $response['message'] = 'Du har redan en bokning den tiden ' . $job->due . '. Du har inte fått denna tolkning';
        }
        return $response;
    }

    public function cancelJobAjax($job_id, $user)
    {
        $response = array();
        $authUser = $user;
        $job = Job::findOrFail($job_id);
        $translator = Job::getJobsAssignedTranslatorDetail($job);
        if ($authUser->is('customer')) {
            $job->withdraw_at = Carbon::now();
            if ($job->withdraw_at->diffInHours($job->due) >= 24) {
                $job->status = 'withdrawbefore24';
                $response['jobstatus'] = 'success';
            } else {
                $job->status = 'withdrawafter24';
                $response['jobstatus'] = 'success';
            }
            $job->save();
            Event::fire(new JobWasCanceled($job));
            $response['status'] = 'success';
            $response['jobstatus'] = 'success';
            if ($translator) {
                $data = array();
                $data['notification_type'] = 'job_cancelled';
                $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
                $msg_text = array(
                    "en" => 'Kunden har avbokat bokningen för ' . $language . 'tolk, ' . $job->duration . 'min, ' . $job->due . '. Var god och kolla dina tidigare bokningar för detaljer.'
                );
                if ($this->isNeedToSendPush($translator->id)) {
                    $users_array = array($translator);
                    $this->sendPushNotificationToSpecificUsers($users_array, $job_id, $data, $msg_text, $this->isNeedToDelayPush($translator->id)); // send Session Cancel Push to Translaotor
                }
            }
        } else {
            if ($job->due->diffInHours(Carbon::now()) > 24) {
                $customer = $job->user()->get()->first();
                if ($customer) {
                    $data = array();
                    $data['notification_type'] = 'job_cancelled';
                    $language = TeHelper::fetchLanguageFromJobId($job->from_language_id);
                    $msg_text = array(
                        "en" => 'Er ' . $language . 'tolk, ' . $job->duration . 'min ' . $job->due . ', har avbokat tolkningen. Vi letar nu efter en ny tolk som kan ersätta denne. Tack.'
                    );
                    if ($this->isNeedToSendPush($customer->id)) {
                        $users_array = array($customer);
                        $this->sendPushNotificationToSpecificUsers($users_array, $job_id, $data, $msg_text, $this->isNeedToDelayPush($customer->id));     // send Session Cancel Push to customer
                    }
                }
                $job->status = 'pending';
                $job->created_at = date('Y-m-d H:i:s');
                $job->will_expire_at = TeHelper::willExpireAt($job->due, date('Y-m-d H:i:s'));
                $job->save();
                Job::deleteTranslatorJobRel($translator->id, $job_id);

                $data = $this->jobToData($job);

                $notication = new NotificationRepository();
                $notication->sendNotificationTranslator($job, $data, $translator->id);   // send Push all sutiable translators
                $response['status'] = 'success';
            } else {
                $response['status'] = 'fail';
                $response['message'] = 'Du kan inte avboka en bokning som sker inom 24 timmar genom DigitalTolk. Vänligen ring på +46 73 75 86 865 och gör din avbokning over telefon. Tack!';
            }
        }
        return $response;
    }

    /*Function to get the potential jobs for paid,rws,unpaid translators*/
    public function getPotentialJobs($authUser)
    {
        $cuser_meta = $authUser->userMeta;
        $job_type = 'unpaid';
        $translator_type = $cuser_meta->translator_type;
        if ($translator_type == 'professional')
            $job_type = 'paid';   /*show all jobs for professionals.*/
        else if ($translator_type == 'rwstranslator')
            $job_type = 'rws';  /* for rwstranslator only show rws jobs. */
        else if ($translator_type == 'volunteer')
            $job_type = 'unpaid';  /* for volunteers only show unpaid jobs. */

        $languages = UserLanguages::where('user_id', '=', $authUser->id)->get();
        $userlanguage = collect($languages)->pluck('lang_id')->all();
        $gender = $cuser_meta->gender;
        $translator_level = $cuser_meta->translator_level;
        $job_ids = Job::getJobs($authUser->id, $job_type, 'pending', $userlanguage, $gender, $translator_level);
        foreach ($job_ids as $k => $job) {
            $jobuserid = $job->user_id;
            $job->specific_job = Job::assignedToPaticularTranslator($authUser->id, $job->id);
            $job->check_particular_job = Job::checkParticularJob($authUser->id, $job);
            $checktown = Job::checkTowns($jobuserid, $authUser->id);

            if ($job->specific_job == 'SpecificJob')
                if ($job->check_particular_job == 'userCanNotAcceptJob')
                    unset($job_ids[$k]);

            if (($job->customer_phone_type == 'no' || $job->customer_phone_type == '') && $job->customer_physical_type == 'yes' && $checktown == false) {
                unset($job_ids[$k]);
            }
        }
        return $job_ids;
    }

    public function endJob($post_data)
    {
        $completeddate = date('Y-m-d H:i:s');
        $jobid = $post_data["job_id"];
        $job_detail = Job::with('translatorJobRel')->find($jobid);

        if ($job_detail->status != 'started')
            return ['status' => 'success'];

        $duedate = $job_detail->due;
        $start = date_create($duedate);
        $end = date_create($completeddate);
        $diff = date_diff($end, $start);
        $interval = $diff->h . ':' . $diff->i . ':' . $diff->s;
        $job = $job_detail;
        $job->end_at = date('Y-m-d H:i:s');
        $job->status = 'completed';
        $job->session_time = $interval;

        $user = $job->user()->get()->first();
        if (!empty($job->user_email)) {
            $email = $job->user_email;
        } else {
            $email = $user->email;
        }
        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
        $session_explode = explode(':', $job->session_time);
        $session_time = $session_explode[0] . ' tim ' . $session_explode[1] . ' min';
        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $session_time,
            'for_text'     => 'faktura'
        ];
        $mailer = new AppMailer();
        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        $job->save();

        $tr = $job->translatorJobRel()->where('completed_at', Null)->where('cancel_at', Null)->first();

        Event::fire(new SessionEnded($job, ($post_data['user_id'] == $job->user_id) ? $tr->user_id : $job->user_id));

        $user = $tr->user()->first();
        $email = $user->email;
        $name = $user->name;
        $subject = 'Information om avslutad tolkning för bokningsnummer # ' . $job->id;
        $data = [
            'user'         => $user,
            'job'          => $job,
            'session_time' => $session_time,
            'for_text'     => 'lön'
        ];
        $mailer = new AppMailer();
        $mailer->send($email, $name, $subject, 'emails.session-ended', $data);

        $tr->completed_at = $completeddate;
        $tr->completed_by = $post_data['user_id'];
        $tr->save();
        $response['status'] = 'success';
        return $response;
    }

    public function reopen($request)
    {
        $jobId  = $request['jobid'];
        $userId = $request['userid'];
        $currenTime = now();

        $jobData    = Job::find($jobId)->toArray();
        $reopenData = [
            'status'         => 'pending',
            'created_at'     => $currenTime,
            'will_expire_at' => TeHelper::willExpireAt($jobData['due'], $currenTime),
        ];

        $translatorData = [
            'created_at'        => $currenTime,
            'cancel_at'         => $currenTime,
            'updated_at'        => $currenTime,
            'will_expire_at'    => TeHelper::willExpireAt($jobData['due'], $currenTime),
            'user_id'           => $userId,
            'job_id'            => $jobId,
        ];

        if ($jobData['status'] != 'timedout') {
            Job::where('id', $jobId)->update($reopenData);
            $newJobId = $jobId;
        } else {
            $jobData['status']              = 'pending';
            $jobData['created_at']          = $currenTime;
            $jobData['updated_at']          = $currenTime;
            $jobData['will_expire_at']      = TeHelper::willExpireAt($jobData['due'], $currenTime);
            $jobData['cust_16_hour_email']  = 0;
            $jobData['cust_48_hour_email']  = 0;
            $jobData['admin_comments']      = 'This booking is a reopening of booking #' . $jobId;
            $newJob = Job::create($jobData);
            $newJobId = $newJob->id;
        }

        Translator::where('job_id', $jobId)->whereNull('cancel_at')->update(['cancel_at' => $currenTime]);
        Translator::create($translatorData);

        if (isset($newJobId)) {
            $this->sendNotificationByAdminCancelJob($newJobId);
            $message =  ["Tolk cancelled!"];
        } else {
            $message = ["Please try again!"];
        }
        return $message;
    }


    public function customerNotCall($jobId)
    {
        $completedDate = now();
        $job = Job::with('translatorJobRel')->find($jobId);

        $job->end_at = now();
        $job->status = 'not_carried_out_customer';
        $job->save();

        $translator = $job->translatorJobRel()->whereNull('completed_at')->whereNull('cancel_at')->first();
        $translator->completed_at = $completedDate;
        $translator->completed_by = $translator->user_id;
        $translator->save();

        $response['status'] = 'success';
        return $response;
    }
}
