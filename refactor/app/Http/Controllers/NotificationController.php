<?php

namespace DTApi\Http\Controllers;

use App\Http\Requests\Email\{Create as JobEmail};
use DTApi\Repository\NotificationRepository;
use App\Http\Requests\Job\JobValidate;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

/**
 * Class NotificationController
 * @package DTApi\Http\Controllers
 */
class NotificationController extends Controller
{
    /**
     * @var NotificationRepository
     */
    protected $repository;

    /**
     * NotificationController constructor.
     * @param NotificationRepository $notificationRepository
     */
    public function __construct(NotificationRepository $notificationRepository)
    {
        $this->repository = $notificationRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(JobEmail $request)
    {
        $data     = $request->validated();
        $response = $this->repository->storeJobEmail($data);
        return $this->__sendResponse($response);
    }

    /**
     * Sends Notification to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendNotifications(JobValidate $request)
    {
        $code     = 200;
        $message  = 'Push Notification send successfully!';
        $response = array();
        try {
            $data     = $request->validated();
            $job      = $this->repository->find($data->job_id);
            $job_data = $this->repository->jobToData($job);

            $this->repository->sendNotificationTranslator($job, $job_data, '*');
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code    = $e->getCode();
            Log::error($e);
        }

        return $this->__sendResponse($response, $code, $message);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(JobValidate $request)
    {
        $code     = 200;
        $message  = 'Push Notification send successfully!';
        $response = array();
        try {
            $data     = $request->validated();
            $job      = $this->repository->find($data->job_id);
            $job_data = $this->repository->jobToData($job);

            $this->repository->sendSMSNotificationToTranslator($job, $job_data, '*');
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code    = $e->getCode();
            Log::error($e);
        }

        return $this->__sendResponse($response, $code, $message);
    }
}
